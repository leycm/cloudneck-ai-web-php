<?php
session_start();
require_once('../config.php');

// Überprüfe Admin-Berechtigung (mindestens Perm 2)
if (!isset($_SESSION['perm']) || $_SESSION['perm'] < 2) {
    header('Location: ../login.php');
    exit();
}

$users = json_decode(file_get_contents('../assets/user.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['perm'] >= 3) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit_credits':
                $user_id = $_POST['user_id'];
                $credits = $_POST['credits'];
                $users[$user_id]['credits'] = intval($credits);
                file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
                break;

            case 'edit_perm':
                if ($_SESSION['perm'] >= 5) {
                    $user_id = $_POST['user_id'];
                    $perm = $_POST['perm'];
                    $users[$user_id]['perm'] = intval($perm);
                    file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
                }
                break;

            case 'edit_chats':
                if ($_SESSION['perm'] >= 3) {
                    $user_id = $_POST['user_id'];
                    $chats = $_POST['chats'];
                    $users[$user_id]['chats'] = intval($chats);
                    file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
                }
                break;

            case 'add_user':
                if ($_SESSION['perm'] >= 5) {
                    $username = $_POST['username'];
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $new_user_id = uniqid();
                    $users[$new_user_id] = [
                        'name' => $username,
                        'password' => $password,
                        'perm' => intval($_POST['perm']),
                        'credits' => intval($_POST['credits']),
                        'chats' => intval($_POST['chats'])
                    ];
                    file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
                }
                break;

            case 'login_as':
                if ($_SESSION['perm'] >= 5) {
                    $user_id = $_POST['user_id'];
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['perm'] = $users[$user_id]['perm'];
                    header('Location: chat.php');
                    exit();
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CloudNeck - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/panel.css">
    <style>
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <div>
                <a href="chat.php" class="action-button">Zurück zum Chat</a>
            </div>
        </div>

        <table class="user-table">
            <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Berechtigung</th>
                <th>Credits</th>
                <th>Chats</th>
                <th>Aktionen</th>
            </tr>
            <?php foreach ($users as $user_id => $user): ?>
            <tr>
                <td><?= htmlspecialchars($user_id) ?></td>
                <td><?= htmlspecialchars($user['name']) ?></td>
                <td>
                    <?php if ($_SESSION['perm'] >= 5): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="edit_perm">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <select name="perm" onchange="this.form.submit()">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $user['perm'] == $i ? 'selected' : '' ?>>
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </form>
                    <?php else: ?>
                        <?= $user['perm'] ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($_SESSION['perm'] >= 3): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="edit_credits">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="number" name="credits" value="<?= $user['credits'] ?>" style="width: 80px">
                            <button type="submit" class="action-button edit-button">Speichern</button>
                        </form>
                    <?php else: ?>
                        <?= $user['credits'] ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($_SESSION['perm'] >= 3): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="edit_chats">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="number" name="chats" value="<?= $user['chats'] ?>" style="width: 80px">
                            <button type="submit" class="action-button edit-button">Speichern</button>
                        </form>
                    <?php else: ?>
                        <?= $user['chats'] ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($_SESSION['perm'] >= 5): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="login_as">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <button type="submit" class="action-button login-button">Als Benutzer einloggen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($_SESSION['perm'] >= 5): ?>
        <div class="inline-form">
            <form method="POST" style="display: flex; gap: 10px; align-items: center; width: 100%;">
                <input type="hidden" name="action" value="add_user">
                <div>
                    <label></label>
                    <input type="text" name="username" required placeholder="Benutzername">
                </div>
                <div>
                    <label></label>
                    <input type="password" name="password" required placeholder="Passwort">
                </div>
                <div>
                    <label></label>
                    <select name="perm">
                        <option value="1">User (1)</option>
                        <option value="2">Mod (2)</option>
                        <option value="3">Admin (3)</option>
                        <option value="4">Super (4)</option>
                        <option value="5">System (5)</option>
                    </select>
                </div>
                <div>
                    <label></label>
                    <input type="number" name="credits" value="100" style="width: 80px" placeholder="Credits">
                </div>
                <div>
                    <label></label>
                    <input type="number" name="chats" value="10" style="width: 80px" placeholder="Chat Limit">
                </div>
                <button type="submit">Benutzer erstellen</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>