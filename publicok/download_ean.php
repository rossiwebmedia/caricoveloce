<?php
/**
 * Script per il download dei codici EAN in formato CSV
 */
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica il parametro batch_id
$batchId = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

if ($batchId <= 0) {
    header('Location: gestione_ean.php');
    exit;
}

// Connessione al database
$conn = getDbConnection();

// Verifica che il batch esista
$stmt = $conn->prepare("SELECT nome FROM batch_ean WHERE id = ?");
$stmt->execute([$batchId]);
$batch = $stmt->fetch();

if (!$batch) {
    header('Location: gestione_ean.php');
    exit;
}

// Ottieni i codici EAN del batch (solo quelli disponibili)
$stmt = $conn->prepare("SELECT ean FROM codici_ean WHERE batch_id = ? AND utilizzato = 0 ORDER BY id ASC");
$stmt->execute([$batchId]);
$eans = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Genera il nome del file
$filename = 'ean_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($batch['nome'])) . '_' . date('Ymd_His') . '.csv';

// Imposta gli header per il download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Apri l'output come file CSV
$output = fopen('php://output', 'w');

// Scrivi l'intestazione
fputcsv($output, ['EAN']);

// Scrivi i dati
foreach ($eans as $ean) {
    fputcsv($output, [$ean]);
}

// Chiudi il file
fclose($output);
exit;
