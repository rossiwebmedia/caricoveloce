<?php
require_once 'config.php';
require_once 'functions.php';

// Controlla se l'utente è autenticato
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit;
}

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Gestisci le diverse azioni
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save_product':
        saveProduct();
        break;
    case 'delete_product':
        deleteProduct();
        break;
    case 'batch_save':
        batchSaveProducts();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

/**
 * Salva un singolo prodotto
 */
function saveProduct() {
    try {
        // Recupera i dati del form
        $fornitoreId = isset($_POST['fornitore_id']) ? trim($_POST['fornitore_id']) : '';
        $productId = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
        $marcaId = isset($_POST['marca_id']) ? trim($_POST['marca_id']) : '';
        $modello = isset($_POST['modello']) ? trim($_POST['modello']) : '';
        $genere = isset($_POST['genere']) ? trim($_POST['genere']) : '';
        $stagione = isset($_POST['stagione']) ? trim($_POST['stagione']) : '';
        $quantita = isset($_POST['quantita']) ? intval($_POST['quantita']) : 1;
        $prezzoAcquisto = isset($_POST['prezzo_acquisto']) ? floatval($_POST['prezzo_acquisto']) : 0;
        $prezzoVendita = isset($_POST['prezzo_vendita']) ? floatval($_POST['prezzo_vendita']) : 0;
        
        // Validazione
        if (empty($fornitoreId) || empty($marcaId) || empty($modello) || empty($genere) || empty($stagione)) {
            echo json_encode(['success' => false, 'message' => 'Dati incompleti']);
            return;
        }
        
        if ($prezzoAcquisto <= 0) {
            echo json_encode(['success' => false, 'message' => 'Prezzo di acquisto non valido']);
            return;
        }
        
        // Ottieni la connessione al database
        $conn = getDbConnection();
        
        // Ottieni marca, fornitore, ecc. per riferimento nel DB
        $stmt = $conn->prepare("SELECT name FROM cache_marche WHERE brand_id = ?");
        $stmt->execute([$marcaId]);
        $marca = $stmt->fetchColumn() ?: '';
        
        $stmt = $conn->prepare("SELECT business_name, nome FROM cache_fornitori WHERE id = ?");
        $stmt->execute([$fornitoreId]);
        $fornitoreData = $stmt->fetch();
        $fornitoreNome = $fornitoreData['business_name'] ?: $fornitoreData['nome'] ?: 'Fornitore sconosciuto';
        
        // Genera uno SKU
        $sku = generaSKU($modello);
        
        // Ottieni un codice EAN se necessario
        $ean = '';
        if (empty($productId)) {
            $ean = getNextEAN();
        }
        
        // Se è un aggiornamento prodotto
        if (!empty($productId)) {
            $stmt = $conn->prepare("
                UPDATE prodotti SET 
                marca = ?, marca_id = ?, modello = ?, genere = ?, stagione = ?, 
                prezzo_acquisto = ?, prezzo_vendita = ?, quantita = ?, 
                aggiornato_il = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $marca, $marcaId, $modello, $genere, $stagione,
                $prezzoAcquisto, $prezzoVendita, $quantita,
                $productId
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prodotto aggiornato con successo',
                    'product_id' => $productId
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Errore nell\'aggiornamento del prodotto'
                ]);
            }
        } else {
            // Inserisci un nuovo prodotto
            $stmt = $conn->prepare("
                INSERT INTO prodotti (
                    fornitore_id, fornitore, marca_id, marca, modello, sku, ean,
                    genere, stagione, prezzo_acquisto, prezzo_vendita, 
                    quantita, stato, utente_id, creato_il
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'attivo', ?, NOW())
            ");
            
            $result = $stmt->execute([
                $fornitoreId, $fornitoreNome, $marcaId, $marca, $modello, $sku, $ean,
                $genere, $stagione, $prezzoAcquisto, $prezzoVendita,
                $quantita, $_SESSION['user_id']
            ]);
            
            if ($result) {
                $newProductId = $conn->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Prodotto salvato con successo',
                    'product_id' => $newProductId,
                    'ean' => $ean
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Errore nel salvataggio del prodotto'
                ]);
            }
        }
    } catch (Exception $e) {
        logMessage("Errore nel salvare prodotto: " . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ]);
    }
}

/**
 * Elimina un prodotto
 */
function deleteProduct() {
    try {
        $productId = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
        
        if (empty($productId)) {
            echo json_encode(['success' => false, 'message' => 'ID prodotto non valido']);
            return;
        }
        
        $conn = getDbConnection();
        $stmt = $conn->prepare("DELETE FROM prodotti WHERE id = ?");
        $result = $stmt->execute([$productId]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Prodotto eliminato con successo'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Errore nell\'eliminazione del prodotto'
            ]);
        }
    } catch (Exception $e) {
        logMessage("Errore nell'eliminare il prodotto: " . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ]);
    }
}

/**
 * Salva più prodotti in batch
 */
function batchSaveProducts() {
    try {
        $fornitoreId = isset($_POST['fornitore_id']) ? trim($_POST['fornitore_id']) : '';
        $products = isset($_POST['products']) ? json_decode($_POST['products'], true) : [];
        
        if (empty($fornitoreId) || empty($products)) {
            echo json_encode(['success' => false, 'message' => 'Dati incompleti']);
            return;
        }
        
        $conn = getDbConnection();
        $conn->beginTransaction();
        
        $savedCount = 0;
        $errors = [];
        
        foreach ($products as $product) {
            // Prepara i parametri per il salvataggio individuale
            $_POST['fornitore_id'] = $fornitoreId;
            $_POST['product_id'] = $product['product_id'] ?? '';
            $_POST['marca_id'] = $product['marca_id'] ?? '';
            $_POST['modello'] = $product['modello'] ?? '';
            $_POST['genere'] = $product['genere'] ?? '';
            $_POST['stagione'] = $product['stagione'] ?? '';
            $_POST['quantita'] = $product['quantita'] ?? 1;
            $_POST['prezzo_acquisto'] = $product['prezzo_acquisto'] ?? 0;
            $_POST['prezzo_vendita'] = $product['prezzo_vendita'] ?? 0;
            
            // Buffer output per catturare la risposta
            ob_start();
            saveProduct();
            $response = ob_get_clean();
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $savedCount++;
            } else {
                $errors[] = $result['message'] ?? 'Errore sconosciuto';
            }
        }
        
        if (count($errors) === 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "$savedCount prodotti salvati con successo"
            ]);
        } else {
            $conn->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Si sono verificati errori',
                'errors' => $errors
            ]);
        }
    } catch (Exception $e) {
        $conn->rollBack();
        logMessage("Errore nel salvataggio batch: " . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ]);
    }
}