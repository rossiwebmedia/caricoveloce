<?php
require_once 'config.php';
require_once 'functions.php';

// Connessione diretta al database senza usare getDbConnection()
try {
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
    
    // Crea un nuovo utente con credenziali fisse
    $username = 'directadmin';
    $plainPassword = 'directpass123';
    
    // Genera hash usando PASSWORD_DEFAULT
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    echo "Username: $username<br>";
    echo "Password: $plainPassword<br>";
    echo "Hash generato: $hashedPassword<br>";
    
    // Elimina utente se esiste
    $stmt = $conn->prepare("DELETE FROM utenti WHERE username = ?");
    $stmt->execute([$username]);
    
    // Inserisci nuovo utente
    $stmt = $conn->prepare("INSERT INTO utenti (username, password, email, nome_completo, ruolo) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$username, $hashedPassword, 'direct@example.com', 'Direct Admin', 'admin']);
    
    echo $success ? "Utente creato con successo!<br>" : "Errore nella creazione dell'utente<br>";
    
    // Verifica che l'utente sia stato creato
    $stmt = $conn->prepare("SELECT * FROM utenti WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "Utente trovato nel database.<br>";
        echo "ID: {$user['id']}<br>";
        echo "Username: {$user['username']}<br>";
        echo "Password salvata: {$user['password']}<br>";
        
        // Verifica password
        $passwordCheck = password_verify($plainPassword, $user['password']);
        echo "Verifica password: " . ($passwordCheck ? "SÌ" : "NO") . "<br>";
    } else {
        echo "Utente non trovato nel database dopo l'inserimento!<br>";
    }
    
    echo "<br><strong>Prova ad accedere con:</strong><br>";
    echo "Username: $username<br>";
    echo "Password: $plainPassword<br>";
    
} catch (PDOException $e) {
    echo "Errore database: " . $e->getMessage();
}
?>