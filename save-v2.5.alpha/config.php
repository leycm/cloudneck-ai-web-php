<?php
// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Fehlermeldungen aktivieren (in Produktion deaktivieren)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Deepseek R1 Server Konfiguration
define('AI_SERVER_URL', 'http://localhost:8000/v1/chat/completions');

// Verzeichnisse erstellen, falls sie nicht existieren
$directories = [
    'assets/chats',
    'assets/modles'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
} 