// In un file temporaneo db_test.php
<?php
require_once 'config.php';

try {
    $conn = getDbConnection();
    echo "Connessione al database riuscita!<br>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM utenti");
    $count = $stmt->fetchColumn();
    echo "Numero di utenti nel database: " . $count;
} catch (Exception $e) {
    echo "Errore di connessione: " . $e->getMessage();
}