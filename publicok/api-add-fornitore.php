<?php
/**
 * API per l'aggiunta di un nuovo fornitore
 */
require_once 'init.php';
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

// Ottieni il nome del fornitore
$nome = isset($_POST['nome']) ? sanitizeInput($_POST['nome']) : '';

// Validazione
if (empty($nome)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nome fornitore non specificato'
    ]);
    exit;
}

try {
    // Verifica se il fornitore esiste già nel database
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM fornitori WHERE nome = ?");
    $stmt->execute([$nome]);
    $existingFornitore = $stmt->fetch();
    
    if ($existingFornitore) {
        echo json_encode([
            'success' => true,
            'message' => 'Fornitore già esistente nel database',
            'supplier_id' => $existingFornitore['id'],
            'supplier_name' => $nome
        ]);
        exit;
    }
    
    // Verifica se il fornitore esiste già su Smarty
    $apiKey = getDefaultApiKey();
    if ($apiKey) {
        $response = callSmartyApi('Suppliers/list', 'GET', null, $apiKey);
        
        if (isset($response['items']) && is_array($response['items'])) {
            foreach ($response['items'] as $fornitore) {
                $businessName = isset($fornitore['business_name']) ? $fornitore['business_name'] : 
                                (isset($fornitore['name']) ? $fornitore['name'] : '');
                
                if (strtolower($businessName) === strtolower($nome)) {
                    // Fornitore trovato su Smarty, aggiungiamolo anche al database locale
                    $stmt = $conn->prepare("INSERT INTO fornitori (nome, smarty_id) VALUES (?, ?)");
                    $stmt->execute([$nome, $fornitore['id']]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Fornitore trovato su Smarty e salvato nel database locale',
                        'supplier_id' => $fornitore['id'],
                        'supplier_name' => $businessName
                    ]);
                    
                    // Aggiorna il file di cache dei fornitori
                    $cacheFile = UPLOADS_DIR . '/fornitori_cache.json';
                    if (file_exists($cacheFile)) {
                        $fornitori = json_decode(file_get_contents($cacheFile), true);
                        $fornitori[] = [
                            'id' => $fornitore['id'], 
                            'business_name' => $businessName,
                            'name' => $businessName
                        ];
                        file_put_contents($cacheFile, json_encode($fornitori));
                    }
                    
                    exit;
                }
            }
        }
    
        // Crea il nuovo fornitore su Smarty
        $smartyData = [
            'business_name' => $nome
        ];
        
        $smartyResponse = callSmartyApi('Suppliers/post', 'POST', $smartyData, $apiKey);
        
        if (isset($smartyResponse['id'])) {
            // Salva il fornitore nel database locale
            $stmt = $conn->prepare("INSERT INTO fornitori (nome, smarty_id) VALUES (?, ?)");
            $stmt->execute([$nome, $smartyResponse['id']]);
            $localId = $conn->lastInsertId();
            
            // Aggiorna il file di cache dei fornitori
            $cacheFile = UPLOADS_DIR . '/fornitori_cache.json';
            if (file_exists($cacheFile)) {
                $fornitori = json_decode(file_get_contents($cacheFile), true);
                $fornitori[] = [
                    'id' => $smartyResponse['id'], 
                    'business_name' => $nome,
                    'name' => $nome
                ];
                file_put_contents($cacheFile, json_encode($fornitori));
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Fornitore aggiunto con successo sia a Smarty che al database locale',
                'supplier_id' => $smartyResponse['id'],
                'local_id' => $localId,
                'supplier_name' => $nome
            ]);
        } else {
            // Errore nella creazione del fornitore su Smarty
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella creazione del fornitore su Smarty: ' . (isset($smartyResponse['message']) ? $smartyResponse['message'] : json_encode($smartyResponse))
            ]);
        }
    } else {
        // Non c'è API key, aggiungiamo solo al database locale
        $stmt = $conn->prepare("INSERT INTO fornitori (nome) VALUES (?)");
        $stmt->execute([$nome]);
        $localId = $conn->lastInsertId();
        
        // Aggiorna il file di cache
        $cacheFile = UPLOADS_DIR . '/fornitori_cache.json';
        if (file_exists($cacheFile)) {
            $fornitori = json_decode(file_get_contents($cacheFile), true);
            $fornitori[] = [
                'id' => 'local_' . $localId, 
                'business_name' => $nome,
                'name' => $nome
            ];
            file_put_contents($cacheFile, json_encode($fornitori));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Fornitore aggiunto con successo al database locale',
            'local_id' => $localId,
            'supplier_name' => $nome
        ]);
    }
} catch (Exception $e) {
    logMessage("Errore nell'aggiunta del fornitore: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}