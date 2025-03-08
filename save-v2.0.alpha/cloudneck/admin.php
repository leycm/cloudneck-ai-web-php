<?php
session_start();
require_once('../config.php');

// Überprüfe Admin-Berechtigung (mindestens Perm 2)
if (!isset($_SESSION['perm']) || $_SESSION['perm'] < 2) {
    header('Location: ../login.php');
    exit();
}

// Lade Benutzerdaten
$users = json_decode(file_get_contents('../assets/user.json'), true);

// Bearbeite Benutzer
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

            case 'add_user':
                if ($_SESSION['perm'] >= 5) {
                    $username = $_POST['username'];
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $new_user_id = uniqid();
                    $users[$new_user_id] = [
                        'name' => $username,
                        'password' => $password,
                        'perm' => intval($_POST['perm']),
                        'credits' => intval($_POST['credits'])
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
    <style>
        .user-table {
            width: 100%;
            border-collapse: collapse;
            background: linear-gradient(135deg, rgba(2, 2, 2, 0.9), rgba(5, 5, 5, 0.95));
            border: 1px solid var(--border-color);
            box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.6);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .user-table tbody {
            border-radius: 12px;
        }

        .user-table th, .user-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary-color);
        }

        .user-table th {
            background: linear-gradient(135deg, #0d0d0d, #1a1a1a);
            color: var(--text-secondary-color);
            font-weight: 500;
        }

        .user-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .user-table tr:last-child td {
            border-bottom: none;
        }

        .user-table input, .user-table select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: white;
            padding: 5px 10px;
        }

        .user-table input:focus, .user-table select:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .user-table button {
            background: linear-gradient(135deg, #0d0d0d, #1a1a1a);
            color: var(--text-secondary-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-table button:hover {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            border-color: var(--accent-color);
        }

        .action-button {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
            background: linear-gradient(135deg, #0d0d0d, #1a1a1a);
            color: var(--text-secondary-color);
            transition: all 0.3s ease;
        }

        .action-button:hover {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            border-color: var(--accent-color);
        }

        .edit-button { 
            background: linear-gradient(135deg, #1a472a, #2a573a);
        }

        .edit-button:hover {
            background: linear-gradient(135deg, #2a573a, #3a674a);
        }

        .login-button { 
            background: linear-gradient(135deg, #1a237e, #283593);
        }

        .login-button:hover {
            background: linear-gradient(135deg, #283593, #3849aa);
        }
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
                    <input type="number" name="credits" value="100" style="width: 80px">
                </div>
                <button type="submit">Benutzer erstellen</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>