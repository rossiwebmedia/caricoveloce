<?php
/**
 * API per verificare se uno SKU esiste già
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

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Metodo non consentito'
    ]);
    exit;
}

// Ottieni lo SKU dalla richiesta
$sku = isset($_POST['sku']) ? sanitizeInput($_POST['sku']) : '';

if (empty($sku)) {
    echo json_encode([
        'success' => false,
        'message' => 'SKU non specificato'
    ]);
    exit;
}

try {
    // Verifica se lo SKU esiste già
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM prodotti WHERE sku = ?");
    $stmt->execute([$sku]);
    $exists = $stmt->fetch() !== false;
    
    echo json_encode([
        'success' => true,
        'exists' => $exists,
        'sku' => $sku
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}