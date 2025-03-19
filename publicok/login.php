<?php
require_once 'config.php';
require_once 'functions.php';

// Reindirizza se già loggato
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Gestione login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password';
    } else {
        $conn = getDbConnection();
        
        // Verifica le credenziali
        $stmt = $conn->prepare("SELECT id, username, password, ruolo FROM utenti WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Sostituisci la sezione di verifica nel login.php con questo codice:
        if ($user) {
            // Debug - salva informazioni in un log
            error_log('Tentativo di login per l\'utente: ' . $username);
            error_log('Password fornita: ' . $password);
            error_log('Hash password nel DB: ' . $user['password']);
            
            if (password_verify($password, $user['password'])) {
                // Credenziali valide, imposta la sessione
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['ruolo'];
                
                // Aggiorna l'ultimo accesso
                $updateStmt = $conn->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Reindirizza alla dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = 'Password non corretta';
                error_log('Verifica password fallita per l\'utente: ' . $username);
            }
        } else {
            $error = 'Utente non trovato';
            error_log('Utente non trovato: ' . $username);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-form {
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
        <div class="login-form">
            <div class="logo">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Accedi</button>
                </div>

                <div class="text-center mt-3">
                    <a href="recupera_password.php">Password dimenticata?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>