<!DOCTYPE html>
<html>
<head>
    <title>CloudNeck</title>
    <style>
        .container {
            text-align: center;
            margin-top: 100px;
        }
        .nav-links a {
            margin: 0 10px;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Willkommen bei CloudNeck</h1>
        <div class="nav-links">
            <a href="login.php">Login</a>
            <a href="cloudneck/chat.php">Chat</a>
            <a href="logout.php">Logout</a>
            <?php if(isset($_SESSION['perm']) && $_SESSION['perm'] >= 4): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>