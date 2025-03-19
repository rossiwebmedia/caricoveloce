<?php
/**
 * API per sincronizzare più prodotti con Smarty in base ai filtri
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

// Ottieni i dati JSON dalla richiesta
$input = file_get_contents('php://input');
$filters = json_decode($input, true) ?: [];

// Costruisci la query per ottenere i prodotti in base ai filtri
$filterSql = '';
$filterParams = [];

// Filtro per stato - se non c'è un filtro, prendiamo solo quelli che non sono pubblicati
if (!empty($filters['stato'])) {
    $filterSql .= ' AND p.stato = ?';
    $filterParams[] = $filters['stato'];
} else {
    $filterSql .= ' AND p.stato <> ?';
    $filterParams[] = 'pubblicato';
}

// Filtro per tipologia
if (!empty($filters['tipologia'])) {
    $filterSql .= ' AND p.tipologia = ?';
    $filterParams[] = $filters['tipologia'];
}

// Filtro per solo prodotti parent
if (!empty($filters['solo_parent'])) {
    $filterSql .= ' AND p.parent_sku IS NULL';
}

// Filtro per ricerca testo
if (!empty($filters['search'])) {
    $filterSql .= ' AND (p.sku LIKE ? OR p.titolo LIKE ? OR p.ean LIKE ?)';
    $searchParam = "%{$filters['search']}%";
    $filterParams[] = $searchParam;
    $filterParams[] = $searchParam;
    $filterParams[] = $searchParam;
}

try {
    // Connessione al database
    $conn = getDbConnection();
    
    // Ottieni i prodotti da sincronizzare
    $sql = "SELECT p.id FROM prodotti p WHERE 1=1" . $filterSql . " ORDER BY p.creato_il DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    
    foreach ($filterParams as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($products)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nessun prodotto trovato con i filtri specificati'
        ]);
        exit;
    }
    
    $successCount = 0;
    $errorCount = 0;
    $results = [];
    
    // Sincronizza ogni prodotto
    foreach ($products as $productId) {
        $result = sincronizzaProdotto($productId);
        
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
        
        $results[] = $result;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Sincronizzazione completata per " . count($products) . " prodotti",
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'results' => $results
    ]);
} catch (Exception $e) {
    logMessage("Errore nella sincronizzazione multipla: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}

/**
 * Sincronizza un singolo prodotto
 * 
 * @param int $productId ID del prodotto da sincronizzare
 * @return array Risultato dell'operazione
 */
function sincronizzaProdotto($productId) {
    // Utilizzo la stessa logica presente in api_sincronizza_prodotto.php ma in versione semplificata
    try {
        $conn = getDbConnection();
        
        // Ottieni le informazioni sul prodotto
        $stmt = $conn->prepare("SELECT * FROM prodotti WHERE id = ?");
        $stmt->execute([$productId]);
        $prodotto = $stmt->fetch();
        
        if (!$prodotto) {
            return [
                'success' => false,
                'product_id' => $productId,
                'message' => 'Prodotto non trovato'
            ];
        }
        
        // Se il prodotto è già pubblicato, salta
        if ($prodotto['stato'] === 'pubblicato') {
            return [
                'success' => true,
                'product_id' => $productId,
                'sku' => $prodotto['sku'],
                'message' => 'Prodotto già sincronizzato'
            ];
        }
        
        // Verifica se è un prodotto parent o una variante
        $isParent = empty($prodotto['parent_sku']);
        
        if ($isParent) {
            // È un prodotto parent, dobbiamo sincronizzare il prodotto e le sue varianti
            // Ottieni l'API key
            $apiKey = getDefaultApiKey();
            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'product_id' => $productId,
                    'message' => 'Nessuna API key configurata'
                ];
            }
            
            // Crea il prodotto su Smarty
            $smartyData = [
                'sku' => $prodotto['sku'],
                'ean' => $prodotto['ean'],
                'title' => $prodotto['titolo'],
                'description' => $prodotto['descrizione'] ?? '',
                'description_short' => $prodotto['descrizione_breve'] ?? '',
                'price' => floatval($prodotto['prezzo_vendita']),
                'purchase_price' => floatval($prodotto['prezzo_acquisto']),
                'tax_id' => $prodotto['aliquota_iva'],
                'brand' => $prodotto['marca'],
                'stock' => 0
            ];
            
            // Aggiungi il fornitore se presente
            if (!empty($prodotto['fornitore'])) {
                $smartyData['supplier'] = [$prodotto['fornitore']];
            }
            
            // Invia i dati a Smarty
            $smartyResponse = callSmartyApi('Products/post', 'POST', $smartyData, $apiKey);
            
            if (!isset($smartyResponse['id'])) {
                // Registra l'errore
                $stmt = $conn->prepare("
                    UPDATE prodotti 
                    SET stato = 'errore', messaggio_errore = ?
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($smartyResponse), $productId]);
                
                return [
                    'success' => false,
                    'product_id' => $productId,
                    'sku' => $prodotto['sku'],
                    'message' => 'Errore nella sincronizzazione: ' . json_encode($smartyResponse)
                ];
            }
            
            $smartyProductId = $smartyResponse['id'];
            
            // Aggiorna il prodotto nel database locale
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                WHERE id = ?
            ");
            $stmt->execute([$smartyProductId, $productId]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $productId,
                !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            // Sincronizza anche le varianti
            $stmt = $conn->prepare("SELECT * FROM prodotti WHERE parent_sku = ? ORDER BY colore, taglia");
            $stmt->execute([$prodotto['sku']]);
            $varianti = $stmt->fetchAll();
            
            $variantiRisultato = [];
            
            foreach ($varianti as $variante) {
                // Prepara i dati della variante per Smarty
                $smartyVariantData = [
                    'product_id' => $smartyProductId,
                    'sku' => $variante['sku'],
                    'ean' => $variante['ean'],
                    'price' => floatval($variante['prezzo_vendita']),
                    'purchase_price' => floatval($variante['prezzo_acquisto']),
                    'tax_id' => $variante['aliquota_iva'],
                    'stock' => 0,
                    'detail' => []
                ];
                
                // Aggiungi taglia e colore se presenti
                if (!empty($variante['taglia'])) {
                    $smartyVariantData['detail'][] = [
                        'name' => 'Taglia',
                        'value' => $variante['taglia']
                    ];
                }
                
                if (!empty($variante['colore'])) {
                    $smartyVariantData['detail'][] = [
                        'name' => 'Colore',
                        'value' => $variante['colore']
                    ];
                }
                
                // Invia la variante a Smarty
                $smartyVariantResponse = callSmartyApi('Variations/post', 'POST', $smartyVariantData, $apiKey);
                
                if (isset($smartyVariantResponse['id'])) {
                    // Aggiorna la variante nel database locale
                    $stmt = $conn->prepare("
                        UPDATE prodotti 
                        SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$smartyVariantResponse['id'], $variante['id']]);
                    
                    // Registra il log
                    $stmt = $conn->prepare("
                        INSERT INTO log_sincronizzazione (
                            prodotto_id, azione, riuscito, risposta_api, utente_id
                        ) VALUES (?, ?, 1, ?, ?)
                    ");
                    $stmt->execute([
                        $variante['id'],
                        !empty($variante['smarty_id']) ? 'aggiornamento' : 'creazione',
                        json_encode($smartyVariantResponse),
                        $_SESSION['user_id']
                    ]);
                    
                    $variantiRisultato[] = [
                        'success' => true,
                        'id' => $variante['id'],
                        'sku' => $variante['sku']
                    ];
                } else {
                    // Registra l'errore
                    $stmt = $conn->prepare("
                        UPDATE prodotti 
                        SET stato = 'errore', messaggio_errore = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([json_encode($smartyVariantResponse), $variante['id']]);
                    
                    // Registra il log
                    $stmt = $conn->prepare("
                        INSERT INTO log_sincronizzazione (
                            prodotto_id, azione, riuscito, risposta_api, utente_id
                        ) VALUES (?, ?, 0, ?, ?)
                    ");
                    $stmt->execute([
                        $variante['id'],
                        !empty($variante['smarty_id']) ? 'aggiornamento' : 'creazione',
                        json_encode($smartyVariantResponse),
                        $_SESSION['user_id']
                    ]);
                    
                    $variantiRisultato[] = [
                        'success' => false,
                        'id' => $variante['id'],
                        'sku' => $variante['sku'],
                        'message' => 'Errore nella sincronizzazione'
                    ];
                }
            }
            
            return [
                'success' => true,
                'product_id' => $productId,
                'sku' => $prodotto['sku'],
                'smarty_id' => $smartyProductId,
                'message' => 'Prodotto sincronizzato con successo',
                'varianti' => $variantiRisultato
            ];
        } else {
            // È una variante, dobbiamo sincronizzarla con il prodotto parent
            
            // Verifica se il parent è stato già sincronizzato
            $stmt = $conn->prepare("SELECT id, smarty_id FROM prodotti WHERE sku = ?");
            $stmt->execute([$prodotto['parent_sku']]);
            $parent = $stmt->fetch();
            
            if (!$parent || empty($parent['smarty_id'])) {
                return [
                    'success' => false,
                    'product_id' => $productId,
                    'sku' => $prodotto['sku'],
                    'message' => 'Il prodotto parent non è stato ancora sincronizzato'
                ];
            }
            
            // Ottieni l'API key
            $apiKey = getDefaultApiKey();
            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'product_id' => $productId,
                    'message' => 'Nessuna API key configurata'
                ];
            }
            
            // Prepara i dati della variante per Smarty
            $smartyVariantData = [
                'product_id' => $parent['smarty_id'],
                'sku' => $prodotto['sku'],
                'ean' => $prodotto['ean'],
                'price' => floatval($prodotto['prezzo_vendita']),
                'purchase_price' => floatval($prodotto['prezzo_acquisto']),
                'tax_id' => $prodotto['aliquota_iva'],
                'stock' => 0,
                'detail' => []
            ];
            
            // Aggiungi taglia e colore se presenti
            if (!empty($prodotto['taglia'])) {
                $smartyVariantData['detail'][] = [
                    'name' => 'Taglia',
                    'value' => $prodotto['taglia']
                ];
            }
            
            if (!empty($prodotto['colore'])) {
                $smartyVariantData['detail'][] = [
                    'name' => 'Colore',
                    'value' => $prodotto['colore']
                ];
            }
            
            // Invia la variante a Smarty
            $smartyVariantResponse = callSmartyApi('Variations/post', 'POST', $smartyVariantData, $apiKey);
            
            if (isset($smartyVariantResponse['id'])) {
                // Aggiorna la variante nel database locale
                $stmt = $conn->prepare("
                    UPDATE prodotti 
                    SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$smartyVariantResponse['id'], $productId]);
                
                // Registra il log
                $stmt = $conn->prepare("
                    INSERT INTO log_sincronizzazione (
                        prodotto_id, azione, riuscito, risposta_api, utente_id
                    ) VALUES (?, ?, 1, ?, ?)
                ");
                $stmt->execute([
                    $productId,
                    !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                    json_encode($smartyVariantResponse),
                    $_SESSION['user_id']
                ]);
                
                return [
                    'success' => true,
                    'product_id' => $productId,
                    'sku' => $prodotto['sku'],
                    'smarty_id' => $smartyVariantResponse['id'],
                    'message' => 'Variante sincronizzata con successo'
                ];
            } else {
                // Registra l'errore
                $stmt = $conn->prepare("
                    UPDATE prodotti 
                    SET stato = 'errore', messaggio_errore = ?
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($smartyVariantResponse), $productId]);
                
                // Registra il log
                $stmt = $conn->prepare("
                    INSERT INTO log_sincronizzazione (
                        prodotto_id, azione, riuscito, risposta_api, utente_id
                    ) VALUES (?, ?, 0, ?, ?)
                ");
                $stmt->execute([
                    $productId,
                    !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                    json_encode($smartyVariantResponse),
                    $_SESSION['user_id']
                ]);
                
                return [
                    'success' => false,
                    'product_id' => $productId,
                    'sku' => $prodotto['sku'],
                    'message' => 'Errore nella sincronizzazione della variante: ' . json_encode($smartyVariantResponse)
                ];
            }
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'product_id' => $productId,
            'message' => 'Errore: ' . $e->getMessage()
        ];
    }
}