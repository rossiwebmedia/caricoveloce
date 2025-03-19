<?php
/**
 * Wrapper per index.php
 * Questo file gestisce gli errori e previene i problemi di caricamento della pagina principale
 */

// Includi il gestore degli errori
require_once 'error_handler.php';

try {
    // Inizia l'output buffering per prevenire problemi di header già inviati
    ob_start();
    
    // Includi il file index originale
    require_once 'index.php';
    
    // Invia l'output
    ob_end_flush();
} catch (Exception $e) {
    // In caso di errore, cancella qualsiasi output precedente
    ob_end_clean();
    
    // Log dettagliato dell'errore
    error_log("Errore critico in index.php: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Linea: " . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Mostra una pagina di errore più user-friendly
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Errore Critico</title>
    </head>
    <body>
        <h1>Errore Interno del Server</h1>
        <p>Si è verificato un errore critico. Il team tecnico è stato avvisato.</p>
        <pre><?php echo htmlspecialchars($e->getMessage()); ?></pre>
    </body>
    </html>
    <?php
    exit(1);
}