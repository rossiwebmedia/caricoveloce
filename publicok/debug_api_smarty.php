<?php
require_once 'config.php';
require_once 'functions.php';

// Protezione base dell'accesso
if (!isLoggedIn()) {
    die("Accesso non autorizzato");
}

// Formattazione dell'output
echo "<html><head><title>Debug API Smarty</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h1 { color: #333; }
    h2 { color: #555; margin-top: 30px; }
    pre { background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto; max-height: 300px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
</style>";
echo "</head><body>";
echo "<h1>Debug API Smarty</h1>";

// Informazioni sulla configurazione API
echo "<h2>1. Configurazione API</h2>";
try {
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT * FROM impostazioni_api WHERE predefinito = 1");
    $apiConfig = $stmt->fetch();
    
    if ($apiConfig) {
        echo "<p class='success'>✓ Configurazione API trovata:</p>";
        echo "<ul>";
        echo "<li><strong>Nome:</strong> " . htmlspecialchars($apiConfig['nome']) . "</li>";
        echo "<li><strong>URL:</strong> " . htmlspecialchars($apiConfig['api_url']) . "</li>";
        echo "<li><strong>API Key:</strong> " . substr(htmlspecialchars($apiConfig['api_key']), 0, 5) . "..." . "</li>";
        echo "</ul>";
        
        // Salva l'API URL e Key per uso successivo
        $apiUrl = rtrim($apiConfig['api_url'], '/');
        $apiKey = $apiConfig['api_key'];
    } else {
        echo "<p class='error'>✗ Nessuna configurazione API predefinita trovata!</p>";
        // Valori predefiniti per evitare errori
        $apiUrl = 'https://www.gestionalesmarty.com/titanium/V2/Api';
        $apiKey = '';
    }
} catch (Exception $e) {
    echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
    // Valori predefiniti in caso di errore
    $apiUrl = 'https://www.gestionalesmarty.com/titanium/V2/Api';
    $apiKey = '';
}

// Test risposta API Raw (versione dettagliata)
echo "<h2>2. Test RAW dell'API Smarty</h2>";
echo "<p>Chiamata diretta all'endpoint 'Suppliers' con azione 'list'...</p>";

try {
    // Costruisci URL per il test
    $testUrl = $apiUrl . "/Suppliers";
    $urlWithParams = $testUrl . "?ApiKey=" . urlencode($apiKey) . "&action=list";
    
    echo "<p>URL chiamata con ApiKey: <code>" . str_replace($apiKey, "*****", $urlWithParams) . "</code></p>";
    
    // Chiamata diretta con cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlWithParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    echo "<p>HTTP Status: <strong>" . $httpCode . "</strong></p>";
    echo "<p>Content-Type: <strong>" . $contentType . "</strong></p>";
    
    if ($error) {
        echo "<p class='error'>Errore cURL: " . $error . "</p>";
    } else {
        echo "<p>Risposta RAW (primi 500 caratteri):</p>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . (strlen($response) > 500 ? "..." : "") . "</pre>";
        
        // Prova a decodificare il JSON
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            echo "<p class='success'>✓ JSON valido</p>";
            echo "<p>Struttura JSON:</p>";
            echo "<pre>";
            print_r($decoded);
            echo "</pre>";
            
            // Verifica la struttura attesa
            if (isset($decoded['Data']) && isset($decoded['Data']['Records']) && is_array($decoded['Data']['Records'])) {
                echo "<p class='success'>✓ Struttura attesa (Data.Records) trovata!</p>";
                echo "<p>Numero di fornitori: " . count($decoded['Data']['Records']) . "</p>";
            } else {
                echo "<p class='warning'>! Struttura Data.Records non trovata!</p>";
            }
        } else {
            echo "<p class='error'>✗ Risposta non è JSON valido: " . json_last_error_msg() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
}

// Test della funzione fetchSuppliersFromApi
echo "<h2>3. Test funzione fetchSuppliersFromApi</h2>";
try {
    echo "<p>Chiamata alla funzione fetchSuppliersFromApi()...</p>";
    $startTime = microtime(true);
    $suppliers = fetchSuppliersFromApi();
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000); // In millisecondi
    
    echo "<p>Tempo di esecuzione: " . $executionTime . " ms</p>";
    echo "<p>Numero di fornitori ottenuti: " . count($suppliers) . "</p>";
    echo "<p>Primi 5 fornitori:</p>";
    echo "<pre>";
    print_r(array_slice($suppliers, 0, 5, true));
    echo "</pre>";
    
    if (count($suppliers) === 0) {
        echo "<p class='error'>! Nessun fornitore trovato!</p>";
    } else if (count($suppliers) === 3 && isset($suppliers[0]['BusinessName']) && $suppliers[0]['BusinessName'] === 'Fornitore Standard 1') {
        echo "<p class='warning'>! La funzione sta restituendo i fornitori predefiniti, non quelli dell'API</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
}

// Includi api_update_cache.php per accesso alle funzioni
$updateCachePath = __DIR__ . '/api_update_cache.php';
if (file_exists($updateCachePath)) {
    include_once $updateCachePath;
}

// Test importazione in cache
echo "<h2>4. Test importazione nella cache</h2>";
try {
    echo "<p>Chiamata diretta alla funzione updateSuppliersCache()...</p>";
    
    // Verifica che la funzione sia disponibile
    if (function_exists('updateSuppliersCache')) {
        $result = updateSuppliersCache();
        echo "<p>Risultato dell'aggiornamento:</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // Verifica il contenuto della cache
        echo "<p>Contenuto attuale della tabella cache_fornitori:</p>";
        $stmt = $conn->query("SELECT * FROM cache_fornitori LIMIT 10");
        $cachedSuppliers = $stmt->fetchAll();
        echo "<pre>";
        print_r($cachedSuppliers);
        echo "</pre>";
    } else {
        echo "<p class='error'>Funzione updateSuppliersCache non trovata! Verifica il file api_update_cache.php</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
}

echo "</body></html>";