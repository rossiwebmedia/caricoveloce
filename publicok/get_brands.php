<?php
/**
 * API per la ricerca di marche con autocomplete
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
    // Verifica se esiste un file di cache delle marche
    $cacheFile = UPLOADS_DIR . '/marche_cache.json';
    $marche = [];
    
    if (file_exists($cacheFile)) {
        // Leggi le marche dalla cache
        $marche = json_decode(file_get_contents($cacheFile), true) ?: [];
        
        // Filtra le marche per il termine di ricerca
        if (!empty($searchTerm)) {
            $searchTermLower = strtolower($searchTerm);
            $marche = array_filter($marche, function($marca) use ($searchTermLower) {
                return isset($marca['name']) && stripos(strtolower($marca['name']), $searchTermLower) !== false;
            });
        }
    }
    
    // Se non abbiamo marche nella cache o ne abbiamo poche, chiamiamo l'API
    if (empty($marche) || count($marche) < 5) {
        // Chiamata all'API Smarty per ottenere le marche
        $apiKey = getDefaultApiKey();
        if ($apiKey) {
            $endpoint = 'Brands/list';
            if (!empty($searchTerm)) {
                $endpoint .= '?search=' . urlencode($searchTerm);
            }
            
            $smartyResponse = callSmartyApi($endpoint, 'GET', null, $apiKey);
            
            if (isset($smartyResponse['items']) && is_array($smartyResponse['items'])) {
                // Aggiorna la cache
                if (empty($searchTerm)) {
                    file_put_contents($cacheFile, json_encode($smartyResponse['items']));
                }
                
                // Usa le marche dall'API
                $marche = $smartyResponse['items'];
            }
        }
    }
    
    // Verifica anche nel database locale
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT DISTINCT marca FROM prodotti WHERE marca LIKE ? AND marca <> '' LIMIT 20");
    $stmt->execute(['%' . $searchTerm . '%']);
    $localMarche = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Aggiungi le marche locali se non esistono già nell'elenco
    foreach ($localMarche as $localMarca) {
        $exists = false;
        foreach ($marche as $marca) {
            if (isset($marca['name']) && $marca['name'] === $localMarca) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $marche[] = ['name' => $localMarca];
        }
    }
    
    // Limita il numero di risultati
    $marche = array_slice($marche, 0, 20);
    
    echo json_encode([
        'success' => true,
        'marche' => array_values($marche)
    ]);
} catch (Exception $e) {
    logMessage("Errore nella ricerca delle marche: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}