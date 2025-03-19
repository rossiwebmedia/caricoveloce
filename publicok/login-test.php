<?php
require_once 'config.php';
require_once 'functions.php';

// Credenziali fisse per test
$test_username = 'admin';
$test_password = 'admin123';

$conn = getDbConnection();

// Inserisci un utente di test se non esiste
$stmt = $conn->prepare("SELECT COUNT(*) FROM utenti WHERE username = ?");
$stmt->execute([$test_username]);
if ($stmt->fetchColumn() == 0) {
    $hashedPassword = password_hash($test_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO utenti (username, password, email, nome_completo, ruolo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$test_username, $hashedPassword, 'admin@esempio.com', 'Admin Test', 'admin']);
    echo "Utente test creato!<br>";
}

// Form di login semplice
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT * FROM utenti WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        echo "<div style='color:green'>Login riuscito! Utente: {$user['username']}, Ruolo: {$user['ruolo']}</div>";
    } else {
        echo "<div style='color:red'>Credenziali non valide</div>";
        
        // Debug info
        echo "<div style='margin-top: 20px; padding: 10px; background: #f8f9fa; border: 1px solid #ddd;'>";
        echo "Debug info:<br>";
        echo "Username: $username<br>";
        echo "Utente trovato: " . ($user ? "SÌ" : "NO") . "<br>";
        if ($user) {
            echo "Password hash nel DB: " . $user['password'] . "<br>";
            echo "Verifica password: " . (password_verify($password, $user['password']) ? "SÌ" : "NO") . "<br>";
        }
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
</head>
<body>
    <h2>Test Login</h2>
    <form method="post">
        <div>
            <label>Username:</label>
            <input type="text" name="username" value="admin">
        </div>
        <div style="margin-top: 10px;">
            <label>Password:</label>
            <input type="password" name="password" value="admin123">
        </div>
        <div style="margin-top: 10px;">
            <button type="submit">Login</button>
        </div>
    </form>
</body>
</html>