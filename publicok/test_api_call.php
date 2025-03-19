<?php
require_once 'config.php';
require_once 'functions.php';

// Solo per amministratori
if (!isLoggedIn()) {
    die("Accesso non autorizzato");
}

echo "<h1>Test API Smarty Diretto</h1>";

$conn = getDbConnection();
$stmt = $conn->query("SELECT api_key, api_url FROM impostazioni_api WHERE predefinito = 1 LIMIT 1");
$apiConfig = $stmt->fetch();

if (!$apiConfig) {
    die("Nessuna configurazione API trovata");
}

$apiKey = $apiConfig['api_key'];
$apiUrl = rtrim($apiConfig['api_url'], '/');

// Costruisci URL per il test
$testUrl = $apiUrl . "/Suppliers?ApiKey=" . urlencode($apiKey) . "&action=list";

echo "<p>URL chiamata: <code>" . str_replace($apiKey, "*****", $testUrl) . "</code></p>";

// Chiamata diretta con cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "<p>HTTP Status: " . $httpCode . "</p>";

if ($error) {
    echo "<p style='color:red'>Errore: " . $error . "</p>";
} else {
    echo "<p>Risposta:</p>";
    echo "<pre>";
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse !== null) {
        print_r($jsonResponse);
    } else {
        echo htmlspecialchars($response);
    }
    echo "</pre>";
}