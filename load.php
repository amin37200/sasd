<?php
header('Content-Type: text/plain');

$session = preg_replace('/[^0-9]/', '', $_GET['session'] ?? '');
$log_file = "logs/{$session}.txt";

if ($session && file_exists($log_file)) {
    echo file_get_contents($log_file);
    unlink($log_file);
} else {
    echo "";
}