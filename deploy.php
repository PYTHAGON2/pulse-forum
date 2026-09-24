<?php
/**
 * Automated Deployment Script for InfinityFree FTP
 * Usage: php deploy.php
 */

$ftpHost = "ftpupload.net";
$ftpUser = "if0_43000894";
$ftpPass = "lordtj116";
$ftpPort = 21;

$localDir = __DIR__;
$remoteTargetDir = "htdocs";

echo "Connecting to InfinityFree FTP ($ftpHost:$ftpPort)...\n";
$ftp = ftp_connect($ftpHost, $ftpPort, 15);
if (!$ftp) {
    die("Error: Could not connect to FTP host.\n");
}

if (!ftp_login($ftp, $ftpUser, $ftpPass)) {
    die("Error: FTP login failed. Check credentials.\n");
}

echo "Logged in as $ftpUser! Enabling passive mode...\n";
ftp_pasv($ftp, true);

// Files and folders excluded from deployment
$exclude = [
    '.git',
    '.agents',
    'deploy.php',
    'database/pulse_local.sqlite',
    'includes/config.local.php',
    'cookies.txt',
    'cookies_login.txt',
    'cookies_invalid.txt',
    'cookies_thread_test.txt'
];

function uploadRecursive($ftp, $localPath, $remotePath, $exclude) {
    if (is_dir($localPath)) {
        @ftp_mkdir($ftp, $remotePath);
        $files = scandir($localPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $relPath = ltrim(str_replace(__DIR__, '', $localPath . '/' . $file), '/');
            if (in_array($relPath, $exclude) || in_array($file, $exclude)) {
                echo "Skipping excluded: $relPath\n";
                continue;
            }
            
            uploadRecursive($ftp, $localPath . '/' . $file, $remotePath . '/' . $file, $exclude);
        }
    } else {
        echo "Uploading $remotePath ... ";
        if (ftp_put($ftp, $remotePath, $localPath, FTP_BINARY)) {
            echo "OK\n";
        } else {
            echo "FAILED\n";
        }
    }
}

uploadRecursive($ftp, $localDir, $remoteTargetDir, $exclude);
ftp_close($ftp);
echo "\nDeployment finished successfully!\n";
