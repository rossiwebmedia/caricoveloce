<?php
/**
 * API per l'importazione di codici EAN da file
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

// Verifica se è stato caricato un file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Nessun file caricato o errore nel caricamento'
    ]);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$tmpName = $file['tmp_name'];
$fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Verifica il tipo di file
if (!in_array($fileType, ['xlsx', 'xls', 'csv'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Tipo di file non supportato. Carica un file XLSX, XLS o CSV.'
    ]);
    exit;
}

// Genera un nome univoco per il file caricato
$uploadedFile = UPLOADS_DIR . '/' . uniqid('ean_') . '.' . $fileType;

// Sposta il file caricato
if (!move_uploaded_file($tmpName, $uploadedFile)) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore nel salvataggio del file'
    ]);
    exit;
}

// Determina il nome del batch per i codici EAN
$batchName = 'Importazione ' . date('Y-m-d H:i:s');

try {
    // Inizializza l'array per i codici EAN
    $eans = [];
    
    // Gestione diversa in base al tipo di file
    if ($fileType === 'csv') {
        // Apri il file CSV
        $handle = fopen($uploadedFile, 'r');
        
        if ($handle !== false) {
            // Leggi la prima riga per determinare se ci sono intestazioni
            $header = fgetcsv($handle);
            $hasHeader = false;
            
            // Verifica se la prima riga sembra essere un'intestazione
            if (is_array($header) && count($header) > 0) {
                $firstCell = strtolower(trim($header[0]));
                $hasHeader = preg_match('/^(ean|codice|barcode|code)/i', $firstCell);
            }
            
            // Se non è un'intestazione, considera la prima riga come dati
            if (!$hasHeader && is_array($header)) {
                $eans[] = trim($header[0]);
            }
            
            // Leggi le righe rimanenti
            while (($row = fgetcsv($handle)) !== false) {
                if (is_array($row) && count($row) > 0 && !empty($row[0])) {
                    $eans[] = trim($row[0]);
                }
            }
            
            fclose($handle);
        }
    } else {
        // Per file Excel, usa PhpSpreadsheet o una libreria simile
        // In questo esempio, useremo una versione semplificata
        
        // Verifica se è disponibile la libreria PhpSpreadsheet
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            echo json_encode([
                'success' => false,
                'message' => 'Libreria PhpSpreadsheet non disponibile. Contatta l\'amministratore.'
            ]);
            exit;
        }
        
        // Carica il file Excel
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadedFile);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Ottieni l'intervallo di celle
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Assume che la prima colonna contenga i codici EAN
        for ($row = 1; $row <= $highestRow; $row++) {
            $value = $worksheet->getCell('A' . $row)->getValue();
            if (!empty($value)) {
                $eans[] = trim($value);
            }
        }
    }
    
    // Rimuovi eventuali duplicati
    $eans = array_unique($eans);
    
    // Connessione al database
    $conn = getDbConnection();
    
    // Crea un nuovo batch
    $stmt = $conn->prepare("INSERT INTO batch_ean (nome, totale, utente_id) VALUES (?, ?, ?)");
    $stmt->execute([$batchName, count($eans), $_SESSION['user_id']]);
    $batchId = $conn->lastInsertId();
    
    // Prepara l'inserimento dei codici EAN
    $stmt = $conn->prepare("INSERT INTO codici_ean (ean, batch_id) VALUES (?, ?)");
    
    // Inserisci i codici EAN nel database
    foreach ($eans as $ean) {
        $stmt->execute([$ean, $batchId]);
    }
    
    // Rimuovi il file dopo l'elaborazione
    unlink($uploadedFile);
    
    // Restituisci il risultato
    echo json_encode([
        'success' => true,
        'message' => 'File elaborato con successo',
        'batch_id' => $batchId,
        'count' => count($eans),
        'eans' => $eans
    ]);
} catch (Exception $e) {
    logMessage("Errore nell'importazione EAN: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'Errore nell\'elaborazione del file: ' . $e->getMessage()
    ]);
    
    // Rimuovi il file in caso di errore
    if (file_exists($uploadedFile)) {
        unlink($uploadedFile);
    }
}
