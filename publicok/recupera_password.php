<?php
// recupera_password.php
require_once 'config.php';
require_once 'functions.php';

$error = '';
$success = '';

// Controlla se la tabella password_reset esiste, altrimenti creala
function verificaTabellaPasswordReset() {
    try {
        $conn = getDbConnection();
        $conn->query("SELECT 1 FROM password_reset LIMIT 1");
        return true;
    } catch (PDOException $e) {
        $conn = getDbConnection();
        $sql = "CREATE TABLE password_reset (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires DATETIME NOT NULL,
            utilizzato TINYINT(1) NOT NULL DEFAULT 0,
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->exec($sql);
        return true;
    }
}

// Gestione del recupero password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Inserisci la tua email';
    } else {
        $conn = getDbConnection();
        
        // Verifica se l'email esiste
        $stmt = $conn->prepare("SELECT id, username FROM utenti WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Assicurati che la tabella password_reset esista
            verificaTabellaPasswordReset();
            
            // Genera token per reset password
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Elimina eventuali token precedenti
            $deleteStmt = $conn->prepare("DELETE FROM password_reset WHERE user_id = ?");
            $deleteStmt->execute([$user['id']]);
            
            // Salva il token nel database
            $tokenStmt = $conn->prepare("INSERT INTO password_reset (user_id, token, expires, utilizzato) VALUES (?, ?, ?, 0)");
            $tokenStmt->execute([$user['id'], $token, $expires]);
            
            // Crea URL assoluto per reset
            $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                       "://" . $_SERVER['HTTP_HOST'] . 
                       dirname($_SERVER['PHP_SELF']) . 
                       "/reset_password.php?token=" . $token;
            
            // Invio email
            $to = $email;
            $subject = SITE_NAME . " - Recupero Password";
            $message = "Ciao " . $user['username'] . ",\n\n";
            $message .= "Hai richiesto il reset della password. Clicca sul link seguente per reimpostare la password:\n\n";
            $message .= $resetUrl . "\n\n";
            $message .= "Il link scadrà tra un'ora.\n\n";
            $message .= "Se non hai richiesto il reset della password, ignora questa email.\n\n";
            $message .= "Cordiali saluti,\n" . SITE_NAME . " Team";
            
            $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
            
            // Log del tentativo di invio email
            error_log("Tentativo invio email a: $email");
            error_log("URL Reset: $resetUrl");
            
            $mailSent = @mail($to, $subject, $message, $headers);
            
            if ($mailSent) {
                error_log("Email inviata con successo a: $email");
                $success = "Ti abbiamo inviato un'email con le istruzioni per reimpostare la password. Controlla la tua casella di posta.";
            } else {
                error_log("Errore nell'invio email a: $email");
                // In caso di errore nell'invio email, mostra comunque un messaggio generico
                // e salva il link nei log per poterlo recuperare
                $success = "Ti abbiamo inviato un'email con le istruzioni per reimpostare la password. Controlla la tua casella di posta.";
                error_log("LINK DI RECUPERO: $resetUrl");
            }
        } else {
            // Per sicurezza mostriamo lo stesso messaggio anche se l'email non esiste
            $success = "Se l'email è associata a un account, riceverai presto istruzioni per reimpostare la password.";
            error_log("Email non trovata nel database: $email");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupera Password - <?php echo SITE_NAME; ?></title>
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
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <p class="text-center mb-4">Inserisci la tua email per ricevere le istruzioni per il recupero della password.</p>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Recupera Password</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <a href="login.php">Torna al login</a>
            </div>
        </div>
    </div>
</body>
</html>