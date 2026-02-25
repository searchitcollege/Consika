<?php
// debug_dashboard.php - Run this to find the error
error_reporting(E_ALL);
ini_set('display_errors', 1);

$lines = file('dashboard.php');
$total_lines = count($lines);
echo "Total lines in file: $total_lines<br>";

if ($total_lines >= 737) {
    echo "<h3>Line 737 content:</h3>";
    echo "<pre>" . htmlspecialchars($lines[736]) . "</pre>";
    
    echo "<h3>Lines around 737:</h3>";
    for ($i = 730; $i <= 740; $i++) {
        if (isset($lines[$i-1])) {
            $line_num = $i;
            $content = $lines[$i-1];
            $highlight = ($i == 737) ? 'style="background-color: #ffeeee; font-weight: bold;"' : '';
            echo "<div $highlight>Line $line_num: " . htmlspecialchars($content) . "</div>";
        }
    }
} else {
    echo "File has fewer than 737 lines. Actual lines: $total_lines";
}
?>