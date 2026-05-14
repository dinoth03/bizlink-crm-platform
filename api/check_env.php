<?php
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OpenSSL extension: " . (extension_loaded('openssl') ? "Loaded" : "NOT LOADED") . "\n";

$host = 'smtp.gmail.com';
$port = 465;
$timeout = 10;

echo "Testing connection to $host:$port...\n";
$start = microtime(true);
$errno = 0;
$errstr = '';
$fp = @fsockopen("ssl://$host", $port, $errno, $errstr, $timeout);
$duration = microtime(true) - $start;

if ($fp) {
    echo "SUCCESS: Connected to $host:$port in " . round($duration * 1000, 2) . "ms\n";
    $banner = fgets($fp, 512);
    echo "Banner: " . trim($banner) . "\n";
    fclose($fp);
} else {
    echo "FAILED: Could not connect to $host:$port\n";
    echo "Error No: $errno\n";
    echo "Error Str: $errstr\n";
}

echo "\nTesting connection to $host:587 (TLS)...\n";
$port = 587;
$fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
if ($fp) {
    echo "SUCCESS: Connected to $host:$port\n";
    $banner = fgets($fp, 512);
    echo "Banner: " . trim($banner) . "\n";
    fclose($fp);
} else {
    echo "FAILED: Could not connect to $host:$port\n";
    echo "Error No: $errno\n";
    echo "Error Str: $errstr\n";
}
?>
