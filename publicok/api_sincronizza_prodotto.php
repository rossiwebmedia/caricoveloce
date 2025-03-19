<?php
/**
 * API per sincronizzare un prodotto con Smarty
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

// Ottieni l'ID del prodotto
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID prodotto non valido'
    ]);
    exit;
}

// Connessione al database
$conn = getDbConnection();

try {
    // Ottieni le informazioni sul prodotto
    $stmt = $conn->prepare("SELECT * FROM prodotti WHERE id = ?");
    $stmt->execute([$id]);
    $prodotto = $stmt->fetch();
    
    if (!$prodotto) {
        echo json_encode([
            'success' => false,
            'message' => 'Prodotto non trovato'
        ]);
        exit;
    }
    
    // Verifica se il prodotto è già pubblicato
    if ($prodotto['stato'] === 'pubblicato') {
        echo json_encode([
            'success' => true,
            'message' => 'Prodotto già sincronizzato con Smarty',
            'smarty_id' => $prodotto['smarty_id']
        ]);
        exit;
    }
    
    // Verifica se è un prodotto parent o una variante
    $isParent = empty($prodotto['parent_sku']);
    
    if ($isParent) {
        // È un prodotto parent, dobbiamo sincronizzare il prodotto e le sue varianti
        
        // Verifica se ci sono varianti
        $stmt = $conn->prepare("SELECT * FROM prodotti WHERE parent_sku = ?");
        $stmt->execute([$prodotto['sku']]);
        $varianti = $stmt->fetchAll();
        
        // Crea/aggiorna il prodotto su Smarty
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
        
        // Ottieni l'API key
        $apiKey = getDefaultApiKey();
        if (empty($apiKey)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nessuna API key configurata'
            ]);
            exit;
        }
        
        // Se il prodotto ha già uno smarty_id, usa PUT invece di POST
        if (!empty($prodotto['smarty_id'])) {
            $smartyResponse = callSmartyApi('Products/' . $prodotto['smarty_id'], 'PUT', $smartyData, $apiKey);
        } else {
            $smartyResponse = callSmartyApi('Products', 'post', $smartyData, $apiKey);
        }
        
        // Verifica la risposta
        if (isset($smartyResponse['id'])) {
            $smartyProductId = $smartyResponse['id'];
            
            // Aggiorna il prodotto nel database locale
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                WHERE id = ?
            ");
            $stmt->execute([$smartyProductId, $id]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $id,
                !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            // Sincronizza anche le varianti
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
                
                // Se la variante ha già uno smarty_id, usa PUT invece di POST
                if (!empty($variante['smarty_id'])) {
                    $smartyVariantResponse = callSmartyApi('Variations/' . $variante['smarty_id'], 'PUT', $smartyVariantData, $apiKey);
                } else {
                    $smartyVariantResponse = callSmartyApi('Variations', 'post', $smartyVariantData, $apiKey);
                }
                
                // Verifica la risposta
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
                        'id' => $variante['id'],
                        'sku' => $variante['sku'],
                        'success' => true,
                        'smarty_id' => $smartyVariantResponse['id']
                    ];
                } else {
                    // Registra l'errore
                    $stmt = $conn->prepare("
                        UPDATE prodotti 
                        SET stato = 'errore', messaggio_errore = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        json_encode($smartyVariantResponse),
                        $variante['id']
                    ]);
                    
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
                        'id' => $variante['id'],
                        'sku' => $variante['sku'],
                        'success' => false,
                        'message' => 'Errore nella sincronizzazione: ' . json_encode($smartyVariantResponse)
                    ];
                }
            }
            
            // Restituisci il risultato con tutte le varianti
            echo json_encode([
                'success' => true,
                'message' => 'Prodotto sincronizzato con successo',
                'smarty_id' => $smartyProductId,
                'varianti' => $variantiRisultato
            ]);
        } else {
            // Registra l'errore
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET stato = 'errore', messaggio_errore = ?
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($smartyResponse),
                $id
            ]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, ?, 0, ?, ?)
            ");
            $stmt->execute([
                $id,
                !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella sincronizzazione: ' . json_encode($smartyResponse)
            ]);
        }
    } else {
        // È una variante, dobbiamo sincronizzarla con il prodotto parent
        
        // Verifica se il parent è stato già sincronizzato
        $stmt = $conn->prepare("SELECT id, smarty_id FROM prodotti WHERE sku = ?");
        $stmt->execute([$prodotto['parent_sku']]);
        $parent = $stmt->fetch();
        
        if (!$parent || empty($parent['smarty_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Il prodotto parent non è stato ancora sincronizzato. Sincronizza prima il prodotto parent.'
            ]);
            exit;
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
        
        // Ottieni l'API key
        $apiKey = getDefaultApiKey();
        if (empty($apiKey)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nessuna API key configurata'
            ]);
            exit;
        }
        
        // Se la variante ha già uno smarty_id, usa PUT invece di POST
        if (!empty($prodotto['smarty_id'])) {
            $smartyResponse = callSmartyApi('Variations/' . $prodotto['smarty_id'], 'PUT', $smartyVariantData, $apiKey);
        } else {
            $smartyResponse = callSmartyApi('Variations/post', 'POST', $smartyVariantData, $apiKey);
        }
        
        // Verifica la risposta
        if (isset($smartyResponse['id'])) {
            // Aggiorna la variante nel database locale
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET smarty_id = ?, stato = 'pubblicato', messaggio_errore = NULL
                WHERE id = ?
            ");
            $stmt->execute([$smartyResponse['id'], $id]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $id,
                !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Variante sincronizzata con successo',
                'smarty_id' => $smartyResponse['id']
            ]);
        } else {
            // Registra l'errore
            $stmt = $conn->prepare("
                UPDATE prodotti 
                SET stato = 'errore', messaggio_errore = ?
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($smartyResponse),
                $id
            ]);
            
            // Registra il log
            $stmt = $conn->prepare("
                INSERT INTO log_sincronizzazione (
                    prodotto_id, azione, riuscito, risposta_api, utente_id
                ) VALUES (?, ?, 0, ?, ?)
            ");
            $stmt->execute([
                $id,
                !empty($prodotto['smarty_id']) ? 'aggiornamento' : 'creazione',
                json_encode($smartyResponse),
                $_SESSION['user_id']
            ]);
            
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella sincronizzazione della variante: ' . json_encode($smartyResponse)
            ]);
        }
    }
} catch (Exception $e) {
    logMessage("Errore nella sincronizzazione del prodotto: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}