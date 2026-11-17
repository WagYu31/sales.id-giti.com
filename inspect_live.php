<?php
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo "=== SERVER INSPECTION LOEWIX ===\n";
echo "Host: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n";
echo "Script Path: " . __FILE__ . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n";
echo "PHP Version: " . PHP_VERSION . " (" . php_sapi_name() . ")\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n\n";

$target = __DIR__ . '/scraping_exhibitor.php';
echo "Checking target: " . $target . "\n";
if (file_exists($target)) {
    echo "Status: FOUND\n";
    echo "Filesize: " . filesize($target) . " bytes\n";
    echo "Last Modified: " . date('Y-m-d H:i:s', filemtime($target)) . "\n";
    $content = file_get_contents($target);
    echo "Has 'Export Excel Pro': " . (strpos($content, 'Export Excel Pro') !== false ? 'YES' : 'NO') . "\n";
    echo "Has 'Download Excel (CSV)': " . (strpos($content, 'Download Excel (CSV)') !== false ? 'YES' : 'NO') . "\n";
    
    // Check lines 1935-1955
    $lines = explode("\n", $content);
    echo "\nSample lines around line 1940-1950:\n";
    for ($i = 1935; $i <= 1955 && $i < count($lines); $i++) {
        echo ($i + 1) . ": " . trim($lines[$i]) . "\n";
    }
} else {
    echo "Status: NOT FOUND\n";
}
?>