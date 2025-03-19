<?php
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

// Ottieni i dati JSON dalla richiesta
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['nome']) || empty($data['nome']) ||
    !isset($data['moltiplicatore_prezzo']) || !is_numeric($data['moltiplicatore_prezzo']) ||
    !isset($data['arrotonda_a']) || !is_numeric($data['arrotonda_a'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dati mancanti o non validi'
    ]);
    exit;
}

try {
    