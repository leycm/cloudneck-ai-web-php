<?php
session_start();
require_once('../config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$users = json_decode(file_get_contents('../assets/user.json'), true);
$current_user = $users[$user_id];

function loadModels() {
    $models = [];
    foreach (glob("../assets/modles/*.json") as $file) {
        $model_data = json_decode(file_get_contents($file), true);
        $models[basename($file, '.json')] = $model_data;
    }
    return $models;
}



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
    return array_reverse($chats);
}

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_chats':
            echo json_encode(loadUserChats($user_id));
            break;

        case 'remove_chat':
            $chatId = $_POST['chat_id'];
            $chat_file = "../assets/chats/$user_id/$chatId.json";
            
            $deleted = false;
            if (file_exists($chat_file)) {
                $deleted = unlink($chat_file);
            }
    
            if ($deleted) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Chat konnte nicht gelöscht werden.']);
            }
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

            // Aktuelle Zeit für beide Nachrichten
            $current_time = date("H:i/d.m.Y");

            // Simulierte AI-Antwort
            $ai_response = "[AI noch nicht verfügbar] Ich verstehe deine Nachricht \"$message\". " . 
                          "Leider ist der AI-Server aktuell nicht erreichbar. " .
                          "Dies ist eine temporäre Antwort von CloudNeck.";
            $response_time = 100;

            // Stelle sicher, dass das chat-Array existiert
            if (!isset($chat['chat'])) {
                $chat['chat'] = [];
            }

            // Neue Nachrichtennummer bestimmen
            $msg_count = count($chat['chat']) + 1;

            // Benutzernachricht hinzufügen
            $chat['chat'][$msg_count] = [
                "sender" => "user",
                "time" => $current_time,
                "message" => $message
            ];
            
            $msg_count++;
            $chat['chat'][$msg_count] = [
                "sender" => "cloudneck",
                "time" => $current_time,
                "message" => $ai_response,
                "respons_time" => $response_time
            ];

            $chat['info']['lastuse'] = $current_time;
            $chat['info']['name'] = substr($message, 0, 20) . "...";

            // Chat speichern
            if (!file_put_contents($chat_file, json_encode($chat, JSON_PRETTY_PRINT))) {
                echo json_encode(['error' => 'Fehler beim Speichern des Chats']);
                exit;
            }

            // Credits aktualisieren
            if ($current_user['credits'] > 0) {
                $users[$user_id]['credits']--;
                file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
            }

            echo json_encode(['success' => true, 'chat' => $chat]);
            break;

        case 'change_chat_name':
            $chat_id = $_POST['chat_id'];
            $new_name = $_POST['new_name'];
            $chat_file = "../assets/chats/$user_id/$chat_id.json";
            
            if (file_exists($chat_file)) {
                $chat = json_decode(file_get_contents($chat_file), true);
                $chat['info']['name'] = $new_name;
                file_put_contents($chat_file, json_encode($chat, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Chat nicht gefunden']);
            }
            break;

        case 'change_chat_model':
            $chat_id = $_POST['chat_id'];
            $new_model = $_POST['new_model'];
            $chat_file = "../assets/chats/$user_id/$chat_id.json";
            
            if (file_exists($chat_file)) {
                $chat = json_decode(file_get_contents($chat_file), true);
                $chat['info']['model'] = $new_model;
                file_put_contents($chat_file, json_encode($chat, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Chat nicht gefunden']);
            }
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CloudNeck Chat</title>
    <link rel="stylesheet" href="../css/message.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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

                // Menü für Optionen
                const optionsMenu = document.createElement('div');
                optionsMenu.className = 'options-menu';
                optionsMenu.innerHTML = '•••';

                optionsMenu.addEventListener('click', (e) => {
                    e.stopPropagation();
                    showOptionsMenu(chat.id, e.clientX, e.clientY);
                });

                chatItem.appendChild(optionsMenu);
                chatItem.onclick = () => {
                    loadChat(chat.id);
                    window.history.pushState({}, '', `?chat=${chat.id}`);
                };
                chatList.appendChild(chatItem);
            });

            if (!currentChatId && chats.length > 0) {
                const urlParams = new URLSearchParams(window.location.search);
                const chatId = urlParams.get('chat');
                
                if (chatId && chats.some(chat => chat.id === chatId)) {
                    loadChat(chatId);
                } else {
                    loadChat(chats[0].id);
                    window.history.pushState({}, '', `?chat=${chats[0].id}`);
                }
            }
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
                loadChats();
            }
        });
    }


    function displayChat(chatData) {
        const messagesDiv = document.getElementById('chat-messages');
        messagesDiv.innerHTML = '';
        
        if (chatData.chat) {
            Object.values(chatData.chat).forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${msg.sender}-message`;
                
                
                
                const textDiv = document.createElement('div');
                textDiv.className = 'message-content';
                textDiv.innerHTML = marked.parse(msg.message);
                messageDiv.appendChild(textDiv);
                
                const timeSpan = document.createElement('span');
                timeSpan.className = 'message-time';
                timeSpan.textContent = msg.time;
                messageDiv.appendChild(timeSpan);
                
                if (msg.respons_time) {
                    const responseTime = document.createElement('span');
                    responseTime.className = 'response-time';
                    responseTime.textContent = `Antwortzeit: ${msg.respons_time}ms`;
                    messageDiv.appendChild(responseTime);
                }
                
                messagesDiv.appendChild(messageDiv);
            });
        }
        
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
        
        const messagesDiv = document.getElementById('chat-messages');
        const userMessageDiv = document.createElement('div');
        userMessageDiv.className = 'message user-message';
        
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        const now = new Date();
        timeSpan.textContent = now.toLocaleTimeString() + '/' + 
                             now.toLocaleDateString('de-DE');
        userMessageDiv.appendChild(timeSpan);
        
        const textDiv = document.createElement('div');
        textDiv.textContent = message;
        userMessageDiv.appendChild(textDiv);
        
        messagesDiv.appendChild(userMessageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
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
                loadChats();
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            input.disabled = false;
            alert('Fehler beim Senden der Nachricht');
        });
    }

    function showOptionsMenu(chatId, x, y) {
        const menu = document.createElement('div');
        menu.className = 'options-dropdown';
        menu.style.position = 'absolute';
        menu.style.left = `${x}px`;
        menu.style.top = `${y}px`;
        menu.innerHTML = `
            <div onclick="changeChatName('${chatId}')">Name ändern</div>
            <div onclick="changeChatModel('${chatId}')">Modell ändern</div>
            <div onclick="removeChat('${chatId}')">Chat löschen</div>
        `;
        document.body.appendChild(menu);

        document.addEventListener('click', () => {
            menu.remove();
        }, { once: true });
    }

    function removeChat(chatId) {
        if (confirm('Möchten Sie diesen Chat wirklich löschen?')) {
            fetch('chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=remove_chat&chat_id=${chatId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    if (currentChatId === chatId) {
                        currentChatId = null;
                        document.getElementById('chat-messages').innerHTML = '';
                    }
                    loadChats();
                }
            });
        }
    }

    function changeChatName(chatId) {
        const newName = prompt("Geben Sie den neuen Namen für den Chat ein:");
        if (newName) {
            fetch('chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=change_chat_name&chat_id=${chatId}&new_name=${encodeURIComponent(newName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    loadChats();
                }
            });
        }
    }

    function changeChatModel(chatId) {
        const newModel = prompt("Geben Sie das neue Modell für den Chat ein:");
        if (newModel) {
            fetch('chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=change_chat_model&chat_id=${chatId}&new_model=${encodeURIComponent(newModel)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    loadChats();
                }
            });
        }
    }

    document.getElementById('message-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const chatId = urlParams.get('chat');
        if (chatId) {
            currentChatId = chatId;
            loadChat(chatId);
        }
        loadChats();
    });
    </script>
</body>
</html>