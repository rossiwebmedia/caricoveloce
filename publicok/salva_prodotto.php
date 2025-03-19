<?php
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia autenticato
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Ottieni la connessione al database
$conn = getDbConnection();

// Inizializza le variabili
$isEdit = isset($_POST['is_edit']) && $_POST['is_edit'] == 1;
$productId = $isEdit ? intval($_POST['product_id']) : 0;

// Variabile per tracciare i messaggi di errore
$errorMessages = [];

// Ottieni i dati dal form
$tipoProdotto = sanitizeInput($_POST['tipo_prodotto'] ?? '');
$tipologia = sanitizeInput($_POST['tipologia'] ?? '');
$nomeModello = sanitizeInput($_POST['nome_modello'] ?? '');
$titolo = sanitizeInput($_POST['titolo'] ?? '');
$prezzoAcquisto = floatval($_POST['prezzo_acquisto'] ?? 0);
$prezzoVendita = floatval($_POST['prezzo_vendita'] ?? 0);
$aliquotaIva = intval($_POST['aliquota_iva'] ?? 22);
$genere = sanitizeInput($_POST['genere'] ?? '');
$stagione = sanitizeInput($_POST['stagione'] ?? '');
$fornitore = sanitizeInput($_POST['fornitore'] ?? '');

// Controlla se è stata inserita una nuova stagione
if (isset($_POST['altra_stagione']) && !empty($_POST['altra_stagione'])) {
    $stagione = sanitizeInput($_POST['altra_stagione']);
}

// Controlla se è stato inserito un nuovo fornitore
if (isset($_POST['altro_fornitore']) && !empty($_POST['altro_fornitore'])) {
    $fornitore = sanitizeInput($_POST['altro_fornitore']);
}

$marca = sanitizeInput($_POST['marca'] ?? '');
$ean = sanitizeInput($_POST['ean'] ?? '');

// Validazione dei dati
if (empty($nomeModello)) {
    $errorMessages[] = "Il nome del modello è obbligatorio.";
}

if ($prezzoAcquisto <= 0) {
    $errorMessages[] = "Il prezzo di acquisto deve essere maggiore di zero.";
}

if ($prezzoVendita <= 0) {
    $errorMessages[] = "Il prezzo di vendita deve essere maggiore di zero.";
}

// Se ci sono errori, reindirizza con un messaggio
if (!empty($errorMessages)) {
    $_SESSION['notification'] = [
        'type' => 'danger',
        'message' => "Si sono verificati i seguenti errori:<br>" . implode("<br>", $errorMessages)
    ];
    
    if ($isEdit) {
        header("Location: inserimento_prodotti.php?edit=" . $productId);
    } else {
        header("Location: inserimento_prodotti.php");
    }
    exit;
}

// Genera lo SKU per il prodotto principale
$sku = $nomeModello;
if ($tipoProdotto == 'semplice') {
    $sku = generaSKU($nomeModello);
}

try {
    // Avvia una transazione
    $conn->beginTransaction();
    
    if ($isEdit) {
        // Aggiorna il prodotto esistente
        $sql = "UPDATE prodotti SET 
                tipologia = ?, 
                titolo = ?, 
                prezzo_acquisto = ?, 
                prezzo_vendita = ?, 
                aliquota_iva = ?, 
                genere = ?, 
                stagione = ?, 
                fornitore = ?, 
                marca = ?, 
                ean = ?, 
                aggiornato_il = NOW() 
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $tipologia, 
            $titolo, 
            $prezzoAcquisto, 
            $prezzoVendita, 
            $aliquotaIva, 
            $genere, 
            $stagione, 
            $fornitore, 
            $marca, 
            $ean, 
            $productId
        ]);
        
        // Se non è un prodotto semplice, elimina tutte le varianti esistenti
        if ($tipoProdotto != 'semplice') {
            $stmt = $conn->prepare("DELETE FROM prodotti WHERE parent_sku = (SELECT sku FROM prodotti WHERE id = ?)");
            $stmt->execute([$productId]);
        }
    } else {
        // Crea un nuovo prodotto
        $sql = "INSERT INTO prodotti (
                sku, 
                tipologia, 
                titolo, 
                prezzo_acquisto, 
                prezzo_vendita, 
                aliquota_iva, 
                genere, 
                stagione, 
                fornitore, 
                marca, 
                ean,
                utente_id,
                stato
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $sku, 
            $tipologia, 
            $titolo, 
            $prezzoAcquisto, 
            $prezzoVendita, 
            $aliquotaIva, 
            $genere, 
            $stagione, 
            $fornitore, 
            $marca, 
            $ean,
            $_SESSION['user_id'],
            'bozza'
        ]);
        
        $productId = $conn->lastInsertId();
    }
    
    // Aggiornamento moltiplicatore in base alla tipologia (se esiste)
    try {
        // Cerca la tipologia nel database
        $stmtTipologia = $conn->prepare("SELECT moltiplicatore_prezzo FROM tipologie_prodotto WHERE nome = ?");
        $stmtTipologia->execute([$tipologia]);
        $tipologiaInfo = $stmtTipologia->fetch();
        
        // Verifica se tipologiaInfo esiste e se contiene l'indice necessario
        if ($tipologiaInfo && isset($tipologiaInfo['moltiplicatore_prezzo'])) {
            $moltiplicatore = $tipologiaInfo['moltiplicatore_prezzo'];
            
            // Non abbiamo un campo per il moltiplicatore nella tabella prodotti,
            // quindi non facciamo nulla qui
        }
    } catch (Exception $e) {
        logMessage("Errore nell'aggiornamento del moltiplicatore: " . $e->getMessage(), 'ERROR');
    }
    
    // Gestione delle varianti
    if ($tipoProdotto != 'semplice' && isset($_POST['varianti_json'])) {
        $varianti = json_decode($_POST['varianti_json'], true);
        
        if (is_array($varianti)) {
            foreach ($varianti as $variante) {
                // Validazione dei dati della variante
                if (empty($variante['sku'])) continue;
                
                $varianteSku = sanitizeInput($variante['sku']);
                $varianteTaglia = sanitizeInput($variante['taglia'] ?? '');
                $varianteColore = sanitizeInput($variante['colore'] ?? '');
                $varianteEan = sanitizeInput($variante['ean'] ?? '');
                $variantePrezzoAcquisto = floatval($variante['prezzo_acquisto'] ?? $prezzoAcquisto);
                $variantePrezzoVendita = floatval($variante['prezzo_vendita'] ?? $prezzoVendita);
                
                // Titolo della variante
                $varianteTitolo = $titolo;
                if (!empty($varianteTaglia)) {
                    $varianteTitolo .= ' - ' . $varianteTaglia;
                }
                if (!empty($varianteColore)) {
                    $varianteTitolo .= ' - ' . $varianteColore;
                }
                
                // Inserisci la variante
                $sql = "INSERT INTO prodotti (
                        sku, 
                        tipologia, 
                        titolo, 
                        prezzo_acquisto, 
                        prezzo_vendita, 
                        aliquota_iva, 
                        genere, 
                        stagione, 
                        fornitore, 
                        marca, 
                        ean, 
                        parent_sku,
                        taglia,
                        colore,
                        utente_id,
                        stato
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $varianteSku, 
                    $tipologia, 
                    $varianteTitolo, 
                    $variantePrezzoAcquisto, 
                    $variantePrezzoVendita, 
                    $aliquotaIva, 
                    $genere, 
                    $stagione, 
                    $fornitore, 
                    $marca, 
                    $varianteEan, 
                    $sku,
                    $varianteTaglia,
                    $varianteColore,
                    $_SESSION['user_id'],
                    'bozza'
                ]);
            }
        }
    }
    
    // Commit della transazione
    $conn->commit();
    
    // Messaggio di successo
    $_SESSION['notification'] = [
        'type' => 'success',
        'message' => $isEdit ? "Prodotto aggiornato con successo!" : "Nuovo prodotto creato con successo!"
    ];
    
    // Reindirizza alla pagina di gestione prodotti
    header("Location: gestione_prodotti.php");
    exit;
    
} catch (Exception $e) {
    // In caso di errore, rollback della transazione
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log dell'errore
    logMessage("Errore nel salvataggio del prodotto: " . $e->getMessage(), 'ERROR');
    
    // Messaggio di errore
    $_SESSION['notification'] = [
        'type' => 'danger',
        'message' => "Si è verificato un errore durante il salvataggio: " . $e->getMessage()
    ];
    
    // Reindirizza alla pagina di inserimento prodotti
    if ($isEdit) {
        header("Location: inserimento_prodotti.php?edit=" . $productId);
    } else {
        header("Location: inserimento_prodotti.php");
    }
    exit;
}