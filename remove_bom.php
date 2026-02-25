<?php
// BOM Remover - Save this as remove_bom.php in your root directory
function removeBOM($filename) {
    $contents = file_get_contents($filename);
    $bom = pack('H*','EFBBBF');
    $contents = preg_replace("/^$bom/", '', $contents);
    file_put_contents($filename, $contents);
    echo "BOM removed from: $filename<br>";
}

// Remove BOM from dashboard.php
$file = __DIR__ . '/admin/dashboard.php';
if (file_exists($file)) {
    removeBOM($file);
    echo "Fixed! <a href='admin/dashboard.php'>Click here to test</a>";
} else {
    echo "File not found: $file";
}
?>