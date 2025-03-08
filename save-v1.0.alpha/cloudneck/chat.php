<?php
session_start();
require_once('../config.php');

// Überprüfe Login-Status
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$users = json_decode(file_get_contents('../assets/user.json'), true);
$current_user = $users[$user_id];

// Lade verfügbare Modelle
function loadModels() {
    $models = [];
    foreach (glob("../assets/modles/*.json") as $file) {
        $model_data = json_decode(file_get_contents($file), true);
        $models[basename($file, '.json')] = $model_data;
    }
    return $models;
}

// Lade Benutzer-Chats
function loadUserChats($user_id) {
    $chats = [];
    $path = "../assets/chats/$user_id";
    if (is_dir($path)) {
        foreach (glob("$path/*.json") as $file) {
            $chat_data = json_decode(file_get_contents($file), true);
            $chat_id = basename($file, '.json');
            $chats[] = [
                'id' => $chat_id,
                'info' => $chat_data['info']
            ];
        }
    }
    return array_reverse($chats); // Neueste zuerst
}

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_chats':
            echo json_encode(loadUserChats($user_id));
            break;
            
        case 'create_chat':
            if ($current_user['credits'] <= 0 && $current_user['credits'] != -1) {
                echo json_encode(['error' => 'Keine Credits verfügbar']);
                exit;
            }
            
            $chat_id = uniqid();
            $model = $_POST['model'];
            $chat = [
                "info" => [
                    "name" => "Neuer Chat",
                    "lastuse" => date("H:i/d.m.Y"),
                    "create" => date("H:i/d.m.Y"),
                    "model" => $model
                ],
                "chat" => []
            ];
            
            $path = "../assets/chats/$user_id";
            file_put_contents("$path/$chat_id.json", json_encode($chat, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'chat_id' => $chat_id]);
            break;

        case 'load_chat':
            $chat_id = $_POST['chat_id'];
            $chat_file = "../assets/chats/$user_id/$chat_id.json";
            if (file_exists($chat_file)) {
                echo file_get_contents($chat_file);
            } else {
                echo json_encode(['error' => 'Chat nicht gefunden']);
            }
            break;

        case 'send_message':
            $chat_id = $_POST['chat_id'];
            $message = $_POST['message'];
            $chat_file = "../assets/chats/$user_id/$chat_id.json";
            
            if (!file_exists($chat_file)) {
                echo json_encode(['error' => 'Chat nicht gefunden']);
                exit;
            }

            if ($current_user['credits'] <= 0 && $current_user['credits'] != -1) {
                echo json_encode(['error' => 'Keine Credits verfügbar']);
                exit;
            }

            $chat = json_decode(file_get_contents($chat_file), true);
            $model_data = loadModels()[$chat['info']['model']];

            // Nachricht formatieren
            $ai_message = str_replace(
                ['%header%', '%message%', '%footer%'],
                [$model_data['header'], $message, $model_data['footer']],
                $model_data['ai_format:']
            );

            // Nachricht an AI-Server senden
            $start_time = microtime(true);
            $ch = curl_init(AI_SERVER_URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'user', 'content' => $ai_message]
                ]
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $response_time = round((microtime(true) - $start_time) * 1000);
            curl_close($ch);

            $response_data = json_decode($response, true);
            $ai_response = $response_data['choices'][0]['message']['content'] ?? 'Fehler bei der AI-Antwort';

            // Chat aktualisieren
            $msg_count = count($chat['chat']) + 1;
            $chat['chat'][$msg_count] = [
                "sender" => "user",
                "time" => date("H:i/d.m.Y"),
                "message" => $message
            ];
            
            $msg_count++;
            $chat['chat'][$msg_count] = [
                "sender" => "ai",
                "time" => date("H:i/d.m.Y"),
                "message" => $ai_response,
                "respons_time" => $response_time
            ];

            $chat['info']['lastuse'] = date("H:i/d.m.Y");
            $chat['info']['name'] = substr($message, 0, 20) . "...";

            file_put_contents($chat_file, json_encode($chat, JSON_PRETTY_PRINT));

            // Credits aktualisieren
            if ($current_user['credits'] > 0) {
                $users[$user_id]['credits']--;
                file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
            }

            echo json_encode(['success' => true, 'chat' => $chat]);
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CloudNeck Chat</title>
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        .container { display: flex; height: 100vh; gap: 20px; }
        .sidebar { width: 250px; border-right: 1px solid #ccc; padding-right: 20px; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; }
        .chat-messages { flex-grow: 1; overflow-y: auto; padding: 20px; border: 1px solid #ccc; margin-bottom: 20px; }
        .message { margin: 10px 0; padding: 10px; border-radius: 5px; max-width: 80%; }
        .user-message { background: #e3f2fd; margin-left: auto; }
        .ai-message { background: #f5f5f5; margin-right: auto; }
        .chat-item { padding: 10px; margin: 5px 0; cursor: pointer; border: 1px solid #ddd; }
        .chat-item:hover { background: #f0f0f0; }
        .chat-item.active { background: #e3f2fd; }
        textarea { width: 100%; height: 100px; margin-bottom: 10px; padding: 10px; }
        .credits { font-weight: bold; margin: 10px 0; }
        .nav-links { margin-top: 20px; }
        .nav-links a { display: block; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>CloudNeck Chat</h2>
            <p class="credits">Credits: <?= $current_user['credits'] == -1 ? "Unbegrenzt" : $current_user['credits'] ?></p>
            <div>
                <h3>Neuer Chat</h3>
                <select id="model-select">
                    <?php foreach (loadModels() as $id => $model): ?>
                        <option value="<?= $id ?>"><?= $model['name'] ?> (<?= $model['version'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button onclick="createNewChat()">Erstellen</button>
            </div>
            <div id="chat-list">
                <!-- Chats werden hier dynamisch geladen -->
            </div>
            <div class="nav-links">
                <a href="../logout.php">Logout</a>
                <?php if($_SESSION['perm'] >= 4): ?>
                    <a href="admin.php">Admin Panel</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="main-content">
            <div id="chat-messages" class="chat-messages">
                <!-- Nachrichten werden hier angezeigt -->
            </div>
            <div class="input-area">
                <textarea id="message-input" placeholder="Nachricht eingeben..."></textarea>
                <button onclick="sendMessage()">Senden</button>
            </div>
        </div>
    </div>

    <script>
    let currentChatId = null;

    function loadChats() {
        fetch('chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_chats'
        })
        .then(response => response.json())
        .then(chats => {
            const chatList = document.getElementById('chat-list');
            chatList.innerHTML = '';
            
            chats.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.className = 'chat-item';
                if (chat.id === currentChatId) chatItem.classList.add('active');
                chatItem.textContent = chat.info.name;
                chatItem.onclick = () => loadChat(chat.id);
                chatList.appendChild(chatItem);
            });
        });
    }

    function createNewChat() {
        const model = document.getElementById('model-select').value;
        fetch('chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=create_chat&model=${model}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                loadChat(data.chat_id);
                loadChats();
            }
        });
    }

    function loadChat(chatId) {
        currentChatId = chatId;
        fetch('chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=load_chat&chat_id=${chatId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                displayChat(data);
                loadChats(); // Aktualisiere Chat-Liste für aktiven Status
            }
        });
    }

    function displayChat(chatData) {
        const messagesDiv = document.getElementById('chat-messages');
        messagesDiv.innerHTML = '';
        
        Object.values(chatData.chat).forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${msg.sender}-message`;
            messageDiv.textContent = msg.message;
            messagesDiv.appendChild(messageDiv);
        });
        
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function sendMessage() {
        if (!currentChatId) {
            alert('Bitte wählen Sie zuerst einen Chat aus oder erstellen Sie einen neuen Chat');
            return;
        }

        const input = document.getElementById('message-input');
        const message = input.value.trim();
        
        if (!message) return;

        input.disabled = true;
        
        fetch('chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=send_message&chat_id=${currentChatId}&message=${encodeURIComponent(message)}`
        })
        .then(response => response.json())
        .then(data => {
            input.disabled = false;
            if (data.error) {
                alert(data.error);
            } else {
                input.value = '';
                displayChat(data.chat);
                loadChats(); // Aktualisiere Chat-Liste für neue Namen
            }
        })
        .catch(error => {
            input.disabled = false;
            alert('Fehler beim Senden der Nachricht');
        });
    }

    // Event-Listener für Enter-Taste
    document.getElementById('message-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Initial Chats laden
    loadChats();
    </script>
</body>
</html> 