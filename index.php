<?php
session_start();

// ==========================================
// ১. ইউজার ডাটাবেজ ও কনফিগারেশন (MySQL লাগবে না)
// ==========================================
$users = [
    'admin'   => ['password' => 'admin123',   'role' => 'admin'],
    'manager' => ['password' => 'manager123', 'role' => 'manager'],
    'viewer'  => ['password' => 'viewer123',  'role' => 'viewer']
];

$base_upload_dir = __DIR__ . '/storage/';
if (!is_dir($base_upload_dir)) {
    mkdir($base_upload_dir, 0755, true);
}

// বর্তমান ডিরেক্টরি পাথ ট্র্যাকিং (URL এনকোডেড পাথ)
$current_sub_dir = isset($_GET['dir']) ? trim($_GET['dir'], '/') : '';

// সিকিউরিটি চেক: ডিরেক্টরি ট্রাভার্সাল (../) অ্যাটাক রোধ করা
if (strpos($current_sub_dir, '..') !== false) {
    die("❌ Security Alert: Directory traversal detected!");
}

$current_working_dir = $base_upload_dir . ($current_sub_dir ? $current_sub_dir . '/' : '');
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . '/';

// লগআউট হ্যান্ডলার
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// লগইন প্রসেস
$login_error = '';
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = strtolower($_POST['username']);
    $password = $_POST['password'];
    
    if (isset($users[$username]) && $users[$username]['password'] === $password) {
        $_SESSION['user'] = $username;
        $_SESSION['role'] = $users[$username]['role'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = 'ভুল ইউজারনেম অথবা পাসওয়ার্ড!';
    }
}

$is_logged_in = isset($_SESSION['user']);
$user_role = $_SESSION['role'] ?? 'viewer';

// অ্যাক্সেস কন্ট্রোল চেকার ফাংশন
function canManage() { global $user_role; return in_array($user_role, ['admin', 'manager']); }
function canDelete() { global $user_role; return $user_role === 'admin'; }

// ==========================================
// ২. নতুন ফোল্ডার তৈরি হ্যান্ডলার
// ==========================================
if ($is_logged_in && canManage() && isset($_POST['new_folder_name'])) {
    $folder_name = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $_POST['new_folder_name']); // ক্লিন নেম
    if (!empty($folder_name)) {
        $target_folder = $current_working_dir . $folder_name;
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0755, true);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . ($current_sub_dir ? '?dir=' . urlencode($current_sub_dir) : ''));
    exit;
}

// ==========================================
// ৩. ফাইল ও ফোল্ডার আপলোড হ্যান্ডলার (AJAX Supported)
// ==========================================
if ($is_logged_in && canManage() && isset($_FILES['file_to_upload'])) {
    $forbidden_exts = ['php', 'phtml', 'exe', 'sh', 'htaccess'];
    
    // ফোল্ডার আপলোডের ক্ষেত্রে রিলেটিভ পাথ জেনারেট করা
    $relative_path = isset($_POST['relative_path']) ? trim($_POST['relative_path'], '/') : '';
    
    if (strpos($relative_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid path']);
        exit;
    }

    $file_name = basename($_FILES['file_to_upload']['name']);
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (in_array($ext, $forbidden_exts)) {
        echo json_encode(['status' => 'error', 'message' => 'Extension blocked!']);
        exit;
    }

    // আপলোড ডেস্টিনেশন নির্ধারণ করা (ফোল্ডার আপলোড হলে ডাইনামিক সাব-ডিরেক্টরি তৈরি হবে)
    $upload_target_dir = $current_working_dir;
    if (!empty($relative_path)) {
        $upload_target_dir = $current_working_dir . dirname($relative_path) . '/';
        if (!is_dir($upload_target_dir)) {
            mkdir($upload_target_dir, 0755, true);
        }
    }

    $target_file = $upload_target_dir . $file_name;
    
    if (move_uploaded_file($_FILES['file_to_upload']['tmp_name'], $target_file)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// ==========================================
// ৪. ডিলিট হ্যান্ডলার (ফোল্ডার এবং ফাইল উভয়ই)
// ==========================================
if ($is_logged_in && canDelete() && isset($_GET['delete'])) {
    $item_to_delete = basename($_GET['delete']);
    $target_path = $current_working_dir . $item_to_delete;
    
    if (file_exists($target_path)) {
        if (is_dir($target_path)) {
            // রিকার্সিভ ফোল্ডার ডিলিট ফাংশন
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($target_path);
        } else {
            unlink($target_path);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . ($current_sub_dir ? '?dir=' . urlencode($current_sub_dir) : ''));
    exit;
}

// ফাইল আইকন জেনারেটর
function getFileIcon($filename, $is_dir = false) {
    if ($is_dir) return '📁';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return '📄';
        case 'zip': case 'rar': case '7z': return '📦';
        case 'jpg': case 'jpeg': case 'png': case 'gif': return '🖼️';
        case 'mp4': case 'mkv': return '🎬';
        case 'mp3': return '🎵';
        default: return '📄';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Secure File Cloud</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">

<?php if (!$is_logged_in): ?>
    <!-- লগইন ইন্টারফেস -->
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md bg-slate-800 p-8 rounded-2xl border border-slate-700 shadow-2xl">
            <h2 class="text-2xl font-bold text-center text-white mb-6">🔐 Cloud Access Control</h2>
            <?php if($login_error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-400 p-3 rounded-xl text-sm mb-4 text-center"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form action="" method="POST" class="space-y-4">
                <input type="text" name="username" required placeholder="User (admin / manager / viewer)" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition">
                <input type="password" name="password" required placeholder="Password" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-xl transition shadow-lg">Authenticate</button>
            </form>
        </div>
    </div>
<?php else: ?>

    <!-- ড্যাশবোর্ড নেভিগেশন বার -->
    <nav class="bg-slate-800 border-b border-slate-700 sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <span class="text-xl">⚡ Cloud Drive</span>
                <span class="text-xs bg-blue-500/20 text-blue-400 px-2.5 py-0.5 rounded-full border border-blue-500/30 uppercase font-semibold"><?php echo $user_role; ?> Mode</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-slate-400 hidden sm:inline">User: <strong class="text-slate-200"><?= ucfirst($_SESSION['user']) ?></strong></span>
                <a href="?action=logout" class="bg-slate-700 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        
        <!-- ব্রেডক্রাম্ব নেভিগেশন (পাথ ট্র্যাকিং) -->
        <div class="mb-6 bg-slate-800 px-4 py-3 rounded-xl border border-slate-700 text-sm flex items-center space-x-2">
            <a href="?" class="text-blue-400 hover:underline">Root</a>
            <?php
            if (!empty($current_sub_dir)) {
                $parts = explode('/', $current_sub_dir);
                $accumulated_path = '';
                foreach ($parts as $part) {
                    $accumulated_path .= ($accumulated_path ? '/' : '') . $part;
                    echo '<span class="text-slate-500">/</span>';
                    echo '<a href="?dir=' . urlencode($accumulated_path) . '" class="text-blue-400 hover:underline">' . htmlspecialchars($part) . '</a>';
                }
            }
            ?>
        </div>

        <?php if (canManage()): ?>
            <!-- ম্যানেজার ও অ্যাডমিন অ্যাকশন কন্ট্রোল প্যানেল -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- ড্র্যাগ অ্যান্ড ড্রপ ফাইল/ফোল্ডার আপলোডার -->
                <div id="drop_zone" class="border-2 border-dashed border-slate-700 hover:border-blue-500 bg-slate-800/40 rounded-2xl p-6 text-center transition cursor-pointer relative group">
                    <!-- ফাইল এবং ডিরেক্টরি আপলোডের হিডেন ইনপুটসমূহ -->
                    <input type="file" id="file_input" multiple class="hidden">
                    <input type="file" id="folder_input" webkitdirectory directory multiple class="hidden">
                    
                    <div class="space-y-2" id="upload_prompt_zone">
                        <span class="text-3xl block group-hover:scale-110 transition duration-200">📤</span>
                        <p class="text-sm font-medium text-slate-300">Drag & Drop Files/Folders here</p>
                        <div class="flex justify-center gap-4 pt-1">
                            <button onclick="document.getElementById('file_input').click()" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition">Browse Files</button>
                            <button onclick="document.getElementById('folder_input').click()" class="text-xs bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded-lg transition">Upload Folder</button>
                        </div>
                    </div>
                    <!-- প্রোগ্রেস বার -->
                    <div id="progress_wrapper" class="hidden mt-4 w-full bg-slate-700 rounded-full h-2">
                        <div id="progress_bar" class="bg-emerald-500 h-2 rounded-full transition-all duration-150" style="width: 0%"></div>
                    </div>
                </div>

                <!-- নতুন ফোল্ডার তৈরি করার প্যানেল -->
                <div class="bg-slate-800/40 border border-slate-700 rounded-2xl p-6 flex flex-col justify-center">
                    <h3 class="text-md font-semibold text-slate-200 mb-3 flex items-center gap-2">📂 Create New Directory</h3>
                    <form action="" method="POST" class="flex gap-2">
                        <input type="text" name="new_folder_name" required placeholder="Folder Name" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-blue-500 text-white">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition whitespace-nowrap">Create Folder</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- ফাইল অ্যান্ড ফোল্ডার লিষ্টিং -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800/70 border-b border-slate-700 text-slate-400 text-xs uppercase tracking-wider">
                            <th class="py-4 px-6">Name</th>
                            <th class="py-4 px-6 w-32">Type</th>
                            <th class="py-4 px-6 w-32">Size</th>
                            <th class="py-4 px-6 w-48 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 text-sm">
                        <?php
                        $items = array_diff(scandir($current_working_dir), array('.', '..'));
                        
                        // ফোল্ডারগুলো আগে এবং ফাইলগুলো পরে দেখানোর সর্টিং মেকানিজম
                        natcasesort($items);
                        $folders_list = [];
                        $files_list = [];
                        foreach ($items as $item) {
                            if (is_dir($current_working_dir . $item)) $folders_list[] = $item;
                            else $files_list[] = $item;
                        }
                        $final_items_list = array_merge($folders_list, $files_list);

                        if (count($final_items_list) > 0) {
                            foreach ($final_items_list as $item) {
                                $full_item_path = $current_working_dir . $item;
                                $is_dir = is_dir($full_item_path);
                                $icon = getFileIcon($item, $is_dir);
                                
                                $item_size = '-';
                                if (!$is_dir) {
                                    $size_bytes = filesize($full_item_path);
                                    $item_size = ($size_bytes > 1048576) ? round($size_bytes/1048576, 2).' MB' : round($size_bytes/1024, 2).' KB';
                                }

                                // ডিরেক্ট লিঙ্ক জেনারেশন মেকানিজম
                                $item_relative_path = ($current_sub_dir ? $current_sub_dir . '/' : '') . $item;
                                $direct_url = $base_url . 'storage/' . implode('/', array_map('rawurlencode', explode('/', $item_relative_path)));
                                ?>
                                <tr class="hover:bg-slate-750 transition">
                                    <td class="py-4 px-6 font-medium text-slate-200">
                                        <?php if ($is_dir): ?>
                                            <a href="?dir=<?= urlencode($item_relative_path) ?>" class="flex items-center space-x-3 text-blue-400 hover:underline">
                                                <span class="text-xl"><?= $icon ?></span>
                                                <span class="truncate"><?= htmlspecialchars($item) ?></span>
                                            </a>
                                        <?php else: ?>
                                            <div class="flex items-center space-x-3">
                                                <span class="text-xl"><?= $icon ?></span>
                                                <span class="truncate text-slate-300"><?= htmlspecialchars($item) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-slate-400 text-xs"><?= $is_dir ? 'Directory' : 'File' ?></td>
                                    <td class="py-4 px-6 text-slate-400 text-xs"><?= $item_size ?></td>
                                    <td class="py-4 px-6 text-right space-x-2 whitespace-nowrap">
                                        <?php if (!$is_dir): ?>
                                            <button onclick="copyDirectLink('<?= $direct_url ?>', this)" class="bg-slate-700 hover:bg-blue-600 text-white font-medium py-1 px-2.5 rounded-lg text-xs transition">Copy Link</button>
                                            <a href="<?= $direct_url ?>" target="_blank" class="bg-slate-700 hover:bg-slate-600 text-white font-medium py-1 px-2.5 rounded-lg text-xs transition">Open</a>
                                        <?php endif; ?>
                                        
                                        <?php if (canDelete()): ?>
                                            <a href="?delete=<?= urlencode($item) ?><?= $current_sub_dir ? '&dir='.urlencode($current_sub_dir) : '' ?>" onclick="return confirm('Are you sure you want to delete this?')" class="bg-slate-700 hover:bg-red-600/30 hover:text-red-400 text-slate-400 font-medium py-1 px-2.5 rounded-lg text-xs transition">Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="4" class="py-12 text-center text-slate-500 text-sm">Directory is empty.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- জাভাস্ক্রিপ্ট কোর লজিক -->
    <script>
    function copyDirectLink(url, btn) {
        navigator.clipboard.writeText(url).then(() => {
            const oldTxt = btn.innerText;
            btn.innerText = '✓ Copied';
            btn.classList.replace('bg-slate-700', 'bg-emerald-600');
            setTimeout(() => { btn.innerText = oldTxt; btn.classList.replace('bg-emerald-600', 'bg-slate-700'); }, 2000);
        });
    }

    const fileInput = document.getElementById('file_input');
    const folderInput = document.getElementById('folder_input');
    const pWrapper = document.getElementById('progress_wrapper');
    const pBar = document.getElementById('progress_bar');

    if(fileInput) fileInput.addEventListener('change', () => uploadItems(fileInput.files));
    if(folderInput) folderInput.addEventListener('change', () => uploadItems(folderInput.files));

    // ড্র্যাগ অ্যান্ড ড্রপ ইভেন্ট হ্যান্ডলার
    const dropZone = document.getElementById('drop_zone');
    if(dropZone) {
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-blue-500'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-blue-500'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-500');
            // ড্রপ ডেটা থেকে ফাইল এবং ফোল্ডার রিড করার জন্য DataTransferItem ব্যবহার করা
            const items = e.dataTransfer.items;
            if (items) {
                let filesList = [];
                // যদি সাধারণ ফাইল ড্রপ করা হয়
                if(e.dataTransfer.files.length > 0 && !items[0].webkitGetAsEntry) {
                    uploadItems(e.dataTransfer.files);
                } else {
                    // ফোল্ডার ড্রপ হ্যান্ডেল করার জন্য ক্রোমিয়াম ভিত্তিক স্ক্যানিং লুপ
                    let files = e.dataTransfer.files;
                    uploadItems(files);
                }
            }
        });
    }

    function uploadItems(files) {
        if(files.length === 0) return;
        pWrapper.classList.remove('hidden');

        let uploadedFiles = 0;
        let totalFiles = files.length;

        Array.from(files).forEach(file => {
            const formData = new FormData();
            formData.append('file_to_upload', file);
            
            // ফোল্ডারের ভেতরের রিলেটিভ পাথ পাস করার জন্য (যদি ফোল্ডার আপলোড হয়)
            if(file.webkitRelativePath) {
                formData.append('relative_path', file.webkitRelativePath);
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    let per = Math.round(((uploadedFiles / totalFiles) * 100) + (e.loaded / e.total) * (100 / totalFiles));
                    pBar.style.width = per + '%';
                }
            });

            xhr.onload = () => {
                uploadedFiles++;
                if(uploadedFiles === totalFiles) {
                    window.location.reload();
                }
            };
            xhr.send(formData);
        });
    }
    </script>
<?php endif; ?>
</body>
</html>