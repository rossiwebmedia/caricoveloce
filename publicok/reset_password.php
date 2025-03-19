<?php
// reset_password.php
require_once 'config.php';
require_once 'functions.php';

$error = '';
$success = '';
$validToken = false;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    header('Location: login.php');
    exit;
}

// Log del token
error_log("Richiesta reset password con token: $token");

// Verifica validità del token
try {
    $conn = getDbConnection();
    
    // Verifica che la tabella esista
    try {
        $conn->query("SELECT 1 FROM password_reset LIMIT 1");
    } catch (PDOException $e) {
        // La tabella non esiste, creala
        $sql = "CREATE TABLE password_reset (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires DATETIME NOT NULL,
            utilizzato TINYINT(1) NOT NULL DEFAULT 0,
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->exec($sql);
        error_log("Tabella password_reset creata");
    }
    
    // Cerca il token
    $stmt = $conn->prepare("
        SELECT pr.*, u.username 
        FROM password_reset pr
        JOIN utenti u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.utilizzato = 0
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if ($reset) {
        // Verifica se il token è scaduto
        $expires = strtotime($reset['expires']);
        $now = time();
        
        if ($now > $expires) {
            $error = "Questo link è scaduto. Richiedi un nuovo link per reimpostare la password.";
            error_log("Token scaduto: " . $reset['expires']);
        } else {
            $validToken = true;
            $userId = $reset['user_id'];
            
            // Gestione del reset password
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (strlen($password) < 8) {
                    $error = "La password deve contenere almeno 8 caratteri.";
                } elseif ($password !== $confirmPassword) {
                    $error = "Le password non coincidono.";
                } else {
                    try {
                        // Aggiorna la password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $conn->prepare("UPDATE utenti SET password = ? WHERE id = ?");
                        $updateStmt->execute([$hashedPassword, $userId]);
                        
                        // Segna il token come utilizzato
                        $tokenStmt = $conn->prepare("UPDATE password_reset SET utilizzato = 1 WHERE token = ?");
                        $tokenStmt->execute([$token]);
                        
                        $success = "Password aggiornata con successo!";
                        error_log("Password reimpostata con successo per l'utente ID: $userId");
                    } catch (PDOException $e) {
                        $error = "Errore durante l'aggiornamento della password: " . $e->getMessage();
                        error_log("Errore reset password: " . $e->getMessage());
                    }
                }
            }
        }
    } else {
        $error = "Link per il reset della password non valido o già utilizzato.";
        error_log("Token non trovato o già utilizzato: $token");
    }
} catch (PDOException $e) {
    $error = "Si è verificato un errore. Riprova più tardi.";
    error_log("Errore database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimposta Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-form {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #3498db;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-form">
            <div class="logo">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <div class="text-center mt-3">
                    <a href="recupera_password.php" class="btn btn-outline-primary">Richiedi un nuovo link</a>
                </div>
            <?php elseif (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <p class="text-center">La tua password è stata reimpostata con successo. Ora puoi accedere al tuo account.</p>
                <div class="d-grid mt-3">
                    <a href="login.php" class="btn btn-primary">Vai al login</a>
                </div>
            <?php elseif ($validToken): ?>
                <h4 class="text-center mb-4">Reimposta la tua password</h4>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="password" class="form-label">Nuova Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        <div class="form-text">La password deve contenere almeno 8 caratteri.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Conferma Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Reimposta Password</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>