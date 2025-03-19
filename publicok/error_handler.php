<?php
/**
 * Gestore personalizzato degli errori per migliorare la diagnostica
 * Includi questo file all'inizio di ogni script PHP principale
 */

// Previeni inclusioni multiple
if (defined('ERROR_HANDLER_LOADED')) {
    return;
}
define('ERROR_HANDLER_LOADED', true);

// Includi il file di configurazione se non è già stato incluso
if (!defined('CONFIG_LOADED')) {
    require_once 'config.php';
}

// Define se siamo in modalità debug o produzione
// Imposta su true durante lo sviluppo, false in produzione
define('DEBUG_MODE', true);

// Configura la gestione degli errori in base all'ambiente
if (DEBUG_MODE) {
    // In ambiente di sviluppo, mostra tutti gli errori
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // In produzione, nascondi gli errori ma registrali nei log
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}

// Assicurati che il log degli errori sia attivo
ini_set('log_errors', 1);
ini_set('error_log', LOGS_DIR . '/php_errors.log');

/**
 * Gestore personalizzato degli errori
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Ottieni il tipo di errore come testo
    $errorType = getErrorTypeText($errno);
    
    // Costruisci il messaggio di log
    $errorMessage = "[$errorType] $errstr in $errfile on line $errline";
    
    // Registra l'errore nel log
    logMessage($errorMessage, 'ERROR');
    
    // Se siamo in debug mode, mostra l'errore
    if (DEBUG_MODE) {
        echo "<div style='color:red; border:1px solid red; padding:10px; margin:10px; font-family:monospace;'>";
        echo "<strong>$errorType</strong>: $errstr<br>";
        echo "File: $errfile, Line: $errline";
        echo "</div>";
    }
    
    // Non interrompere l'esecuzione per avvisi e notice
    if ($errno == E_ERROR || $errno == E_PARSE || $errno == E_CORE_ERROR || 
        $errno == E_COMPILE_ERROR || $errno == E_USER_ERROR) {
        exit(1);
    }
    
    // Restituisci false per continuare con la gestione standard di PHP
    return false;
}

/**
 * Gestore personalizzato delle eccezioni
 */
function customExceptionHandler($exception) {
    // Ottieni le informazioni sull'eccezione
    $message = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTraceAsString();
    
    // Costruisci il messaggio di log
    $errorMessage = "Uncaught Exception: $message in $file on line $line\nStack trace: $trace";
    
    // Registra l'eccezione nel log
    logMessage($errorMessage, 'CRITICAL');
    
    // Se siamo in debug mode, mostra l'eccezione
    if (DEBUG_MODE) {
        echo "<div style='color:white; background-color:#990000; border:2px solid black; padding:10px; margin:10px; font-family:monospace;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<p><strong>Message:</strong> $message</p>";
        echo "<p><strong>File:</strong> $file</p>";
        echo "<p><strong>Line:</strong> $line</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre>" . htmlspecialchars($trace) . "</pre>";
        echo "</div>";
    } else {
        // In produzione, mostra un messaggio generico
        echo "<div style='text-align:center; margin-top:100px;'>";
        echo "<h2>Si è verificato un errore</h2>";
        echo "<p>Ci scusiamo per l'inconveniente. Il nostro team tecnico è stato avvisato.</p>";
        echo "<p><a href='index.php'>Torna alla home</a></p>";
        echo "</div>";
    }
    
    exit(1);
}

/**
 * Funzione per convertire il codice di errore in testo
 */
function getErrorTypeText($errno) {
    switch ($errno) {
        case E_ERROR:             return 'E_ERROR';
        case E_WARNING:           return 'E_WARNING';
        case E_PARSE:             return 'E_PARSE';
        case E_NOTICE:            return 'E_NOTICE';
        case E_CORE_ERROR:        return 'E_CORE_ERROR';
        case E_CORE_WARNING:      return 'E_CORE_WARNING';
        case E_COMPILE_ERROR:     return 'E_COMPILE_ERROR';
        case E_COMPILE_WARNING:   return 'E_COMPILE_WARNING';
        case E_USER_ERROR:        return 'E_USER_ERROR';
        case E_USER_WARNING:      return 'E_USER_WARNING';
        case E_USER_NOTICE:       return 'E_USER_NOTICE';
        case E_STRICT:            return 'E_STRICT';
        case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
        case E_DEPRECATED:        return 'E_DEPRECATED';
        case E_USER_DEPRECATED:   return 'E_USER_DEPRECATED';
        default:                  return 'UNKNOWN_ERROR';
    }
}

// Imposta i gestori personalizzati
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Gestore per gli errori fatali
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || 
                            $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        
        // Pulisci eventuali output precedenti
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Logga l'errore fatale
        $errorType = getErrorTypeText($error['type']);
        logMessage("FATAL ERROR [$errorType]: {$error['message']} in {$error['file']} on line {$error['line']}", 'CRITICAL');
        
        // Mostra messaggio appropriato in base all'ambiente
        if (DEBUG_MODE) {
            echo "<div style='color:white; background-color:#990000; border:2px solid black; padding:10px; margin:10px; font-family:monospace;'>";
            echo "<h3>Fatal Error</h3>";
            echo "<p><strong>Type:</strong> $errorType</p>";
            echo "<p><strong>Message:</strong> {$error['message']}</p>";
            echo "<p><strong>File:</strong> {$error['file']}</p>";
            echo "<p><strong>Line:</strong> {$error['line']}</p>";
            echo "</div>";
        } else {
            // In produzione, mostra un messaggio generico
            echo "<div style='text-align:center; margin-top:100px;'>";
            echo "<h2>Si è verificato un errore critico</h2>";
            echo "<p>Ci scusiamo per l'inconveniente. Il nostro team tecnico è stato avvisato.</p>";
            echo "<p><a href='index.php'>Torna alla home</a></p>";
            echo "</div>";
        }
    }
});