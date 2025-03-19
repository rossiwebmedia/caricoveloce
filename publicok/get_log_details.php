<?php
/**
 * API per recuperare i dettagli di un log di sincronizzazione
 */
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Utente non autenticato'
    ]);
    exit;
}

// Verifica che sia stata fornita un ID valido
$logId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($logId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID log non valido'
    ]);
    exit;
}

try {
    // Connessione al database
    $conn = getDbConnection();
    
    // Ottieni le informazioni sul log
    $stmt = $conn->prepare("SELECT * FROM log_sincronizzazione WHERE id = ?");
    $stmt->execute([$logId]);
    $log = $stmt->fetch();
    
    if (!$log) {
        echo json_encode([
            'success' => false,
            'message' => 'Log non trovato'
        ]);
        exit;
    }
    
    // Restituisci i dettagli del log
    echo json_encode([
        'success' => true,
        'id' => $log['id'],
        'prodotto_id' => $log['prodotto_id'],
        'azione' => $log['azione'],
        'riuscito' => (bool)$log['riuscito'],
        'risposta_api' => $log['risposta_api'],
        'creato_il' => $log['creato_il'],
        'creato_il_formatted' => date('d/m/Y H:i:s', strtotime($log['creato_il']))
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore nel recupero dei dettagli del log: ' . $e->getMessage()
    ]);
}