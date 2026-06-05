<?php
session_start();

/** * --- CONFIGURATION ---
 * Change 'admin' to your desired username and '1234' to your password.
 */
$auth = [
    'user' => 'admin',
    'pass' => '1234' 
];

$storageDir = "media";
if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);

// --- AUTH LOGIC ---
if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }
if (isset($_POST['login'])) {
    if ($_POST['u'] === $auth['user'] && $_POST['p'] === $auth['pass']) {
        $_SESSION['logged_in'] = true;
    } else { $error = "Invalid Credentials"; }
}

// Redirect to login if not authenticated
if (!isset($_SESSION['logged_in'])) {
    renderLogin($error ?? null);
    exit;
}

// --- FILE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $target = $storageDir . '/' . basename($_FILES['file']['name']);
    move_uploaded_file($_FILES['file']['tmp_name'], $target);
    echo json_encode(['status' => 'success']); exit; // For AJAX upload
}

if (isset($_GET['del'])) {
    unlink($storageDir . '/' . basename($_GET['del']));
    header("Location: ?"); exit;
}

if (isset($_GET['dl'])) {
    $path = $storageDir . '/' . basename($_GET['dl']);
    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        readfile($path); exit;
    }
}

$files = array_diff(scandir($storageDir), array('.', '..'));

// --- HELPER FUNCTIONS ---
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

function getIcon($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf' => 'fa-file-pdf text-red-500',
        'jpg', 'png', 'gif', 'webp' => 'fa-file-image text-emerald-400',
        'zip', 'rar', '7z' => 'fa-file-archive text-yellow-500',
        'mp4', 'mov' => 'fa-file-video text-blue-400',
        'mp3', 'wav' => 'fa-file-audio text-pink-400',
        default => 'fa-file text-slate-400'
    };
}

function renderLogin($err) { ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <title>Login | Pro Server</title>
    </head>
    <body class="bg-slate-950 flex items-center justify-center min-h-screen">
        <form method="POST" class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl w-96">
            <div class="text-center mb-8">
                <i class="fas fa-shield-halved text-4xl text-blue-500 mb-4"></i>
                <h2 class="text-white text-2xl font-bold">Secure Access</h2>
            </div>
            <?php if($err): ?><p class="text-red-400 text-sm mb-4 text-center"><?= $err ?></p><?php endif; ?>
            <input type="text" name="u" placeholder="Username" class="w-full bg-slate-800 border-none rounded-lg p-3 text-white mb-4 focus:ring-2 focus:ring-blue-500 outline-none">
            <input type="password" name="p" placeholder="Password" class="w-full bg-slate-800 border-none rounded-lg p-3 text-white mb-6 focus:ring-2 focus:ring-blue-500 outline-none">
            <button name="login" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition">Enter Storage</button>
        </form>
    </body>
    </html>
<?php } ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro Cloud Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); }
        .drop-zone--over { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen font-sans">

    <nav class="sticky top-0 z-50 glass border-b border-slate-800 px-6 py-4 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <div class="bg-blue-600 p-2 rounded-lg"><i class="fas fa-layer-group text-white"></i></div>
            <span class="text-xl font-bold tracking-tight">PRO<span class="text-blue-500">SERVER</span></span>
        </div>
        <div class="flex items-center gap-6">
            <input type="text" id="searchInput" placeholder="Search files..." class="bg-slate-800 border-none rounded-full px-4 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none hidden md:block w-64">
            <a href="?logout=1" class="text-slate-400 hover:text-white transition"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto p-6">
        <div id="dropZone" class="mb-10 border-2 border-dashed border-slate-700 rounded-3xl p-12 text-center transition-all cursor-pointer hover:bg-slate-900/50">
            <input type="file" id="fileInput" class="hidden" multiple>
            <i class="fas fa-cloud-arrow-up text-5xl text-blue-500 mb-4"></i>
            <h3 class="text-xl font-semibold">Drag & Drop Files Here</h3>
            <p class="text-slate-500 mt-2">Or click to browse your computer</p>
            <div id="progressContainer" class="mt-4 hidden">
                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                    <div id="progressBar" class="bg-blue-500 h-full w-0 transition-all"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="fileGrid">
            <?php foreach ($files as $file): 
                $path = "$storageDir/$file";
                $size = formatSize(filesize($path));
                $icon = getIcon($file);
            ?>
            <div class="file-card group bg-slate-900 border border-slate-800 p-4 rounded-2xl hover:border-blue-500 transition-all relative shadow-lg" data-name="<?= strtolower($file) ?>">
                <div class="flex items-start justify-between mb-4">
                    <i class="fas <?= $icon ?> text-3xl"></i>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                        <a href="?dl=<?= urlencode($file) ?>" class="p-2 bg-slate-800 hover:bg-blue-600 rounded-lg transition"><i class="fas fa-download text-xs"></i></a>
                        <a href="?del=<?= urlencode($file) ?>" onclick="return confirm('Delete?')" class="p-2 bg-slate-800 hover:bg-red-600 rounded-lg transition"><i class="fas fa-trash text-xs"></i></a>
                    </div>
                </div>
                <h4 class="font-medium truncate pr-2 text-slate-100" title="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></h4>
                <p class="text-xs text-slate-500 mt-1"><?= $size ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Real-time Search
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.file-card').forEach(card => {
                card.style.display = card.dataset.name.includes(term) ? 'block' : 'none';
            });
        });

        // AJAX Drag & Drop Upload
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.onclick = () => fileInput.click();

        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('drop-zone--over'); };
        dropZone.ondragleave = () => dropZone.classList.remove('drop-zone--over');
        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.classList.remove('drop-zone--over');
            handleUpload(e.dataTransfer.files[0]);
        };
        fileInput.onchange = () => handleUpload(fileInput.files[0]);

        function handleUpload(file) {
            if(!file) return;
            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            
            document.getElementById('progressContainer').classList.remove('hidden');
            xhr.upload.onprogress = (e) => {
                const percent = (e.loaded / e.total) * 100;
                document.getElementById('progressBar').style.width = percent + '%';
            };

            xhr.onload = () => { if(xhr.status === 200) window.location.reload(); };
            xhr.send(formData);
        }
    </script>
</body>
</html>