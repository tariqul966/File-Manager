<?php
// --- CONFIGURATION ---
$storageDir = "media";
if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

// --- LOGIC: HANDLE UPLOAD ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $targetFile = $storageDir . '/' . basename($_FILES['fileToUpload']['name']);
    if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
        $message = "File uploaded successfully!";
    } else {
        $message = "Error uploading file.";
    }
}

// --- LOGIC: HANDLE DOWNLOAD ---
if (isset($_GET['dl'])) {
    $file = basename($_GET['dl']);
    $path = "$storageDir/$file";
    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($path);
        exit;
    }
}

// --- LOGIC: READ FILES ---
$files = array_diff(scandir($storageDir), array('.', '..'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CloudShelf | Private File Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-4 md:p-10">

    <div class="max-w-4xl mx-auto">
        <header class="flex justify-between items-center mb-10">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent">
                CloudShelf
            </h1>
            <span class="text-slate-400 text-sm italic"><?php echo count($files); ?> Files stored</span>
        </header>

        <section class="bg-slate-800 border-2 border-dashed border-slate-700 rounded-xl p-8 mb-10 text-center hover:border-blue-500 transition">
            <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                <i class="fas fa-cloud-upload-alt text-4xl text-blue-500 mb-4"></i>
                <h2 class="text-xl mb-2">Upload new files</h2>
                <input type="file" name="fileToUpload" id="fileInput" class="hidden" onchange="document.getElementById('uploadForm').submit()">
                <button type="button" onclick="document.getElementById('fileInput').click()" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-medium transition">
                    Select File
                </button>
            </form>
            <?php if($message): ?>
                <p class="mt-4 text-emerald-400 font-medium"><?= $message ?></p>
            <?php endif; ?>
        </section>

        <section class="bg-slate-800 rounded-xl overflow-hidden shadow-2xl">
            <table class="w-full text-left">
                <thead class="bg-slate-700/50 text-slate-400 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4">Size</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    <?php foreach ($files as $file): 
                        $size = filesize("$storageDir/$file");
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $icon = "fa-file";
                        if(in_array($ext, ['jpg','png','gif'])) $icon = "fa-file-image text-purple-400";
                        if(in_array($ext, ['pdf'])) $icon = "fa-file-pdf text-red-400";
                        if(in_array($ext, ['zip','rar'])) $icon = "fa-file-archive text-yellow-400";
                    ?>
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4 flex items-center gap-3">
                            <i class="fas <?= $icon ?> text-lg"></i>
                            <span class="font-medium"><?= htmlspecialchars($file) ?></span>
                        </td>
                        <td class="px-6 py-4 text-slate-400 text-sm">
                            <?= round($size / 1024, 1) ?> KB
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="?dl=<?= urlencode($file) ?>" class="text-blue-400 hover:text-blue-300 font-bold">
                                <i class="fas fa-download"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($files)): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-slate-500 italic">No files found. Drag something up there!</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

</body>
</html>