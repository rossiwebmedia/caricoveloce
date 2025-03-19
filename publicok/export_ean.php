<?php
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Recupera parametri
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filter_batch = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Connessione al database
$conn = getDbConnection();

// Costruzione query - Rimossi i riferimenti a varianti_prodotto
$query = "SELECT e.id, e.ean, e.batch_id, e.utilizzato, e.prodotto_id, e.creato_il, e.aggiornato_il,
          b.nome AS batch_nome,
          p.sku AS prodotto_sku, p.nome AS prodotto_nome
          FROM codici_ean e 
          LEFT JOIN batch_ean b ON e.batch_id = b.id
          LEFT JOIN prodotti p ON e.prodotto_id = p.id";

$whereConditions = [];
$params = [];

// Applicazione filtri - CORRETTI per rimuovere riferimenti a varianti_prodotto
if (!empty($search)) {
    $whereConditions[] = "(e.ean LIKE ? OR p.sku LIKE ? OR p.nome LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filter_status === 'used') {
    $whereConditions[] = "e.utilizzato = 1";
} elseif ($filter_status === 'available') {
    $whereConditions[] = "e.utilizzato = 0";
}

if ($filter_batch > 0) {
    $whereConditions[] = "e.batch_id = ?";
    $params[] = $filter_batch;
}

if (!empty($filter_date_from)) {
    $whereConditions[] = "e.creato_il >= ?";
    $params[] = $filter_date_from . ' 00:00:00';
}

if (!empty($filter_date_to)) {
    $whereConditions[] = "e.creato_il <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

// Aggiunta delle condizioni WHERE
if (count($whereConditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

// Ordinamento
$query .= " ORDER BY e.creato_il DESC";

// Esecuzione query
$stmt = $conn->prepare($query);
foreach ($params as $index => $value) {
    $stmt->bindValue($index + 1, $value);
}
$stmt->execute();
$eans = $stmt->fetchAll();

// Esporta come CSV
if ($format === 'csv') {
    $filename = 'export_ean_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Intestazioni CSV
    fputcsv($output, [
        'ID', 
        'Codice EAN', 
        'Batch', 
        'Stato', 
        'SKU Prodotto',
        'Nome Prodotto',
        'Data Creazione',
        'Data Utilizzo'
    ]);
    
    // Dati
    foreach ($eans as $ean) {
        $stato = $ean['utilizzato'] ? 'Utilizzato' : 'Disponibile';
        $dataCreazione = date('d/m/Y H:i', strtotime($ean['creato_il']));
        $dataUtilizzo = $ean['utilizzato'] && $ean['aggiornato_il'] ? date('d/m/Y H:i', strtotime($ean['aggiornato_il'])) : '';
        
        fputcsv($output, [
            $ean['id'],
            $ean['ean'],
            $ean['batch_nome'] ?? '',
            $stato,
            $ean['prodotto_sku'] ?? '',
            $ean['prodotto_nome'] ?? '',
            $dataCreazione,
            $dataUtilizzo
        ]);
    }
    
    fclose($output);
    exit;
}

// Formato non supportato
header('HTTP/1.1 400 Bad Request');
echo 'Formato di esportazione non supportato';