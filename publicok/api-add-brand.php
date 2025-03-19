<?php
/**
 * API per l'aggiunta di una nuova marca
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

// Ottieni il nome della marca
$nome = isset($_POST['nome']) ? sanitizeInput($_POST['nome']) : '';

// Validazione
if (empty($nome)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nome marca non specificato'
    ]);
    exit;
}

try {
    // Verifica se la marca esiste già nel database
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM marche WHERE nome = ?");
    $stmt->execute([$nome]);
    $existingMarca = $stmt->fetch();
    
    if ($existingMarca) {
        echo json_encode([
            'success' => true,
            'message' => 'Marca già esistente nel database',
            'brand_id' => $existingMarca['id'],
            'brand_name' => $nome
        ]);
        exit;
    }
    
    // Verifica se la marca esiste già su Smarty
    $apiKey = getDefaultApiKey();
    if ($apiKey) {
        $response = callSmartyApi('Brands/list', 'GET', null, $apiKey);
        
        if (isset($response['items']) && is_array($response['items'])) {
            foreach ($response['items'] as $marca) {
                if (strtolower($marca['name']) === strtolower($nome)) {
                    // Marca trovata su Smarty, aggiungiamola anche al database locale
                    $stmt = $conn->prepare("INSERT INTO marche (nome, smarty_id) VALUES (?, ?)");
                    $stmt->execute([$nome, $marca['id']]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Marca trovata su Smarty e salvata nel database locale',
                        'brand_id' => $marca['id'],
                        'brand_name' => $marca['name']
                    ]);
                    
                    // Aggiorna il file di cache delle marche
                    $cacheFile = UPLOADS_DIR . '/marche_cache.json';
                    if (file_exists($cacheFile)) {
                        $marche = json_decode(file_get_contents($cacheFile), true);
                        if (!in_array(['name' => $nome], $marche)) {
                            $marche[] = ['name' => $nome, 'id' => $marca['id']];
                            file_put_contents($cacheFile, json_encode($marche));
                        }
                    }
                    
                    exit;
                }
            }
        }
    
        // Crea la nuova marca su Smarty
        $smartyData = [
            'name' => $nome
        ];
        
        $smartyResponse = callSmartyApi('Brands/post', 'POST', $smartyData, $apiKey);
        
        if (isset($smartyResponse['id'])) {
            // Salva la marca nel database locale
            $stmt = $conn->prepare("INSERT INTO marche (nome, smarty_id) VALUES (?, ?)");
            $stmt->execute([$nome, $smartyResponse['id']]);
            $localId = $conn->lastInsertId();
            
            // Aggiorna il file di cache delle marche
            $cacheFile = UPLOADS_DIR . '/marche_cache.json';
            if (file_exists($cacheFile)) {
                $marche = json_decode(file_get_contents($cacheFile), true);
                $marche[] = ['name' => $nome, 'id' => $smartyResponse['id']];
                file_put_contents($cacheFile, json_encode($marche));
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Marca aggiunta con successo sia a Smarty che al database locale',
                'brand_id' => $smartyResponse['id'],
                'local_id' => $localId,
                'brand_name' => $nome
            ]);
        } else {
            // Errore nella creazione della marca su Smarty
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella creazione della marca su Smarty: ' . (isset($smartyResponse['message']) ? $smartyResponse['message'] : json_encode($smartyResponse))
            ]);
        }
    } else {
        // Non c'è API key, aggiungiamo solo al database locale
        $stmt = $conn->prepare("INSERT INTO marche (nome) VALUES (?)");
        $stmt->execute([$nome]);
        $localId = $conn->lastInsertId();
        
        // Aggiorna il file di cache
        $cacheFile = UPLOADS_DIR . '/marche_cache.json';
        if (file_exists($cacheFile)) {
            $marche = json_decode(file_get_contents($cacheFile), true);
            $marche[] = ['name' => $nome];
            file_put_contents($cacheFile, json_encode($marche));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Marca aggiunta con successo al database locale',
            'local_id' => $localId,
            'brand_name' => $nome
        ]);
    }
} catch (Exception $e) {
    logMessage("Errore nell'aggiunta della marca: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}