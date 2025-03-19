<?php
/**
 * Funzioni comuni utilizzate in tutto il sito
 */

// Previeni inclusioni multiple
if (defined('FUNCTIONS_LOADED')) {
    return;
}
define('FUNCTIONS_LOADED', true);

// Includi la configurazione se non è già stata inclusa
if (!defined('CONFIG_LOADED')) {
    require_once 'config.php';
}

/**
 * Calcola il prezzo di vendita in base al tipo di prodotto e al prezzo di acquisto
 *
 * @param float $prezzoAcquisto Prezzo di acquisto
 * @param string $tipologia Tipologia di prodotto
 * @return float Prezzo di vendita calcolato
 */
function calcolaPrezzoVendita($prezzoAcquisto, $tipologia) {
    try {
        $conn = getDbConnection();
        
        if ($conn === null) {
            // Se la connessione fallisce, usa valori predefiniti
            $moltiplicatore = 3.0;
            $arrotondaA = 9.90;
        } else {
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
    } catch (Exception $e) {
        logMessage("Errore nel calcolo del prezzo: " . $e->getMessage(), 'ERROR');
        // Fallback in caso di errore: moltiplica per 3 e arrotonda a .90
        return floor($prezzoAcquisto * 3) + 0.90;
    }
}

/**
 * Ottiene i generi disponibili
 * @return array Array associativo di generi
 */
function getGeneri() {
    try {
        $conn = getDbConnection();
        $generi = ['' => 'Seleziona...'];
        
        // Prova a caricare da cache_generi
        if ($conn->query("SHOW TABLES LIKE 'cache_generi'")->rowCount() > 0) {
            $stmt = $conn->query("SELECT nome FROM cache_generi ORDER BY nome");
            while ($row = $stmt->fetch()) {
                $key = strtolower(str_replace(' ', '_', $row['nome']));
                $generi[$key] = $row['nome'];
            }
        }
        
        // Se non ci sono risultati, usa i valori predefiniti
        if (count($generi) <= 1) {
            $generi = [
                '' => 'Seleziona...',
                'uomo' => 'Uomo',
                'donna' => 'Donna',
                'unisex' => 'Unisex',
                'bambino' => 'Bambino',
                'bambina' => 'Bambina'
            ];
        }
        
        return $generi;
    } catch (Exception $e) {
        logMessage('Errore nel recupero dei generi: ' . $e->getMessage(), 'ERROR');
        return [
            '' => 'Seleziona...',
            'uomo' => 'Uomo',
            'donna' => 'Donna',
            'unisex' => 'Unisex'
        ];
    }
}

/**
 * Ottiene le stagioni disponibili
 * @return array Array associativo di stagioni
 */
function getStagioni() {
    try {
        $conn = getDbConnection();
        $stagioni = ['' => 'Seleziona...'];
        
        // Prova a caricare da cache_stagioni
        if ($conn->query("SHOW TABLES LIKE 'cache_stagioni'")->rowCount() > 0) {
            $stmt = $conn->query("SELECT nome FROM cache_stagioni ORDER BY nome");
            while ($row = $stmt->fetch()) {
                $key = strtolower(str_replace(' ', '_', $row['nome'])); 
                $stagioni[$key] = $row['nome'];
            }
        }
        
        // Se non ci sono risultati, usa i valori predefiniti
        if (count($stagioni) <= 1) {
            $stagioni = [
                '' => 'Seleziona...',
                'ss25' => 'SS25',
                'fw25' => 'FW25',
                'ss26' => 'SS26',
                'fw26' => 'FW26',
                'accessori' => 'Accessori'
            ];
        }
        
        return $stagioni;
    } catch (Exception $e) {
        logMessage('Errore nel recupero delle stagioni: ' . $e->getMessage(), 'ERROR');
        return [
            '' => 'Seleziona...',
            'ss25' => 'SS25',
            'fw25' => 'FW25',
            'ss26' => 'SS26',
            'fw26' => 'FW26',
            'accessori' => 'Accessori'
        ];
    }
}

/**
 * Ottiene un codice EAN non utilizzato e lo marca come utilizzato
 *
 * @param int $prodottoId ID del prodotto a cui assegnare l'EAN
 * @return string|null Codice EAN o null se non disponibile
 */
function getNextEAN($prodottoId = null) {
    try {
        $conn = getDbConnection();
        
        if ($conn === null) {
            return null;
        }
        
        // Transazione per assicurarsi che non ci siano problemi di concorrenza
        $conn->beginTransaction();
        
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
        // Assicurati di eseguire il rollback in caso di errore
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        logMessage("Errore nell'ottenere un codice EAN: " . $e->getMessage(), 'ERROR');
        return null;
    }
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

/**
 * Invia una richiesta all'API Smarty
 *
 * @param string $endpoint Endpoint API (es. Products)
 * @param string $action Azione da eseguire (es. post, list) o ID record
 * @param array|null $data Dati da inviare (per POST o PUT)
 * @param string $apiKey API key da utilizzare (opzionale)
 * @return array Risposta dall'API
 */
function callSmartyApi($endpoint, $action = 'list', $data = null, $apiKey = '') {
    try {
        // Se non è stata fornita una API key, usa quella predefinita
        if (empty($apiKey)) {
            $apiKey = getDefaultApiKey();
            
            if (empty($apiKey)) {
                throw new Exception('API key non configurata');
            }
        }
        
        // Costruisci l'URL dell'API
        $url = SMARTY_API_BASE_URL . $endpoint;
        
        // Aggiungi l'azione all'URL se necessario
        if (!empty($action) && $action !== 'list') {
            $url .= '/' . $action;
        }
        
        // Aggiungi l'API key all'URL
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'ApiKey=' . urlencode($apiKey);
        
        // Determina il metodo HTTP in base all'azione
        $method = 'GET';
        if ($action === 'post') {
            $method = 'POST';
        } else if ($action === 'put') {
            $method = 'PUT';
        }
        
        // Log della chiamata
        logMessage("Chiamata API a: " . str_replace($apiKey, "[HIDDEN]", $url) . " [Metodo: $method]", 'DEBUG');
        
        // Inizializza cURL
        $ch = curl_init();
        
        // Imposta l'URL
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // Imposta le opzioni cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        // In ambiente di sviluppo, disabilita la verifica SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Aggiungi il corpo della richiesta per POST o PUT
        if (($method === 'POST' || $method === 'PUT') && $data !== null) {
            $postData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            // Log dei dati inviati (limitato per sicurezza)
            logMessage("Dati API inviati: " . substr(json_encode($data), 0, 500) . "...", 'DEBUG');
        }
        
        // Esegui la richiesta
        $response = curl_exec($ch);
        
        // Ottieni informazioni sulla richiesta
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Log della risposta
        logMessage("Risposta HTTP: $httpCode", 'DEBUG');
        
        if ($error) {
            logMessage("Errore cURL: $error", 'ERROR');
            throw new Exception("Errore nella chiamata API: $error");
        }
        
        // Parsa la risposta JSON
        $result = json_decode($response, true);
        
        // Verifica la decodifica JSON
        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            logMessage("Errore JSON: " . json_last_error_msg() . " - Risposta: " . substr($response, 0, 200), 'ERROR');
            throw new Exception("Risposta non valida dall'API: " . json_last_error_msg());
        }
        
        // Verifica errori API
        if (isset($result['error'])) {
            logMessage("Errore API: " . $result['error'], 'ERROR');
            throw new Exception("Errore API: " . $result['error']);
        }
        
        return $result;
    } catch (Exception $e) {
        logMessage("Eccezione API: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'exception' => true
        ];
    }
}
/**
 * Funzione specifica per l'endpoint Suppliers
 * 
 * @return array Lista di fornitori
 */
function fetchSuppliersFromApi() {
    try {
        $response = callSmartyApi('Suppliers', 'list');
        
        // Verifica se la risposta contiene un errore
        if (isset($response['success']) && $response['success'] === false) {
            logMessage("Errore in fetchSuppliersFromApi: " . ($response['message'] ?? 'Errore sconosciuto'), 'ERROR');
            return [];
        }
        
        // Caso 1: La risposta è già un array di fornitori (come visto nel debug)
        if (is_array($response) && isset($response[0]) && isset($response[0]['business_name'])) {
            return $response;
        }
        
        // Caso 2: La risposta ha una struttura Data.Records (come da documentazione precedente)
        if (isset($response['Data']) && isset($response['Data']['Records']) && is_array($response['Data']['Records'])) {
            return $response['Data']['Records'];
        }
        
        // Caso 3: Altri possibili formati di risposta
        if (isset($response['Records']) && is_array($response['Records'])) {
            return $response['Records'];
        }
        
        logMessage("Struttura API fornitori non riconosciuta: " . json_encode(array_slice((array)$response, 0, 3)) . "...", 'WARNING');
        return [];
    } catch (Exception $e) {
        logMessage("Errore nel recupero dei fornitori: " . $e->getMessage(), 'ERROR');
        return [];
    }
}
/**
 * Funzione specifica per l'endpoint Brands
 * 
 * @return array Lista di marche
 */
function fetchBrandsFromApi() {
    try {
        $response = callSmartyApi('Brands', 'list');
        
        // Verifica se la risposta contiene un errore
        if (isset($response['success']) && $response['success'] === false) {
            logMessage("Errore in fetchBrandsFromApi: " . ($response['message'] ?? 'Errore sconosciuto'), 'ERROR');
            return [];
        }
        
        // Caso 1: La risposta è già un array di marche
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['name']) || 
            isset($response[0]['Name']) ||
            isset($response[0]['id'])
        )) {
            return $response;
        }
        
        // Caso 2: Formato con Data.Records 
        if (isset($response['Data']) && isset($response['Data']['Records']) && is_array($response['Data']['Records'])) {
            return $response['Data']['Records'];
        }
        
        // Caso 3: Altri formati possibili
        if (isset($response['Records']) && is_array($response['Records'])) {
            return $response['Records'];
        }
        
        logMessage("Struttura API marche non riconosciuta: " . json_encode(array_slice((array)$response, 0, 3)) . "...", 'WARNING');
        return [];
    } catch (Exception $e) {
        logMessage("Errore nel recupero delle marche: " . $e->getMessage(), 'ERROR');
        return [];
    }
}
/**
 * Funzione specifica per l'endpoint Taxes
 * 
 * @return array Lista di aliquote IVA
 */
function fetchTaxesFromApi() {
    try {
        $response = callSmartyApi('Taxes', 'list');
        
        // Verifica se la risposta contiene un errore
        if (isset($response['success']) && $response['success'] === false) {
            logMessage("Errore in fetchTaxesFromApi: " . ($response['message'] ?? 'Errore sconosciuto'), 'ERROR');
            return [];
        }
        
        // Caso 1: La risposta è già un array di aliquote
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['value']) || 
            isset($response[0]['Value']) ||
            isset($response[0]['name']) ||
            isset($response[0]['Name'])
        )) {
            return $response;
        }
        
        // Caso 2: Formato con Data.Records 
        if (isset($response['Data']) && isset($response['Data']['Records']) && is_array($response['Data']['Records'])) {
            return $response['Data']['Records'];
        }
        
        // Caso 3: Altri formati possibili
        if (isset($response['Records']) && is_array($response['Records'])) {
            return $response['Records'];
        }
        
        logMessage("Struttura API aliquote non riconosciuta: " . json_encode(array_slice((array)$response, 0, 3)) . "...", 'WARNING');
        return [];
    } catch (Exception $e) {
        logMessage("Errore nel recupero delle aliquote IVA: " . $e->getMessage(), 'ERROR');
        return [];
    }
}
/**
 * Funzione per recuperare attributi di prodotto (stagioni e generi)
 * 
 * @return array Attributi dei prodotti
 */
function fetchProductAttributesFromApi() {
    try {
        $response = callSmartyApi('ProductAttributes', 'list');
        
        // Verifica se la risposta contiene un errore
        if (isset($response['success']) && $response['success'] === false) {
            logMessage("Errore in fetchProductAttributesFromApi: " . ($response['message'] ?? 'Errore sconosciuto'), 'ERROR');
            return [];
        }
        
        // Caso 1: La risposta è un array di attributi
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['name']) || 
            isset($response[0]['Name']) ||
            isset($response[0]['Values']) ||
            isset($response[0]['values'])
        )) {
            return $response;
        }
        
        // Caso 2: Formato con Data.Attributes
        if (isset($response['Data']) && isset($response['Data']['Attributes']) && is_array($response['Data']['Attributes'])) {
            return $response['Data']['Attributes'];
        }
        
        // Caso 3: Altri formati possibili
        if (isset($response['Attributes']) && is_array($response['Attributes'])) {
            return $response['Attributes'];
        }
        
        logMessage("Struttura API attributi prodotto non riconosciuta: " . json_encode(array_slice((array)$response, 0, 3)) . "...", 'WARNING');
        return [];
    } catch (Exception $e) {
        logMessage("Errore nel recupero degli attributi prodotto: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Sanitizza l'input dell'utente
 *
 * @param string $input Input da sanitizzare
 * @return string Input sanitizzato
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        // Se è un array, sanitizza ogni elemento
        return array_map('sanitizeInput', $input);
    }
    
    // Assicurati che l'input sia una stringa
    $input = (string)$input;
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

/**
 * Controlla se una tabella esiste nel database
 *
 * @param string $tableName Nome della tabella
 * @return bool True se la tabella esiste, false altrimenti
 */
function tableExists($tableName) {
    try {
        $conn = getDbConnection();
        
        if ($conn === null) {
            return false;
        }
        
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        logMessage("Errore nel controllo dell'esistenza della tabella $tableName: " . $e->getMessage(), 'ERROR');
        return false;
    }
}