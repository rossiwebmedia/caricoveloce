<?php
/**
 * API per la ricerca di fornitori con autocomplete
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

// Ottieni il termine di ricerca
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

try {
    // Verifica se esiste un file di cache dei fornitori
    $cacheFile = UPLOADS_DIR . '/fornitori_cache.json';
    $fornitori = [];
    
    if (file_exists($cacheFile)) {
        // Leggi i fornitori dalla cache
        $fornitori = json_decode(file_get_contents($cacheFile), true) ?: [];
        
        // Filtra i fornitori per il termine di ricerca
        if (!empty($searchTerm)) {
            $searchTermLower = strtolower($searchTerm);
            $fornitori = array_filter($fornitori, function($fornitore) use ($searchTermLower) {
                $namefield = isset($fornitore['name']) ? $fornitore['name'] : 
                             (isset($fornitore['business_name']) ? $fornitore['business_name'] : '');
                return stripos(strtolower($namefield), $searchTermLower) !== false;
            });
        }
    }
    
    // Se non abbiamo fornitori nella cache o ne abbiamo pochi, chiamiamo l'API
    if (empty($fornitori) || count($fornitori) < 5) {
        // Chiamata all'API Smarty per ottenere i fornitori
        $apiKey = getDefaultApiKey();
        if ($apiKey) {
            $endpoint = 'Suppliers/list';
            if (!empty($searchTerm)) {
                $endpoint .= '?search=' . urlencode($searchTerm);
            }
            
            $smartyResponse = callSmartyApi($endpoint, 'GET', null, $apiKey);
            
            if (isset($smartyResponse['items']) && is_array($smartyResponse['items'])) {
                // Aggiorna la cache
                if (empty($searchTerm)) {
                    file_put_contents($cacheFile, json_encode($smartyResponse['items']));
                }
                
                // Usa i fornitori dall'API
                $fornitori = $smartyResponse['items'];
            }
        }
    }
    
    // Verifica anche nel database locale
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT DISTINCT fornitore FROM prodotti WHERE fornitore LIKE ? AND fornitore <> '' LIMIT 20");
    $stmt->execute(['%' . $searchTerm . '%']);
    $localFornitori = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Aggiungi i fornitori locali se non esistono già nell'elenco
    foreach ($localFornitori as $localFornitore) {
        $exists = false;
        foreach ($fornitori as $fornitore) {
            if (isset($fornitore['name']) && $fornitore['name'] === $localFornitore) {
                $exists = true;
                break;
            }
            if (isset($fornitore['business_name']) && $fornitore['business_name'] === $localFornitore) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $fornitori[] = ['name' => $localFornitore, 'business_name' => $localFornitore];
        }
    }
    
    // Limita il numero di risultati
    $fornitori = array_slice($fornitori, 0, 20);
    
    echo json_encode([
        'success' => true,
        'fornitori' => array_values($fornitori)
    ]);
} catch (Exception $e) {
    logMessage("Errore nella ricerca dei fornitori: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}