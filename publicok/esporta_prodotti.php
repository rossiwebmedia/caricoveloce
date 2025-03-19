<?php
/**
 * Script per l'esportazione dei prodotti in formato CSV o Excel
 */
require_once 'config.php';
require_once 'functions.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Ottieni i parametri di esportazione
$format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'csv';
$includeVariants = isset($_GET['include_variants']) && $_GET['include_variants'] == '1';

// Filtri
$filters = [];
$filterSql = '';
$filterParams = [];

// Filtro per stato
$stato = isset($_GET['stato']) ? sanitizeInput($_GET['stato']) : '';
if (!empty($stato)) {
    $filters[] = 'p.stato = ?';
    $filterParams[] = $stato;
}

// Filtro per tipologia
$tipologia = isset($_GET['tipologia']) ? sanitizeInput($_GET['tipologia']) : '';
if (!empty($tipologia)) {
    $filters[] = 'p.tipologia = ?';
    $filterParams[] = $tipologia;
}

// Filtro per solo prodotti parent
$soloParent = isset($_GET['solo_parent']) && $_GET['solo_parent'] == '1';
if ($soloParent && !$includeVariants) {
    $filters[] = 'p.parent_sku IS NULL';
}

// Filtro per ricerca testo
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
if (!empty($search)) {
    $filters[] = '(p.sku LIKE ? OR p.titolo LIKE ? OR p.ean LIKE ?)';
    $filterParams[] = "%{$search}%";
    $filterParams[] = "%{$search}%";
    $filterParams[] = "%{$search}%";
}

// Combina i filtri
if (!empty($filters)) {
    $filterSql = ' WHERE ' . implode(' AND ', $filters);
}

// Costruisci la query SQL
$sql = "SELECT 
            p.id, p.sku, p.parent_sku, p.titolo, p.descrizione, p.descrizione_breve, 
            p.tipologia, p.genere, p.stagione, p.taglia, p.colore, p.ean, 
            p.prezzo_acquisto, p.prezzo_vendita, p.aliquota_iva, p.marca, p.fornitore, 
            p.stato, p.smarty_id, p.creato_il,
            u.username as utente
        FROM prodotti p
        LEFT JOIN utenti u ON p.utente_id = u.id
        " . $filterSql . "
        ORDER BY p.creato_il DESC";

try {
    // Connessione al database
    $conn = getDbConnection();
    
    // Esegui la query
    $stmt = $conn->prepare($sql);
    if (!empty($filterParams)) {
        $stmt->execute($filterParams);
    } else {
        $stmt->execute();
    }
    $products = $stmt->fetchAll();
    
    // Prepara le intestazioni per l'esportazione
    $headers = [
        'ID', 'SKU', 'Parent SKU', 'Titolo', 'Tipologia', 'Genere', 'Stagione',
        'Taglia', 'Colore', 'EAN', 'Prezzo Acquisto', 'Prezzo Vendita',
        'Aliquota IVA', 'Marca', 'Fornitore', 'Stato', 'Smarty ID', 'Data Creazione', 'Utente'
    ];
    
    // Prepara i dati per l'esportazione
    $data = [];
    
    foreach ($products as $product) {
        // Salta le varianti se non richieste
        if (!$includeVariants && !empty($product['parent_sku'])) {
            continue;
        }
        
        $data[] = [
            $product['id'],
            $product['sku'],
            $product['parent_sku'] ?: '',
            $product['titolo'],
            $product['tipologia'],
            $product['genere'],
            $product['stagione'],
            $product['taglia'] ?: '',
            $product['colore'] ?: '',
            $product['ean'],
            $product['prezzo_acquisto'],
            $product['prezzo_vendita'],
            $product['aliquota_iva'],
            $product['marca'],
            $product['fornitore'] ?: '',
            $product['stato'],
            $product['smarty_id'] ?: '',
            date('d/m/Y H:i', strtotime($product['creato_il'])),
            $product['utente'] ?: 'Sconosciuto'
        ];
    }
    
    // Genera il nome del file
    $filename = 'prodotti_' . date('Y-m-d_His');
    if ($format === 'excel') {
    // Informa l'utente che l'esportazione Excel non è disponibile
    header('Content-Type: text/html');
    echo '<script>
        alert("L\'esportazione Excel non è disponibile. Libreria mancante. Prova con il formato CSV.");
        window.location.href = "gestione_prodotti.php";
    </script>';
    exit;
} else {
        // Esportazione in formato CSV (default)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // Apri l'output per il CSV
        $output = fopen('php://output', 'w');
        
        // Output della BOM UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Aggiungi le intestazioni
        fputcsv($output, $headers);
        
        // Aggiungi i dati
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        // Chiudi il file
        fclose($output);
    }
} catch (Exception $e) {
    // In caso di errore, scrivi un messaggio nel log e reindirizza con un messaggio di errore
    logMessage("Errore nell'esportazione dei prodotti: " . $e->getMessage(), 'ERROR');
    
    $_SESSION['notification'] = [
        'type' => 'danger',
        'message' => "Errore durante l'esportazione dei prodotti: " . $e->getMessage()
    ];
    
    header('Location: gestione_prodotti.php');
    exit;
}