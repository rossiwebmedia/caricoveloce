<?php
/**
 * API per l'invio di più prodotti a Smarty contemporaneamente
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

// Verifica l'azione richiesta
$action = $_POST['action'] ?? '';
if ($action !== 'send_to_smarty') {
    echo json_encode([
        'success' => false,
        'message' => 'Azione non valida'
    ]);
    exit;
}

// Ottieni i parametri
$fornitoreId = $_POST['fornitore_id'] ?? '';
$productIds = isset($_POST['product_ids']) ? json_decode($_POST['product_ids'], true) : [];

// Validazione
if (empty($fornitoreId) || empty($productIds)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parametri mancanti'
    ]);
    exit;
}

// Connessione al database
$conn = getDbConnection();

try {
    // Recupera i prodotti dal database
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT * FROM prodotti 
        WHERE id IN ($placeholders) AND fornitore_id = ? AND stato = 'attivo'
    ");
    
    // Aggiungi l'ID del fornitore ai parametri
    $params = array_merge($productIds, [$fornitoreId]);
    $stmt->execute($params);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nessun prodotto trovato con gli ID forniti'
        ]);
        exit;
    }
    
    // Raggruppa i prodotti per marca
    $productsByBrand = [];
    foreach ($products as $product) {
        $marcaId = $product['marca_id'];
        if (!isset($productsByBrand[$marcaId])) {
            $productsByBrand[$marcaId] = [];
        }
        $productsByBrand[$marcaId][] = $product;
    }
    
    // Invia i prodotti a Smarty in batch per marca
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($productsByBrand as $marcaId => $brandProducts) {
        // Prepara i dati per l'invio
        $smartyData = [];
        
        foreach ($brandProducts as $product) {
            // Verifica che il prodotto non sia già su Smarty
            if (!empty