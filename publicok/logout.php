<?php
/**
 * Script per il logout
 */
require_once 'config.php';

// Distruggi la sessione
session_start();
session_unset();
session_destroy();

// Reindirizza alla pagina di login
header('Location: login.php');
exit;
