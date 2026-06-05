<?php
// Define the directory to browse (relative to this script)
$dir = "./media";

// Create the directory if it doesn't exist
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Handle File Downloads
if (isset($_GET['download'])) {
    $file = basename($_GET['download']); // basename() prevents directory traversal attacks
    $path = $dir . '/' . $file;

    if (file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($path);
        exit;
    }
}

// Get file list
$files = array_diff(scandir($dir), array('.', '..'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple PHP File Server</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; padding: 50px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        .btn { text-decoration: none; background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>📂 File Explorer</h2>
    <table>
        <thead>
            <tr>
                <th>File Name</th>
                <th>Size</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): 
                $filesize = filesize($dir . '/' . $file);
                $formattedSize = round($filesize / 1024, 2) . ' KB';
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($file); ?></td>
                    <td><?php echo $formattedSize; ?></td>
                    <td><a href="?download=<?php echo urlencode($file); ?>" class="btn">Download</a></td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (empty($files)): ?>
                <tr><td colspan="3" style="text-align:center;">No files found in the directory.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>