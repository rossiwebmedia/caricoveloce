<?php
require_once 'config.php';
require_once 'functions.php';

// Reindirizza se già loggato
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Abilita la modalità debug in fase di sviluppo
$debug_mode = true;
$error = '';
$debug_info = '';

// Gestione login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if ($debug_mode) {
        $debug_info .= "Username inserito: " . htmlspecialchars($username) . "<br>";
        $debug_info .= "Password inserita: " . (empty($password) ? "VUOTA" : "PRESENTE") . "<br>";
    }
    
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password';
    } else {
        try {
            // Connessione diretta al database per evitare problemi con getDbConnection
            $conn = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // Verifica le credenziali
            $stmt = $conn->prepare("SELECT * FROM utenti WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($debug_mode) {
                $debug_info .= "Utente trovato nel database: " . ($user ? "SI" : "NO") . "<br>";
                if ($user) {
                    $debug_info .= "ID utente: " . $user['id'] . "<br>";
                    $debug_info .= "Password nel DB: " . $user['password'] . "<br>";
                    $passwordCheck = password_verify($password, $user['password']);
                    $debug_info .= "Risultato verifica password: " . ($passwordCheck ? "SI" : "NO") . "<br>";
                    
                    // Test diretto con stringa di confronto
                    $passwordCheck2 = ($password === $user['password']);
                    $debug_info .= "Confronto diretto password: " . ($passwordCheck2 ? "SI" : "NO") . "<br>";
                }
            }
            
            // MODIFICA: Accetta sia password con hash che dirette per scopi di debug
            if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
                // Credenziali valide, imposta la sessione
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['ruolo'];
                
                // Aggiorna l'ultimo accesso
                $updateStmt = $conn->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                if ($debug_mode) {
                    $debug_info .= "Login riuscito! Reindirizzamento a index.php<br>";
                }
                
                // Reindirizza alla dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = 'Credenziali non valide';
            }
        } catch (PDOException $e) {
            $error = 'Errore di connessione al database: ' . $e->getMessage();
            if ($debug_mode) {
                $debug_info .= "Eccezione PDO: " . $e->getMessage() . "<br>";
            }
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
        .debug-info {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
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
            </form>
            
            <?php if ($debug_mode && !empty($debug_info)): ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    <?php echo $debug_info; ?>
                </div>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <small class="text-muted">Prova con: directadmin / directpass123</small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>