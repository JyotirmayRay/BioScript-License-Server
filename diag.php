<?php
header('Content-Type: text/plain');

echo "--- BioScript Path Diagnostic ---\n\n";

$base_dir = __DIR__;
echo "Current directory: $base_dir\n";

$paths_to_check = [
    '/../../libs/PHPMailer/src/PHPMailer.php',
    '/../libs/PHPMailer/src/PHPMailer.php',
    '/libs/PHPMailer/src/PHPMailer.php',
];

foreach ($paths_to_check as $path) {
    $full_path = $base_dir . $path;
    echo "Checking: $full_path\n";
    if (file_exists($full_path)) {
        echo ">> FOUND at " . realpath($full_path) . "\n";
    }
    else {
        echo ">> NOT FOUND\n";
    }
    echo "\n";
}

echo "--- Directory Map ---\n";
function list_dirs($dir, $depth = 0)
{
    if ($depth > 2)
        return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..')
            continue;
        if (is_dir($dir . '/' . $item)) {
            echo str_repeat('  ', $depth) . "[DIR] $item\n";
            list_dirs($dir . '/' . $item, $depth + 1);
        }
    }
}

echo "Contents of " . realpath($base_dir . '/..') . ":\n";
list_dirs(realpath($base_dir . '/..'));