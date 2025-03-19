<?php
/**
 * Proxy per le richieste a Smarty API per evitare problemi di CORS e nascondere le API Key
 */
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode([
        'success' => false,
        'message' => 'Utente non autenticato'
    ]);
    exit;
}

// Ottieni l'endpoint dall'URL
$endpoint = isset($_GET['endpoint']) ? sanitizeInput($_GET['endpoint']) : '';

// Ottieni la chiave API
$apiKey = isset($_GET['apiKey']) ? $_GET['apiKey'] : getDefaultApiKey();

// Verifica parametri obbligatori
if (empty($endpoint) || empty($apiKey)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => 'Parametri mancanti (endpoint o apiKey)'
    ]);
    exit;
}

// Costruisci l'URL dell'API
$url = SMARTY_API_BASE_URL . $endpoint;

// Aggiungi ApiKey all'URL se non è già presente
if (strpos($url, 'ApiKey=') === false) {
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    $url .= $separator . 'ApiKey=' . urlencode($apiKey);
}

// Ottieni tutti i parametri dall'URL originale tranne ApiKey ed endpoint
$params = $_GET;
unset($params['apiKey']);
unset($params['endpoint']);

// Aggiungi parametri rimanenti all'URL
foreach ($params as $key => $value) {
    $url .= '&' . urlencode($key) . '=' . urlencode($value);
}

// Ottieni il metodo HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Ottieni il contenuto della richiesta per POST o PUT
$inputData = null;
if ($method === 'POST' || $method === 'PUT') {
    $inputData = file_get_contents('php://input');
}

// Log della richiesta (solo per debug)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("[Smarty API Request] $method $url");
    if ($inputData) {
        error_log("[Smarty API Request Body] $inputData");
    }
}

// Inizializza cURL
$ch = curl_init($url);

// Imposta le opzioni cURL
$options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => false
];

// Aggiungi il corpo della richiesta per POST o PUT
if (($method === 'POST' || $method === 'PUT') && $inputData !== null) {
    $options[CURLOPT_POSTFIELDS] = $inputData;
}

curl_setopt_array($ch, $options);

// Esegui la richiesta
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

// Imposta lo stesso codice HTTP della risposta originale
http_response_code($httpCode);

// Imposta gli header di risposta
header('Content-Type: application/json');

// Gestione errori
if ($error) {
    error_log("Errore cURL nella chiamata API Smarty: $error");
    echo json_encode([
        'success' => false,
        'message' => "Errore di comunicazione: $error",
        'http_code' => $httpCode
    ]);
    exit;
}

// Log della risposta (solo per debug)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("[Smarty API Response] HTTP $httpCode: $response");
}

// Restituisci la risposta originale
echo $response;