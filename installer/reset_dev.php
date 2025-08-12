<?php
// Reset Installation Script (FOR DEVELOPMENT ONLY)
// This script will delete the database and reset the installation

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

echo "=== SpotiFLAC Installation Reset ===\n\n";

// Confirm action
echo "This will DELETE the database and reset the installation.\n";
echo "Are you sure you want to continue? (type 'yes' to confirm): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) !== 'yes') {
    echo "Reset cancelled.\n";
    exit;
}

// Define paths
define('ROOT_DIR', __DIR__);
define('DATA_DIR', ROOT_DIR . '/data');
define('DOWNLOADS_DIR', ROOT_DIR . '/downloads');

// Delete database file
$dbFile = DATA_DIR . '/app.sqlite';
if (file_exists($dbFile)) {
    if (unlink($dbFile)) {
        echo "Database file deleted successfully.\n";
    } else {
        echo "Error: Could not delete database file.\n";
    }
} else {
    echo "Database file not found.\n";
}

// Clear downloads directory
if (is_dir(DOWNLOADS_DIR)) {
    $files = glob(DOWNLOADS_DIR . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "Downloads directory cleared.\n";
} else {
    echo "Downloads directory not found.\n";
}

echo "\nInstallation reset complete!\n";
echo "You can now run the installer again.\n";
?>