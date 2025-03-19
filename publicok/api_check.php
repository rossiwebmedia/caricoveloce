<?php
/**
 * Script per verificare lo stato della connessione con l'API Smarty
 */
require_once 'error_handler.php';
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Utente non autenticato'
    ]);
    exit;
}

try {
    // Ottieni l'API key predefinita
    $apiKey = getDefaultApiKey();
    
    if (empty($apiKey)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Nessuna API key configurata. Vai in Impostazioni per configurare una API key.'
        ]);
        exit;
    }
    
    // URL dell'API
    $url = SMARTY_API_BASE_URL . 'Products/list?ApiKey=' . urlencode($apiKey) . '&limit=1';
    
    // Inizializza cURL
    $ch = curl_init($url);
    
    // Imposta le opzioni cURL
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10  // Timeout di 10 secondi
    ]);
    
    // Esegui la richiesta
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    // Registra il risultato nei log per debugging
    logMessage("API Check - HTTP Code: $httpCode", 'DEBUG');
    logMessage("API Check - Response: " . substr($response, 0, 500), 'DEBUG');
    
    curl_close($ch);
    
    if ($error) {
        logMessage("Errore cURL nella verifica della connessione: " . $error, 'ERROR');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Errore di connessione: ' . $error,
            'http_code' => $httpCode
        ]);
        exit;
    }
    
    // Parsa la risposta JSON
    $result = json_decode($response, true);
    
    // Verifica se la decodifica JSON è riuscita
    if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Errore nella decodifica della risposta JSON: " . json_last_error_msg(), 'ERROR');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Risposta non valida dall\'API',
            'http_code' => $httpCode,
            'raw_response' => substr($response, 0, 100) // Mostra solo una parte della risposta
        ]);
        exit;
    }
    
    // La risposta è un JSON valido, ma dobbiamo verificare il contenuto
    header('Content-Type: application/json');
    
    // Caso 1: Campo 'success' esplicito
    if (isset($result['success'])) {
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Connessione a Smarty API attiva' : ($result['message'] ?? 'Errore restituito dall\'API Smarty'),
            'api_version' => $result['api_version'] ?? 'N/A',
            'http_code' => $httpCode
        ]);
        exit;
    }
    
    // Caso 2: La presenza di 'items' o altri campi chiave può indicare successo
    if (isset($result['items']) || isset($result['total']) || isset($result['count'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Connessione a Smarty API attiva',
            'api_version' => $result['api_version'] ?? 'N/A',
            'items_count' => count($result['items'] ?? []),
            'http_code' => $httpCode
        ]);
        exit;
    }
    
    // Caso 3: Errore esplicito nell'API
    if (isset($result['error']) || isset($result['errors'])) {
        $errorMessage = isset($result['error']) ? $result['error'] : (isset($result['errors'][0]) ? $result['errors'][0] : 'Errore restituito dall\'API Smarty');
        echo json_encode([
            'success' => false,
            'message' => $errorMessage,
            'http_code' => $httpCode
        ]);
        exit;
    }
    
    // Se arriviamo qui, consideriamo la connessione riuscita ma con un formato di risposta inaspettato
    echo json_encode([
        'success' => true,
        'message' => 'Connessione a Smarty API attiva (risposta inaspettata)',
        'api_version' => 'N/A',
        'http_code' => $httpCode,
        'response_preview' => substr(json_encode($result), 0, 100) . '...'
    ]);
    
} catch (Exception $e) {
    logMessage("Errore nella verifica della connessione: " . $e->getMessage(), 'ERROR');
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}