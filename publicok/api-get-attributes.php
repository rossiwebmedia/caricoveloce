<?php
/**
 * API per ottenere gli attributi (taglie e colori) da Smarty
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

// Ottieni il tipo di attributi richiesto
$tipo = isset($_GET['tipo']) ? sanitizeInput($_GET['tipo']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

if (!in_array($tipo, ['taglia', 'colore'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Tipo di attributo non valido'
    ]);
    exit;
}

// Restituisci i dati dal file di cache o dal database
$attributi = [];

try {
    // Connessione al database
    $conn = getDbConnection();
    
    // Prima verifica se ci sono dati nella tabella locale
    $tabella = ($tipo === 'taglia') ? 'taglie' : 'colori';
    $stmt = $conn->prepare("SELECT nome FROM $tabella WHERE nome LIKE ? ORDER BY nome ASC");
    $stmt->execute(["%$search%"]);
    $risultatiDB = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($risultatiDB)) {
        $attributi = $risultatiDB;
    } else {
        // Nessun dato nel database locale, prova dal file di cache
        $cacheFile = UPLOADS_DIR . "/{$tipo}_cache.json";
        
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            
            if (!empty($cache)) {
                foreach ($cache as $item) {
                    $nome = $item['name'] ?? $item['value'] ?? null;
                    if ($nome && (empty($search) || stripos($nome, $search) !== false)) {
                        $attributi[] = $nome;
                    }
                }
            }
        }
        
        // Se ancora non abbiamo dati, vediamo se possiamo ottenerli dall'API Smarty
        if (empty($attributi)) {
            $apiKey = getDefaultApiKey();
            
            if ($apiKey) {
                // Per le taglie e i colori, dobbiamo estrarre gli attributi dai prodotti
                // Questo non è efficiente ma può essere un modo per costruire il nostro database di attributi
                $response = callSmartyApi('Products/list', 'GET', null, $apiKey);
                
                if (isset($response['items']) && is_array($response['items'])) {
                    // Estrai gli attributi dalle varianti dei prodotti
                    $attributiTrovati = [];
                    
                    foreach ($response['items'] as $product) {
                        if (isset($product['variations']) && is_array($product['variations'])) {
                            foreach ($product['variations'] as $variation) {
                                if (isset($variation['detail']) && is_array($variation['detail'])) {
                                    foreach ($variation['detail'] as $detail) {
                                        $attributoNome = $detail['name'] ?? '';
                                        $attributoValore = $detail['value'] ?? '';
                                        
                                        if (($tipo === 'taglia' && strtolower($attributoNome) === 'taglia') ||
                                            ($tipo === 'colore' && strtolower($attributoNome) === 'colore')) {
                                            if (!empty($attributoValore) && !in_array($attributoValore, $attributiTrovati) &&
                                                (empty($search) || stripos($attributoValore, $search) !== false)) {
                                                $attributiTrovati[] = $attributoValore;
                                                
                                                // Aggiungi l'attributo al database locale
                                                $stmt = $conn->prepare("INSERT IGNORE INTO $tabella (nome) VALUES (?)");
                                                $stmt->execute([$attributoValore]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Ordina gli attributi trovati in modo alfabetico
                    sort($attributiTrovati);
                    $attributi = $attributiTrovati;
                    
                    // Salva gli attributi in un file di cache
                    if (!empty($attributiTrovati)) {
                        $cacheData = [];
                        foreach ($attributiTrovati as $valore) {
                            $cacheData[] = ['value' => $valore, 'name' => $valore];
                        }
                        file_put_contents($cacheFile, json_encode($cacheData));
                    }
                }
            }
        }
    }
    
    // Se ancora non abbiamo nulla, restituisci un insieme di valori predefiniti
    if (empty($attributi)) {
        if ($tipo === 'taglia') {
            $attributi = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'];
        } elseif ($tipo === 'colore') {
            $attributi = ['Nero', 'Bianco', 'Rosso', 'Blu', 'Verde', 'Giallo', 'Arancione', 'Viola', 'Rosa', 'Marrone', 'Grigio', 'Beige'];
        }
        
        // Filtra per la ricerca
        if (!empty($search)) {
            $attributi = array_filter($attributi, function($attributo) use ($search) {
                return stripos($attributo, $search) !== false;
            });
        }
    }
    
    // Rimuovi eventuali duplicati e ordina
    $attributi = array_unique($attributi);
    sort($attributi);
    
    // Restituisci il risultato
    echo json_encode([
        'success' => true,
        'tipo' => $tipo,
        'attributi' => array_values($attributi)
    ]);
} catch (Exception $e) {
    logMessage("Errore nel recupero degli attributi: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}