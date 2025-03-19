<?php
/**
 * Script per la generazione di codici EAN demo
 */
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gestione_ean.php?action=importa');
    exit;
}

// Parametri di generazione
$count = isset($_POST['demo_count']) ? intval($_POST['demo_count']) : 100;
$prefix = isset($_POST['demo_prefix']) ? sanitizeInput($_POST['demo_prefix']) : '8012345';

// Limita il numero di codici generabili
$count = max(1, min(1000, $count));

// Verifica che il prefisso sia valido (solo cifre e lunghezza max 7)
if (!preg_match('/^[0-9]{1,7}$/', $prefix)) {
    $prefix = '8012345'; // Prefisso predefinito in caso di errore
}

// Connessione al database
$conn = getDbConnection();

try {
    // Crea un nuovo batch
    $batchName = 'Demo EAN ' . date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO batch_ean (nome, descrizione, totale, utente_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$batchName, 'Codici EAN generati automaticamente per test', $count, $_SESSION['user_id']]);
    $batchId = $conn->lastInsertId();
    
    // Genera i codici EAN
    $eans = [];
    $generatedCount = 0;
    $baseNumber = 1;
    
    while ($generatedCount < $count) {
        // Genera il codice EAN
        $eanWithoutChecksum = $prefix . str_pad($baseNumber, 12 - strlen($prefix), '0', STR_PAD_LEFT);
        
        // Calcola la cifra di controllo (semplificato per brevità)
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = intval($eanWithoutChecksum[$i]);
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        $checksum = (10 - ($sum % 10)) % 10;
        
        // Genera il codice EAN completo
        $ean = $eanWithoutChecksum . $checksum;
        
        try {
            // Inserisci il codice EAN nel database
            $stmt = $conn->prepare("INSERT INTO codici_ean (ean, batch_id) VALUES (?, ?)");
            $stmt->execute([$ean, $batchId]);
            
            $eans[] = $ean;
            $generatedCount++;
        } catch (PDOException $e) {
            // Ignora i duplicati
            if ($e->getCode() !== '23000') { // Errore di duplicato
                throw $e;
            }
        }
        
        $baseNumber++;
    }
    
    // Aggiorna il conteggio totale del batch
    $stmt = $conn->prepare("UPDATE batch_ean SET totale = ? WHERE id = ?");
    $stmt->execute([$generatedCount, $batchId]);
    
    // Reindirizza alla pagina di gestione EAN con un messaggio di successo
    $_SESSION['notification'] = [
        'type' => 'success',
        'message' => "Generati con successo $generatedCount codici EAN demo."
    ];
    
    header('Location: gestione_ean.php?action=view&id=' . $batchId);
    exit;
} catch (Exception $e) {
    logMessage("Errore nella generazione di EAN demo: " . $e->getMessage(), 'ERROR');
    
    // Reindirizza con messaggio di errore
    $_SESSION['notification'] = [
        'type' => 'danger',
        'message' => "Errore durante la generazione dei codici EAN: " . $e->getMessage()
    ];
    
    header('Location: gestione_ean.php?action=importa');
    exit;
}
