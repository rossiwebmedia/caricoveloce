<?php
/**
 * Configurazione del database e altre impostazioni
 */

// Previeni inclusioni multiple
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

// Avvio della sessione (solo se non già avviata)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'h6xt66s7_carico');
define('DB_USER', 'h6xt66s7_carico');  // Modifica con il tuo utente MySQL
define('DB_PASS', 's,Grh0U?IKkx');     // Modifica con la tua password MySQL

// Impostazioni generali
define('SITE_NAME', 'Gestione Prodotti Smarty');
define('BASE_URL', 'http://www.rwmgest.it/caricoveloce');  // Assicurati che questo URL sia corretto

// URL dell'API Smarty
define('SMARTY_API_BASE_URL', 'https://www.gestionalesmarty.com/titanium/V2/Api/');

// Directory
define('ROOT_DIR', dirname(__FILE__));
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('LOGS_DIR', ROOT_DIR . '/logs');

// Gestione degli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Assicurati che esistano le directory necessarie
if (!file_exists(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0755, true);
}
if (!file_exists(LOGS_DIR)) {
    @mkdir(LOGS_DIR, 0755, true);
}

// Timezone
date_default_timezone_set('Europe/Rome');

// Funzione per la connessione al database
function getDbConnection() {
    static $conn;
    
    if ($conn === null) {
        try {
            $conn = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // Log della connessione riuscita
            if (function_exists('logMessage')) {
                logMessage('Connessione al database riuscita', 'DEBUG');
            }
            
            return $conn;
        } catch (PDOException $e) {
            // Log dettagliato dell'errore
            $errorMessage = "Errore di connessione al database: " . $e->getMessage() . 
                            "\nHost: " . DB_HOST . 
                            "\nDatabase: " . DB_NAME . 
                            "\nUtente: " . DB_USER;
            
            if (function_exists('logMessage')) {
                logMessage($errorMessage, 'CRITICAL');
            } else {
                error_log($errorMessage);
            }
            
            // In caso di errore, mostra un messaggio più informativo
            die("Errore di connessione al database. Controlla i log per maggiori dettagli.");
        }
    }
    
    return $conn;
}

// Funzione per il logging
function logMessage($message, $level = 'INFO') {
    $logFile = LOGS_DIR . '/app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // Assicurati che la directory esista
    if (!file_exists(LOGS_DIR)) {
        @mkdir(LOGS_DIR, 0755, true);
    }
    
    // Scrivi il log con gestione degli errori
    if (!@file_put_contents($logFile, $logMessage, FILE_APPEND)) {
        // Se non è possibile scrivere il log, usa error_log come fallback
        error_log("Impossibile scrivere nel file di log. Messaggio originale: " . $message);
    }
}

// Funzione per verificare se l'utente è loggato
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Funzione per verificare se l'utente è admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Funzione per ottenere l'API key predefinita
function getDefaultApiKey() {
    try {
        $conn = getDbConnection();
        
        // Gestisci il caso in cui la connessione abbia fallito
        if ($conn === null) {
            return '';
        }
        
        $stmt = $conn->query("SELECT api_key FROM impostazioni_api WHERE predefinito = 1 LIMIT 1");
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['api_key'];
        }
    } catch (PDOException $e) {
        logMessage("Errore nel recupero dell'API key: " . $e->getMessage(), 'ERROR');
    }
    
    return '';
}