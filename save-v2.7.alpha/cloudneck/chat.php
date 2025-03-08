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
            $first_message = isset($_POST['first_message']) ? $_POST['first_message'] : null;
            
            $chat = [
                "info" => [
                    "name" => $first_message ? substr($first_message, 0, 30) . "..." : "Neuer Chat",
                    "lastuse" => date("H:i/d.m.Y"),
                    "create" => date("H:i/d.m.Y"),
                    "model" => "default"
                ],
                "chat" => []
            ];
            
            // Wenn eine erste Nachricht vorhanden ist, füge sie hinzu
            if ($first_message) {
                $current_time = date("H:i d.m.Y");
                $chat['chat'][1] = [
                    "sender" => "user",
                    "time" => $current_time,
                    "message" => $first_message
                ];
                
                // Simulierte AI-Antwort
                $chat['chat'][2] = [
                    "sender" => "cloudneck",
                    "time" => $current_time,
                    "message" => "[AI noch nicht verfügbar] Ich verstehe deine Nachricht.\nDies ist eine temporäre Antwort von CloudNeck.",
                    "respons_time" => 100
                ];
            }
            
            $path = "../assets/chats/$user_id";
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            
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

            $current_time = date("H:i d.m.Y");

            // Simulierte AI-Antwort
            $ai_response = "[AI noch nicht verfügbar] Ich verstehe deine Nachricht.\n" . 
                          "Leider ist der AI-Server aktuell nicht erreichbar.\n" .
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

        case 'get_models':
            $models = [];
            foreach (glob("../assets/models/*.json") as $file) {
                $model_data = json_decode(file_get_contents($file), true);
                $models[basename($file, '.json')] = $model_data;
            }
            echo json_encode($models);
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
            <button onclick="createNewChat()" class="new-chat-btn">
                <img src="../assets/icons/plus.png" alt="+">
                Neuer Chat
            </button>
            <span>Deine Chats</span>
            <div id="chat-list">
            </div>
            <div class="user-info-bar">
                <div class="perm-circle perm-<?= $_SESSION['perm'] ?>" title="Berechtigung: <?= $_SESSION['perm'] ?>"></div>
                <span class="username"><?= $current_user['name'] ?></span>
                <div class="status-icons">
                    <div class="credit-status" title="Credits">
                        <img src="../assets/icons/coin.png" alt="Credits">
                        <span><?= $current_user['credits'] == -1 ? "∞" : $current_user['credits'] ?></span>
                    </div>
                    <?php if($_SESSION['perm'] >= 2): ?>
                        <a href="admin.php" class="status-icon" title="Admin Panel">
                            <img src="../assets/icons/wrench.png" alt="Admin">
                        </a>
                    <?php endif; ?>
                    <a href="../logout.php" class="status-icon" title="Logout">
                        <img src="../assets/icons/logout-box.png" alt="Logout">
                    </a>
                </div>
            </div>
        </div>
        <div class="main-content">
            <div class="chat-header" id="chat-header">
                <!-- Wird dynamisch gefüllt -->
            </div>
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
    let models = {};
    let isTemporaryChat = false;

    function loadModels() {
        return fetch('chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_models'
        })
        .then(response => response.json())
        .then(data => {
            models = data;
            console.log('Geladene Modelle:', models);
        });
    }

    document.addEventListener('DOMContentLoaded', async function() {
        await loadModels();

        const urlParams = new URLSearchParams(window.location.search);
        const chatId = urlParams.get('chat');
        
        if (chatId) {
            loadChat(chatId);
        } else {
            createNewChat();
        }
        loadChats();
    });

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
                if (chat.id === 'temporary') return;
                
                const chatItem = document.createElement('div');
                chatItem.className = 'chat-item';
                if (chat.id === currentChatId) chatItem.classList.add('active');
                chatItem.textContent = chat.info.name;

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
        });
    }

    function createNewChat() {
        isTemporaryChat = true;
        currentChatId = null;
        window.history.pushState({}, '', window.location.pathname);
        
        document.getElementById('chat-messages').innerHTML = '';
        const header = document.getElementById('chat-header');
        header.innerHTML = `
            <div class="chat-info">
                <span class="chat-id">Neuer Chat</span>
            </div>
        `;
        
        document.getElementById('message-input').value = '';
        document.getElementById('message-input').focus();
        
        const chatItems = document.querySelectorAll('.chat-item');
        chatItems.forEach(item => item.classList.remove('active'));
    }

    function loadChat(chatId) {
        currentChatId = chatId;
        isTemporaryChat = false;
        
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
                console.log('Geladener Chat:', data);
                displayChat(data);
                updateChatHeader(data);
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
        const input = document.getElementById('message-input');
        const message = input.value.trim();
        
        if (!message) return;
        input.disabled = true;

        if (isTemporaryChat) {
            // Erstelle einen neuen permanenten Chat mit der ersten Nachricht
            fetch('chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=create_chat&first_message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                input.disabled = false;
                if (data.error) {
                    alert(data.error);
                    return;
                }
                isTemporaryChat = false;
                currentChatId = data.chat_id;
                input.value = '';
                window.history.pushState({}, '', `?chat=${data.chat_id}`);
                loadChat(data.chat_id);
                loadChats();
            })
            .catch(error => {
                input.disabled = false;
                console.error('Fehler:', error);
                alert('Fehler beim Erstellen des Chats');
            });
        } else {
            // Normale Nachricht in existierenden Chat senden
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
                    return;
                }
                input.value = '';
                displayChat(data.chat);
                loadChats();
            })
            .catch(error => {
                input.disabled = false;
                console.error('Fehler:', error);
                loadChats()
            });
        }
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

    function updateChatHeader(chatData) {
        const header = document.getElementById('chat-header');
        console.log('Update Header:', { chatData, isTemporaryChat, models }); // Debug-Ausgabe
        
        if (isTemporaryChat) {
            header.innerHTML = `
                <div class="chat-info">
                    <span class="chat-id">Neuer Chat</span>
                </div>
            `;
            return;
        }

        if (!chatData || !chatData.info) {
            header.innerHTML = '';
            return;
        }

        header.innerHTML = `
            <div class="chat-info">
                <div class="chat-status">
                    <?php if($_SESSION['perm'] >= 2): ?>
                        <span class="chat-id" title="Chat ID">#${currentChatId}</span>
                    <?php endif; ?>
                    <?php if($_SESSION['perm'] <= 2): ?>
                        <span class="chat-name" title="Chat Name">#${chatData.info.name}</span>
                    <?php endif; ?>
                    <div class="model-selector" title="Modell auswählen">
                        <img src="../assets/icons/ai.png" alt="AI">
                        <select class="model-select" onchange="changeChatModel(this.value)">
                            ${Object.entries(models).map(([id, model]) => `
                                <option value="${id}" ${chatData.info.model === id ? 'selected' : ''}>
                                    ${model.name} (${model.version})
                            `).join('')}
                        </select>
                    </div>
                </div>
                <div class="chat-actions">
                    <button onclick="removeChat('${currentChatId}')" class="status-icon" title="Chat löschen">
                        <img src="../assets/icons/trash.png" alt="Delete">
                    </button>
                </div>
            </div>
        `;
    }

    function changeChatModel(newModel) {
        if (!currentChatId || isTemporaryChat) return;
        
        fetch('chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=change_chat_model&chat_id=${currentChatId}&new_model=${newModel}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                loadChat(currentChatId);
            }
        });
    }

    document.getElementById('message-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    </script>
</body>
</html>