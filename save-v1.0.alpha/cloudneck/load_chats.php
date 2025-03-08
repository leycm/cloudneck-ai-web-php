<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$user_id = $_SESSION['user_id'];
$path = "assets/chats/$user_id";
$chats = [];

if (is_dir($path)) {
    foreach (glob("$path/*.json") as $file) {
        $chat_data = json_decode(file_get_contents($file), true);
        $chat_data['id'] = basename($file, '.json'); // Füge die ID hinzu
        $chats[] = $chat_data;
    }
}

header('Content-Type: application/json');
echo json_encode($chats);
?> 