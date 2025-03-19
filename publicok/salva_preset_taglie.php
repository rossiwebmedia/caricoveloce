<?php
/**
 * API per il salvataggio di un preset di taglie
 */
require_once 'config.php';
require_once 'functions.php';
require_once 'funzioni_cache.php';

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

// Ottieni i dati JSON dalla richiesta
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica che i dati siano validi
if (!$data || !isset($data['nome']) || !isset($data['taglie']) || !is_array($data['taglie'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dati non validi'
    ]);
    exit;
}

$nome = sanitizeInput($data['nome']);
$taglie = array_map('sanitizeInput', $data['taglie']);

// Salva il preset
try {
    $result = saveTagliePreset($nome, $taglie);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Preset salvato con successo',
            'id' => $result
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Errore nel salvataggio del preset'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}