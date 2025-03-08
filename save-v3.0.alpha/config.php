<?php
date_default_timezone_set('Europe/Berlin');

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('AI_SERVER_URL', 'http://localhost:8000/v1/chat/completions');

$directories = [
    'assets/chats',
    'assets/models'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
} 