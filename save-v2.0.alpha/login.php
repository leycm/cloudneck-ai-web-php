<?php
session_start();
require_once('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $users = json_decode(file_get_contents('assets/user.json'), true);
    
    foreach ($users as $user_id => $user) {
        if ($user['name'] === $username && $user['code'] === $password) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['perm'] = $user['perm'];
            
            // Erstelle Benutzerverzeichnis falls nicht vorhanden
            $user_chat_dir = "assets/chats/$user_id";
            if (!is_dir($user_chat_dir)) {
                mkdir($user_chat_dir, 0777, true);
                
                // Erstelle ersten Chat für neue Benutzer
                $chat_id = uniqid();
                $chat = [
                    "info" => [
                        "name" => "Willkommen!",
                        "lastuse" => date("H:i/d.m.Y"),
                        "create" => date("H:i/d.m.Y"),
                        "model" => "cloudneck-g1"
                    ],
                    "chat" => [
                        "1" => [
                            "sender" => "ai",
                            "time" => date("H:i/d.m.Y"),
                            "message" => "Willkommen bei CloudNeck! Wie kann ich dir helfen?",
                            "respons_time" => 0
                        ]
                    ]
                ];
                file_put_contents("$user_chat_dir/$chat_id.json", 
                    json_encode($chat, JSON_PRETTY_PRINT));
            }
            
            header('Location: cloudneck/chat.php');
            exit();
        }
    }
    $error = "Falscher Benutzername oder Passwort";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CloudNeck - Login</title>
    <style>
        .login-container {
            max-width: 300px;
            margin: 100px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input, button {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>CloudNeck Login</h2>
        <?php if (isset($error)) echo "<p style='color: red'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Benutzername" required><br>
            <input type="password" name="password" placeholder="Passwort" required><br>
            <button type="submit">Einloggen</button>
        </form>
    </div>
</body>
</html>
