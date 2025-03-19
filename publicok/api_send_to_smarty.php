<?php
/**
 * API per l'invio di prodotti a Smarty - Versione corretta
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
$data = json_decode($input, true);

// Debug - registra l'input completo per diagnostica
error_log("Input ricevuto da api_send_to_smarty.php: " . $input);

// Verifica che i dati siano validi
if (!$data || !isset($data['variants']) || !is_array($data['variants']) || empty($data['variants'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dati non validi o mancanti'
    ]);
    exit;
}

// Estrai le varianti
$variants = $data['variants'];
$isSimple = isset($data['is_simple']) ? (bool)$data['is_simple'] : false;

// Assicurati che tutti i campi obbligatori siano presenti nelle varianti
foreach ($variants as $index => $variant) {
    // Per il prodotto principale (prima variante), lo SKU è obbligatorio
    if ($index === 0 && empty($variant['sku'])) {
        echo json_encode([
            'success' => false,
            'message' => 'SKU mancante per il prodotto principale'
        ]);
        exit;
    }
    
    // Per le varianti, verifica che abbiano un parent_sku
    if ($index > 0 && empty($variant['parent_sku'])) {
        echo json_encode([
            'success' => false,
            'message' => 'parent_sku mancante per una variante'
        ]);
        exit;
    }
    
    // Assicurati che il titolo non sia vuoto
    if (empty($variant['titolo']) && isset($variant['sku'])) {
        // Se il titolo è vuoto ma lo SKU è presente, crea un titolo
        $variants[$index]['titolo'] = isset($data['marca']) ? $data['marca'] . ' ' . $variant['sku'] : $variant['sku'];
    }
}

// Connessione al database
$conn = getDbConnection();
$conn->beginTransaction();

try {
    // Primo passo: salva tutte le varianti nel database locale
    $savedVariants = [];
    $smartyProductId = null;
    $variantsCreated = 0;
    
    // Verifica se è un prodotto semplice o con varianti
    if ($isSimple) {
        // Prodotto semplice (senza varianti)
        $variant = $variants[0];
        
        // Inserisci il prodotto nel database locale
        $stmt = $conn->prepare("
            INSERT INTO prodotti (
                sku, parent_sku, titolo, tipologia, genere, stagione, taglia, colore, ean,
                prezzo_acquisto, prezzo_vendita, aliquota_iva, fornitore, marca, stato, utente_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bozza', ?
            )
        ");
        
        $stmt->execute([
            $variant['sku'],
            null, // parent_sku è null per il prodotto principale
            $variant['titolo'],
            $variant['tipologia'] ?? $data['tipologia'],
            $variant['genere'] ?? $data['genere'],
            $variant['stagione'] ?? $data['stagione'],
            $variant['taglia'] ?? '',
            $variant['colore'] ?? '',
            $variant['ean'] ?? $data['ean'] ?? '',
            $variant['prezzo_acquisto'] ?? $data['prezzo_acquisto'],
            $variant['prezzo_vendita'] ?? $data['prezzo_vendita'],
            $variant['aliquota_iva'] ?? $data['aliquota_iva'],
            $variant['fornitore'] ?? $data['fornitore'],
            $variant['marca'] ?? $data['marca'],
            $_SESSION['user_id']
        ]);
        
        $productId = $conn->lastInsertId();
        $variant['id'] = $productId;
        $savedVariants[] = $variant;
        
        // Crea il prodotto su Smarty
        $smartyData = [
            'sku' => $variant['sku'],
            'ean' => $variant['ean'] ?? $data['ean'] ?? '',
            'title' => $variant['titolo'],
            'description' => $variant['descrizione'] ?? '',
            'description_short' => $variant['descrizione_breve'] ?? '',
            'price' => floatval($variant['prezzo_vendita'] ?? $data['prezzo_vendita']),
            'purchase_price' => floatval($variant['prezzo_acquisto'] ?? $data['prezzo_acquisto']),
            'tax_id' => intval($variant['aliquota_iva'] ?? $data['aliquota_iva']),
            'brand' => $variant['marca'] ?? $data['marca'],
            'stock' => 0
        ];
        
        // Aggiungi il fornitore se presente
        if (!empty($variant['fornitore']) || !empty($data['fornitore'])) {
            $smartyData['supplier'] = [($variant['fornitore'] ?? $data['fornitore'])];
        }
        
// Invia i dati a Smarty
$smartyResponse = callSmartyApi('Products', 'post', $smartyData);        
        // Verifica la risposta
        if (isset($smartyResponse['id'])) {
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
                ) VALUES (?, 'creazione', 1, ?, ?)
            ");
            $stmt->execute([
                $productId,
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            $variantsCreated = 1;
        } else {
            // Registra l'errore
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET stato = 'errore', messaggio_errore = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($smartyResponse),
                $productId
            ]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, 'creazione', 0, ?, ?)
            ");
            $stmt->execute([
                $productId,
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            throw new Exception('Errore nella creazione del prodotto su Smarty: ' . json_encode($smartyResponse));
        }
    } else {
        // Prodotto con varianti - Identifica il prodotto principale (parent)
        $parentVariant = $variants[0];
        $parentSku = $parentVariant['sku'];
        
        // Inserisci il prodotto principale nel database locale
        $stmt = $conn->prepare("
            INSERT INTO prodotti (
                sku, parent_sku, titolo, tipologia, genere, stagione, taglia, colore, ean,
                prezzo_acquisto, prezzo_vendita, aliquota_iva, fornitore, marca, stato, utente_id
            ) VALUES (
                ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bozza', ?
            )
        ");
        
        $stmt->execute([
            $parentSku,
            $parentVariant['titolo'],
            $parentVariant['tipologia'] ?? $data['tipologia'],
            $parentVariant['genere'] ?? $data['genere'],
            $parentVariant['stagione'] ?? $data['stagione'],
            $parentVariant['taglia'] ?? '',
            $parentVariant['colore'] ?? '',
            $parentVariant['ean'] ?? '',
            $parentVariant['prezzo_acquisto'] ?? $data['prezzo_acquisto'],
            $parentVariant['prezzo_vendita'] ?? $data['prezzo_vendita'],
            $parentVariant['aliquota_iva'] ?? $data['aliquota_iva'],
            $parentVariant['fornitore'] ?? $data['fornitore'],
            $parentVariant['marca'] ?? $data['marca'],
            $_SESSION['user_id']
        ]);
        
        $parentProductId = $conn->lastInsertId();
        $parentVariant['id'] = $parentProductId;
        $savedVariants[] = $parentVariant;
        
        // Crea il prodotto principale su Smarty
        $smartyData = [
            'sku' => $parentSku,
            'title' => $parentVariant['titolo'],
            'description' => $parentVariant['descrizione'] ?? '',
            'description_short' => $parentVariant['descrizione_breve'] ?? '',
            'price' => floatval($parentVariant['prezzo_vendita'] ?? $data['prezzo_vendita']),
            'purchase_price' => floatval($parentVariant['prezzo_acquisto'] ?? $data['prezzo_acquisto']),
            'tax_id' => intval($parentVariant['aliquota_iva'] ?? $data['aliquota_iva']),
            'brand' => $parentVariant['marca'] ?? $data['marca'],
            'stock' => 0
        ];
        
        // Aggiungi il fornitore se presente
        if (!empty($parentVariant['fornitore']) || !empty($data['fornitore'])) {
            $smartyData['supplier'] = [($parentVariant['fornitore'] ?? $data['fornitore'])];
        }
        
        // Invia i dati a Smarty
$smartyResponse = callSmartyApi('Products', 'post', $smartyData);        
        // Verifica la risposta
        if (isset($smartyResponse['id'])) {
            $smartyProductId = $smartyResponse['id'];
            
            // Aggiorna il prodotto principale nel database locale
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                WHERE id = ?
            ");
            $stmt->execute([$smartyProductId, $parentProductId]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, 'creazione', 1, ?, ?)
            ");
            $stmt->execute([
                $parentProductId,
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            // Ora crea tutte le varianti
            foreach ($variants as $index => $variant) {
                // Salta il prodotto principale
                if ($index === 0) {
                    continue;
                }
                
                // Inserisci la variante nel database locale
                $stmt = $conn->prepare("
                    INSERT INTO prodotti (
                        sku, parent_sku, titolo, tipologia, genere, stagione, taglia, colore, ean,
                        prezzo_acquisto, prezzo_vendita, aliquota_iva, fornitore, marca, stato, utente_id
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bozza', ?
                    )
                ");
                
                $stmt->execute([
                    $variant['sku'],
                    $parentSku,
                    $variant['titolo'],
                    $variant['tipologia'] ?? $data['tipologia'],
                    $variant['genere'] ?? $data['genere'],
                    $variant['stagione'] ?? $data['stagione'],
                    $variant['taglia'] ?? '',
                    $variant['colore'] ?? '',
                    $variant['ean'] ?? '',
                    $variant['prezzo_acquisto'] ?? $data['prezzo_acquisto'],
                    $variant['prezzo_vendita'] ?? $data['prezzo_vendita'],
                    $variant['aliquota_iva'] ?? $data['aliquota_iva'],
                    $variant['fornitore'] ?? $data['fornitore'],
                    $variant['marca'] ?? $data['marca'],
                    $_SESSION['user_id']
                ]);
                
                $variantId = $conn->lastInsertId();
                $variant['id'] = $variantId;
                $savedVariants[] = $variant;
                
                // Prepara i dati della variante per Smarty
                $smartyVariantData = [
                    'product_id' => $smartyProductId,
                    'sku' => $variant['sku'],
                    'ean' => $variant['ean'] ?? '',
                    'price' => floatval($variant['prezzo_vendita'] ?? $data['prezzo_vendita']),
                    'purchase_price' => floatval($variant['prezzo_acquisto'] ?? $data['prezzo_acquisto']),
                    'tax_id' => intval($variant['aliquota_iva'] ?? $data['aliquota_iva']),
                    'stock' => 0,
                    'detail' => []
                ];
                
                // Aggiungi taglia e colore se presenti
                if (!empty($variant['taglia'])) {
                    $smartyVariantData['detail'][] = [
                        'name' => 'Taglia',
                        'value' => $variant['taglia']
                    ];
                }
                
                if (!empty($variant['colore'])) {
                    $smartyVariantData['detail'][] = [
                        'name' => 'Colore',
                        'value' => $variant['colore']
                    ];
                }
                
                // Invia la variante a Smarty
$smartyVariantResponse = callSmartyApi('Variations', 'post', $smartyVariantData);
                
                // Verifica la risposta
                if (isset($smartyVariantResponse['id'])) {
                    // Aggiorna la variante nel database locale
                    $stmt = $conn->prepare("
                        UPDATE prodotti 
                        SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$smartyVariantResponse['id'], $variantId]);
                    
                    // Registra il log
                    $stmt = $conn->prepare("
                        INSERT INTO log_sincronizzazione (
                            prodotto_id, azione, riuscito, risposta_api, utente_id
                        ) VALUES (?, 'creazione', 1, ?, ?)
                    ");
                    $stmt->execute([
                        $variantId,
                        json_encode($smartyVariantResponse),
                        $_SESSION['user_id']
                    ]);
                    
                    $variantsCreated++;
                } else {
                    // Registra l'errore
                    $stmt = $conn->prepare("
                        UPDATE prodotti 
                        SET stato = 'errore', messaggio_errore = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        json_encode($smartyVariantResponse),
                        $variantId
                    ]);
                    
                    // Registra il log
                    $stmt = $conn->prepare("
                        INSERT INTO log_sincronizzazione (
                            prodotto_id, azione, riuscito, risposta_api, utente_id
                        ) VALUES (?, 'creazione', 0, ?, ?)
                    ");
                    $stmt->execute([
                        $variantId,
                        json_encode($smartyVariantResponse),
                        $_SESSION['user_id']
                    ]);
                    
                    logMessage("Errore nella creazione della variante su Smarty: " . json_encode($smartyVariantResponse), 'ERROR');
                }
            }
        } else {
            // Registra l'errore
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET stato = 'errore', messaggio_errore = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($smartyResponse),
                $parentProductId
            ]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, 'creazione', 0, ?, ?)
            ");
            $stmt->execute([
                $parentProductId,
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            throw new Exception('Errore nella creazione del prodotto su Smarty: ' . json_encode($smartyResponse));
        }
    }
    
    // Commit della transazione
    $conn->commit();
    
    // Restituisci il risultato
    echo json_encode([
        'success' => true,
        'message' => 'Prodotto inviato con successo a Smarty',
        'product_id' => $smartyProductId,
        'variants_count' => $variantsCreated,
        'saved_variants' => count($savedVariants)
    ]);
} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollBack();
    
    logMessage("Errore nell'invio a Smarty: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}