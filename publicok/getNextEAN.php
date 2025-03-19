<?php
/**
 * API per ottenere il prossimo EAN disponibile
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

try {
    // Ottieni un EAN non utilizzato
    $ean = getNextEAN();
    
    if ($ean) {
        echo json_encode([
            'success' => true,
            'ean' => $ean
        ]);
    } else {
        // Genera un EAN demo
        echo json_encode([
            'success' => false,
            'message' => 'Nessun codice EAN disponibile'
        ]);
    }
} catch (Exception $e) {
    logMessage("Errore nel recupero del prossimo EAN: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}