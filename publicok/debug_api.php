<?php
// Crea un nuovo file chiamato debug_api.php
require_once 'config.php';
require_once 'functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Testa una chiamata API
echo "<h2>Debug chiamata API Suppliers/list</h2>";
$response = callSmartyApi('Suppliers/list', 'GET');
echo "<pre>";
print_r($response);
echo "</pre>";

echo "<h2>Debug chiamata API ProductAttributes/list</h2>";
$response = callSmartyApi('ProductAttributes/list', 'GET');
echo "<pre>";
print_r($response);
echo "</pre>";

// Verifica se le stagioni sono state salvate nella cache
echo "<h2>Contenuto della tabella cache_stagioni</h2>";
try {
    $conn = getDbConnection();
    $tableExists = $conn->query("SHOW TABLES LIKE 'cache_stagioni'")->rowCount() > 0;
    echo "Tabella cache_stagioni esiste: " . ($tableExists ? 'SI' : 'NO') . "<br>";
    
    if ($tableExists) {
        $stmt = $conn->query("SELECT * FROM cache_stagioni");
        $stagioni = $stmt->fetchAll();
        echo "<pre>";
        print_r($stagioni);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}

// Verifica i fornitori nella cache
echo "<h2>Contenuto della tabella cache_fornitori</h2>";
try {
    $conn = getDbConnection();
    $tableExists = $conn->query("SHOW TABLES LIKE 'cache_fornitori'")->rowCount() > 0;
    echo "Tabella cache_fornitori esiste: " . ($tableExists ? 'SI' : 'NO') . "<br>";
    
    if ($tableExists) {
        $stmt = $conn->query("SELECT * FROM cache_fornitori");
        $fornitori = $stmt->fetchAll();
        echo "<pre>";
        print_r($fornitori);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}

// Verifica la funzione getStagioni
echo "<h2>Output della funzione getStagioni()</h2>";
try {
    if (function_exists('getStagioni')) {
        $stagioni = getStagioni();
        print_r($stagioni);
    } else {
        echo "La funzione getStagioni() non esiste";
    }
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}