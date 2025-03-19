<?php
// init.php - File di inizializzazione per tutte le pagine

// Previeni inclusioni multiple
if (defined('INIT_LOADED')) {
    return;
}
define('INIT_LOADED', true);

// Includi i file base necessari
require_once 'config.php';
require_once 'functions.php';

// Log di debug sull'inizializzazione
logMessage("Inizializzazione pagina: " . basename($_SERVER['SCRIPT_NAME']), 'DEBUG');

// Array di pagine pubbliche che non richiedono autenticazione
$public_pages = [
    'login.php',
    'recupera_password.php',
    'reset_password.php',
    'debug.php'  // Aggiungi il tool di debug alla lista delle pagine pubbliche
];

// Ottieni il nome del file corrente
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Se la pagina non è pubblica e l'utente non è autenticato, reindirizza al login
if (!in_array($current_page, $public_pages) && !isLoggedIn()) {
    // Log dell'evento di reindirizzamento (utile per debug)
    logMessage("Reindirizzamento a login.php da: " . $current_page . " - Utente non autenticato", 'INFO');
    
    // Salva l'URL corrente per il redirect dopo il login, se necessario
    if ($current_page != 'login.php' && $current_page != 'index.php') {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    }
    
    header('Location: login.php');
    exit;
}