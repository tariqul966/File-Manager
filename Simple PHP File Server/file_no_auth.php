<?php
// --- CONFIGURATION ---
$storageDir = "media";
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

// --- FILE ACTIONS ---
// Handle AJAX Uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $target = $storageDir . '/' . basename($_FILES['file']['name']);
    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit; 
}

// Handle Deletion
if (isset($_GET['del'])) {
    $fileToDelete = $storageDir . '/' . basename($_GET['del']);
    if (file_exists($fileToDelete)) {
        unlink($fileToDelete);
    }
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
    exit;
}

// Handle Downloads
if (isset($_GET['dl'])) {
    $path = $storageDir . '/' . basename($_GET['dl']);
    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }
}

// Get current files
$files = array_diff(scandir($storageDir), array('.', '..'));

// --- HELPER FUNCTIONS ---
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

function getIcon($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf' => 'fa-file-pdf text-red-500',
        'jpg', 'png', 'gif', 'webp', 'jpeg' => 'fa-file-image text-emerald-400',
        'zip', 'rar', '7z', 'tar' => 'fa-file-archive text-yellow-500',
        'mp4', 'mov', 'avi' => 'fa-file-video text-blue-400',
        'mp3', 'wav', 'ogg' => 'fa-file-audio text-pink-400',
        'txt', 'md' => 'fa-file-lines text-slate-300',
        default => 'fa-file text-slate-400'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Cloud Storage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); }
        .drop-zone--over { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); transform: scale(1.01); }
        .file-card:hover { transform: translateY(-4px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen font-sans">

    <nav class="sticky top-0 z-50 glass border-b border-slate-800 px-6 py-4 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <div class="bg-indigo-600 p-2 rounded-lg"><i class="fas fa-folder-open text-white"></i></div>
            <span class="text-xl font-bold tracking-tight uppercase">Public<span class="text-indigo-500">Drive</span></span>
        </div>
        <div class="flex items-center gap-4">
            <input type="text" id="searchInput" placeholder="Search storage..." class="bg-slate-800 border-none rounded-full px-4 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none w-48 md:w-64">
        </div>
    </nav>

    <main class="max-w-6xl mx-auto p-6">
        <div id="dropZone" class="mb-10 border-2 border-dashed border-slate-700 rounded-3xl p-12 text-center transition-all cursor-pointer hover:border-indigo-500">
            <input type="file" id="fileInput" class="hidden" multiple>
            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-900 rounded-full mb-4">
                <i class="fas fa-arrow-up-from-bracket text-2xl text-indigo-400"></i>
            </div>
            <h3 class="text-xl font-semibold">Drop files to upload</h3>
            <p class="text-slate-500 mt-2">Instant sharing enabled</p>
            
            <div id="progressContainer" class="mt-6 hidden max-w-md mx-auto">
                <div class="flex justify-between text-xs mb-1">
                    <span id="progressText">Uploading...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div id="progressBar" class="bg-indigo-500 h-full w-0 transition-all duration-300"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="fileGrid">
            <?php foreach ($files as $file): 
                $path = "$storageDir/$file";
                $size = formatSize(filesize($path));
                $icon = getIcon($file);
            ?>
            <div class="file-card group bg-slate-900 border border-slate-800 p-4 rounded-2xl hover:border-indigo-500 transition-all relative shadow-lg" data-name="<?= strtolower($file) ?>">
                <div class="flex items-start justify-between mb-4">
                    <i class="fas <?= $icon ?> text-3xl"></i>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                        <a href="?dl=<?= urlencode($file) ?>" class="p-2 bg-slate-800 hover:bg-indigo-600 rounded-lg text-white transition shadow-xl" title="Download">
                            <i class="fas fa-download text-xs"></i>
                        </a>
                        <a href="?del=<?= urlencode($file) ?>" onclick="return confirm('Delete this file permanently?')" class="p-2 bg-slate-800 hover:bg-red-600 rounded-lg text-white transition shadow-xl" title="Delete">
                            <i class="fas fa-trash text-xs"></i>
                        </a>
                    </div>
                </div>
                <h4 class="font-medium truncate pr-2 text-slate-100" title="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></h4>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-xs text-slate-500"><?= $size ?></span>
                    <span class="text-[10px] uppercase tracking-widest text-slate-600 font-bold"><?= pathinfo($file, PATHINFO_EXTENSION) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($files)): ?>
            <div class="col-span-full py-20 text-center border border-slate-800 rounded-3xl">
                <p class="text-slate-500">The storage is empty. Upload your first file above.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Real-time Search Logic
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.file-card').forEach(card => {
                const isMatch = card.dataset.name.includes(term);
                card.style.display = isMatch ? 'block' : 'none';
            });
        });

        // AJAX File Upload Logic
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.onclick = () => fileInput.click();

        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('drop-zone--over'); };
        dropZone.ondragleave = () => dropZone.classList.remove('drop-zone--over');
        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.classList.remove('drop-zone--over');
            if(e.dataTransfer.files.length) handleUpload(e.dataTransfer.files[0]);
        };
        fileInput.onchange = () => {
            if(fileInput.files.length) handleUpload(fileInput.files[0]);
        };

        function handleUpload(file) {
            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            
            document.getElementById('progressContainer').classList.remove('hidden');
            
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    document.getElementById('progressBar').style.width = percent + '%';
                    document.getElementById('progressPercent').innerText = percent + '%';
                }
            };

            xhr.onload = () => {
                if(xhr.status === 200) {
                    window.location.reload();
                } else {
                    alert("Upload failed. Check server permissions.");
                }
            };
            xhr.send(formData);
        }
    </script>
</body>
</html>