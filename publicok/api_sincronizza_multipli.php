<?php
/**
 * API per sincronizzare multipli prodotti con Smarty
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
$filters = json_decode($input, true);

// Prepara la query SQL per ottenere i prodotti da sincronizzare
$sql = "SELECT id FROM prodotti p";
$where = [];
$params = [];

// Applica i filtri
if (isset($filters['stato']) && !empty($filters['stato'])) {
    $where[] = 'p.stato = ?';
    $params[] = $filters['stato'];
}

if (isset($filters['tipologia']) && !empty($filters['tipologia'])) {
    $where[] = 'p.tipologia = ?';
    $params[] = $filters['tipologia'];
}

if (isset($filters['solo_parent']) && $filters['solo_parent'] == '1') {
    $where[] = 'p.parent_sku IS NULL';
}

if (isset($filters['search']) && !empty($filters['search'])) {
    $where[] = '(p.sku LIKE ? OR p.titolo LIKE ? OR p.ean LIKE ?)';
    $search = "%{$filters['search']}%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Aggiungi la clausola WHERE se ci sono filtri
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// Limita il numero di prodotti per evitare timeout
$sql .= ' LIMIT 100';

try {
    // Connessione al database
    $conn = getDbConnection();
    
    // Ottieni l'elenco dei prodotti da sincronizzare
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($products)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nessun prodotto trovato con i filtri specificati'
        ]);
        exit;
    }
    
    // Inizializza i contatori
    $successCount = 0;
    $errorCount = 0;
    
    // Sincronizza ogni prodotto
    foreach ($products as $productId) {
        // Ottieni le informazioni sul prodotto
        $stmt = $conn->prepare("SELECT * FROM prodotti WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $errorCount++;
            continue;
        }
        
        // Salta i prodotti già pubblicati
        if ($product['stato'] === 'pubblicato') {
            $successCount++;
            continue;
        }
        
        // Determina se è un prodotto parent o una variante
        $isParent = empty($product['parent_sku']);
        
        if ($isParent) {
            // Se è un prodotto parent, sincronizza il prodotto e le sue varianti
            $result = sincronizzaProdottoParent($productId);
        } else {
            // Se è una variante, sincronizza solo la variante
            $result = sincronizzaVariante($productId);
        }
        
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    // Restituisci il risultato
    echo json_encode([
        'success' => true,
        'message' => 'Sincronizzazione multipla completata',
        'total_products' => count($products),
        'success_count' => $successCount,
        'error_count' => $errorCount
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante la sincronizzazione multipla: ' . $e->getMessage()
    ]);
}

/**
 * Sincronizza un prodotto parent e le sue varianti
 * 
 * @param int $productId ID del prodotto parent
 * @return array Risultato dell'operazione
 */
function sincronizzaProdottoParent($productId) {
    // Questa funzione dovrebbe contenere la stessa logica di sincronizzazione
    // che è presente in api_sincronizza_prodotto.php per i prodotti parent
    
    // Per esempio, potrebbe chiamare direttamente quella funzione:
    // Simuliamo una chiamata all'API di sincronizzazione
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api_sincronizza_prodotto.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "id=$productId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    // Fallback in caso di errori di comunicazione
    if ($result === null) {
        return [
            'success' => false,
            'message' => 'Errore nella comunicazione con l\'API di sincronizzazione'
        ];
    }
    
    return $result;
}

/**
 * Sincronizza una variante di prodotto
 * 
 * @param int $variantId ID della variante
 * @return array Risultato dell'operazione
 */
function sincronizzaVariante($variantId) {
    // Questa funzione dovrebbe contenere la stessa logica di sincronizzazione
    // che è presente in api_sincronizza_prodotto.php per le varianti
    
    // Per esempio, potrebbe chiamare direttamente quella funzione:
    // Simuliamo una chiamata all'API di sincronizzazione
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api_sincronizza_prodotto.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "id=$variantId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    // Fallback in caso di errori di comunicazione
    if ($result === null) {
        return [
            'success' => false,
            'message' => 'Errore nella comunicazione con l\'API di sincronizzazione'
        ];
    }
    
    return $result;
}