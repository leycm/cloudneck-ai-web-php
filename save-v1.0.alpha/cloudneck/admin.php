<?php
session_start();
require_once('../config.php');

// Überprüfe Admin-Berechtigung
if (!isset($_SESSION['perm']) || $_SESSION['perm'] < 4) {
    header('Location: ../login.php');
    exit();
}

// Lade Benutzerdaten
$users = json_decode(file_get_contents('../assets/user.json'), true);

// Bearbeite Benutzer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit_credits':
                $user_id = $_POST['user_id'];
                $credits = $_POST['credits'];
                $users[$user_id]['credits'] = intval($credits);
                file_put_contents('../assets/user.json', json_encode($users, JSON_PRETTY_PRINT));
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CloudNeck - Admin</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Admin Panel</h1>
    <table>
        <tr>
            <th>User ID</th>
            <th>Name</th>
            <th>Berechtigung</th>
            <th>Credits</th>
            <th>Aktion</th>
        </tr>
        <?php foreach ($users as $user_id => $user): ?>
        <tr>
            <td><?= $user_id ?></td>
            <td><?= $user['name'] ?></td>
            <td><?= $user['perm'] ?></td>
            <td>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="edit_credits">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    <input type="number" name="credits" value="<?= $user['credits'] ?>">
                    <button type="submit">Speichern</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="chat.php">Zurück zum Chat</a></p>
</body>
</html>