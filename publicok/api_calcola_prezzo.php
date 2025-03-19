<?php
/**
 * API per calcolare il prezzo di vendita
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

// Ottieni i parametri
$prezzoAcquisto = isset($_POST['prezzo_acquisto']) ? floatval($_POST['prezzo_acquisto']) : 0;
$tipologia = isset($_POST['tipologia']) ? sanitizeInput($_POST['tipologia']) : '';

// Validazione
if ($prezzoAcquisto <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Prezzo di acquisto non valido'
    ]);
    exit;
}

if (empty($tipologia)) {
    echo json_encode([
        'success' => false,
        'message' => 'Tipologia non specificata'
    ]);
    exit;
}

try {
    // Ottieni i dati della tipologia dal database
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT moltiplicatore_prezzo, arrotonda_a FROM tipologie_prodotto WHERE nome = ?");
    $stmt->execute([$tipologia]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Se la tipologia non esiste, usa valori predefiniti
        $moltiplicatore = 3.0;
        $arrotondaA = 9.90;
    } else {
        $moltiplicatore = (float)$result['moltiplicatore_prezzo'];
        $arrotondaA = (float)$result['arrotonda_a'];
    }
    
    // Calcola il prezzo di vendita
    $prezzoVendita = calcolaPrezzoVendita($prezzoAcquisto, $tipologia);
    
    // Restituisci il risultato
    echo json_encode([
        'success' => true,
        'prezzo_acquisto' => $prezzoAcquisto,
        'moltiplicatore' => $moltiplicatore,
        'arrotonda_a' => $arrotondaA,
        'prezzo_vendita' => $prezzoVendita
    ]);
} catch (Exception $e) {
    logMessage("Errore nel calcolo del prezzo: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno: ' . $e->getMessage()
    ]);
}
