<?php
// Abilita la visualizzazione degli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Informazioni di sistema
echo "<h1>Diagnostica di Sistema</h1>";

// Test di connessione al database
try {
    $conn = getDbConnection();
    echo "<h2>Connessione al Database</h2>";
    echo "<p style='color:green'>✅ Connessione riuscita</p>";
} catch (Exception $e) {
    echo "<h2>Errore Connessione Database</h2>";
    echo "<p style='color:red'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Verifica delle funzioni critiche
$criticalFunctions = ['getDbConnection', 'logMessage', 'isLoggedIn'];
echo "<h2>Verifica Funzioni Critiche</h2>";
foreach ($criticalFunctions as $func) {
    echo "<p>" . $func . ": " . (function_exists($func) ? "✅ Esistente" : "❌ Non trovata") . "</p>";
}