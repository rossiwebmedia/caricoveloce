<?php
/**
 * Funzioni comuni utilizzate in tutto il sito
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

/**
 * Calcola il prezzo di vendita in base al tipo di prodotto e al prezzo di acquisto
 *
 * @param float $prezzoAcquisto Prezzo di acquisto
 * @param string $tipologia Tipologia di prodotto
 * @return float Prezzo di vendita calcolato
 */
function calcolaPrezzoVendita($prezzoAcquisto, $tipologia) {
    $conn = getDbConnection();
    
    // Ottieni il moltiplicatore e il valore di arrotondamento dalla tabella tipologie_prodotto
    $stmt = $conn->prepare("SELECT moltiplicatore_prezzo, arrotonda_a FROM tipologie_prodotto WHERE nome = ?");
    $stmt->execute([$tipologia]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Valori predefiniti se la tipologia non esiste
        $moltiplicatore = 3.0;
        $arrotondaA = 9.90;
    } else {
        $moltiplicatore = $result['moltiplicatore_prezzo'];
        $arrotondaA = $result['arrotonda_a'];
    }
    
    // Applica regole specifiche in base alla tipologia
    if ($tipologia == 'uomo' || $tipologia == 'donna') {
        // Per abbigliamento, se prezzo < 10 euro usiamo un moltiplicatore diverso
        if ($prezzoAcquisto <= 10) {
            $moltiplicatore = 3.0;
        }
    }
    
    // Calcola il prezzo base
    $prezzo = $prezzoAcquisto * $moltiplicatore;
    
    // Arrotonda al valore più vicino (es. 24.90, 29.90, ecc.)
    $resto = $prezzo % 10;
    if ($resto > $arrotondaA) {
        $prezzo = floor($prezzo / 10) * 10 + $arrotondaA;
    } else {
        $prezzo = floor($prezzo / 10) * 10 - (10 - $arrotondaA);
        if ($prezzo < 0) $prezzo = $arrotondaA;
    }
    
    return $prezzo;
}

/**
 * Ottiene un codice EAN non utilizzato e lo marca come utilizzato
 *
 * @param int $prodottoId ID del prodotto a cui assegnare l'EAN
 * @return string|null Codice EAN o null se non disponibile
 */
function getNextEAN($prodottoId = null) {
    $conn = getDbConnection();
    
    // Transazione per assicurarsi che non ci siano problemi di concorrenza
    $conn->beginTransaction();
    
    try {
        // Trova il primo EAN non utilizzato
        $stmt = $conn->query("SELECT id, ean FROM codici_ean WHERE utilizzato = 0 LIMIT 1 FOR UPDATE");
        $ean = $stmt->fetch();
        
        if (!$ean) {
            $conn->rollBack();
            return null;
        }
        
        // Marca l'EAN come utilizzato
        $updateStmt = $conn->prepare("UPDATE codici_ean SET utilizzato = 1, prodotto_id = ?, aggiornato_il = NOW() WHERE id = ?");
        $updateStmt->execute([$prodottoId, $ean['id']]);
        
        $conn->commit();
        return $ean['ean'];
    } catch (Exception $e) {
        $conn->rollBack();
        logMessage("Errore nell'ottenere un codice EAN: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * Ottiene le taglie preimpostate per una determinata tipologia
 *
 * @param string $tipologia Tipologia (es. abbigliamento_uomo, scarpe_donna)
 * @return array Lista di taglie
 */
function getTagliePreset($tipologia) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT taglie FROM preset_taglie WHERE nome = ?");
    $stmt->execute([$tipologia]);
    $result = $stmt->fetch();
    
    if ($result) {
        return explode(',', $result['taglie']);
    }
    
    return [];
}

/**
 * Ottiene i colori preimpostati per un determinato set
 *
 * @param string $nome Nome del preset (es. base, estesi)
 * @return array Lista di colori
 */
function getColoriPreset($nome = 'base') {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT colori FROM preset_colori WHERE nome = ?");
    $stmt->execute([$nome]);
    $result = $stmt->fetch();
    
    if ($result) {
        return explode(',', $result['colori']);
    }
    
    return [];
}

/**
 * Genera uno SKU valido basato sul nome modello e sulle varianti
 *
 * @param string $nomeModello Nome del modello
 * @param string $taglia Taglia (opzionale)
 * @param string $colore Colore (opzionale)
 * @return string SKU generato
 */
function generaSKU($nomeModello, $taglia = '', $colore = '') {
    // Converti in minuscolo e sostituisci gli spazi con trattini
    $base = strtolower(preg_replace('/\s+/', '-', trim($nomeModello)));
    
    // Rimuovi caratteri speciali
    $base = preg_replace('/[^a-z0-9\-]/', '', $base);
    
    if (!empty($taglia) && !empty($colore)) {
        // Formatta taglia e colore
        $taglia = strtolower(trim($taglia));
        $colore = strtolower(preg_replace('/\s+/', '-', trim($colore)));
        return "{$base}-{$taglia}-{$colore}";
    } elseif (!empty($taglia)) {
        $taglia = strtolower(trim($taglia));
        return "{$base}-{$taglia}";
    } elseif (!empty($colore)) {
        $colore = strtolower(preg_replace('/\s+/', '-', trim($colore)));
        return "{$base}-{$colore}";
    }
    
    return $base;
}

function callSmartyApi($endpoint, $method = 'GET', $data = null, $apiKey = null) {
    $url = "https://www.gestionalesmarty.com/titanium/V2/Api/" . $endpoint . "?ApiKey=" . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "Errore cURL: $error"];
    }

    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Risposta non valida JSON', 'raw_response' => $response];
    }

    return $decodedResponse;
}
/**
 * Crea un nuovo prodotto su Smarty
 *
 * @param array $prodottoData Dati del prodotto
 * @return array Risultato dell'operazione
 */
function creaProdottoSmarty($prodottoData) {
    // Dati necessari per Smarty
    $smartyData = [
        'sku' => $prodottoData['sku'],
        'ean' => $prodottoData['ean'],
        'title' => $prodottoData['titolo'],
        'description' => $prodottoData['descrizione'] ?? '',
        'description_short' => $prodottoData['descrizione_breve'] ?? '',
        'price' => floatval($prodottoData['prezzo_vendita']),
        'purchase_price' => floatval($prodottoData['prezzo_acquisto']),
        'stock' => 0, // Default a 0, va aggiornato separatamente
        'tax_id' => ($prodottoData['aliquota_iva'] == 22) ? 1 : 2, // 1 = 22%, 2 = altro
        'brand' => $prodottoData['marca'] ?? 'Ciabalù'
    ];
    
    // Aggiungi il fornitore se presente
    if (!empty($prodottoData['fornitore'])) {
        $smartyData['supplier'] = [$prodottoData['fornitore']];
    }
    
    // Esegui la chiamata API
    $result = callSmartyApi('Products/post', 'POST', $smartyData);
    
    return $result;
}

/**
 * Crea una variante di prodotto su Smarty
 *
 * @param array $varianteData Dati della variante
 * @param int $prodottoId ID del prodotto principale su Smarty
 * @return array Risultato dell'operazione
 */
function creaVarianteSmarty($varianteData, $prodottoId) {
    // Dati necessari per la variante
    $smartyData = [
        'product_id' => $prodottoId,
        'sku' => $varianteData['sku'],
        'ean' => $varianteData['ean'],
        'price' => floatval($varianteData['prezzo_vendita']),
        'purchase_price' => floatval($varianteData['prezzo_acquisto']),
        'tax_id' => ($varianteData['aliquota_iva'] == 22) ? 1 : 2,
        'stock' => 0, // Default a 0
        'detail' => []
    ];
    
    // Aggiungi taglia se presente
    if (!empty($varianteData['taglia'])) {
        $smartyData['detail'][] = [
            'name' => 'Taglia',
            'value' => $varianteData['taglia']
        ];
    }
    
    // Aggiungi colore se presente
    if (!empty($varianteData['colore'])) {
        $smartyData['detail'][] = [
            'name' => 'Colore',
            'value' => $varianteData['colore']
        ];
    }
    
    // Esegui la chiamata API
    $result = callSmartyApi('Variations/post', 'POST', $smartyData);
    
    return $result;
}

/**
 * Sanitizza l'input dell'utente
 *
 * @param string $input Input da sanitizzare
 * @return string Input sanitizzato
 */
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Genera una password casuale
 *
 * @param int $length Lunghezza della password
 * @return string Password generata
 */
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    $characterCount = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[mt_rand(0, $characterCount)];
    }
    
    return $password;
}