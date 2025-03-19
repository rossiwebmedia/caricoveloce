<?php
require_once 'config.php';
require_once 'functions.php';

// Titolo della pagina
$pageTitle = 'Dashboard';

// Include l'header
include 'header.php';

// Ottieni statistiche
$conn = getDbConnection();

// Numero totale di prodotti
$stmt = $conn->query("SELECT COUNT(*) as total FROM prodotti WHERE parent_sku IS NULL");
$totalProdotti = $stmt->fetch()['total'];

// Numero totale di varianti
$stmt = $conn->query("SELECT COUNT(*) as total FROM prodotti WHERE parent_sku IS NOT NULL");
$totalVarianti = $stmt->fetch()['total'];

// Prodotti in attesa di pubblicazione
$stmt = $conn->query("SELECT COUNT(*) as total FROM prodotti WHERE stato = 'bozza'");
$prodottiInAttesa = $stmt->fetch()['total'];

// EAN disponibili
$stmt = $conn->query("SELECT COUNT(*) as total FROM codici_ean WHERE utilizzato = 0");
$eanDisponibili = $stmt->fetch()['total'];

// Prodotti recenti
$stmt = $conn->query("SELECT * FROM prodotti WHERE parent_sku IS NULL ORDER BY creato_il DESC LIMIT 5");
$prodottiRecenti = $stmt->fetchAll();

// Ultimi log di sincronizzazione
$stmt = $conn->query("
    SELECT l.*, p.sku, p.titolo
    FROM log_sincronizzazione l
    JOIN prodotti p ON l.prodotto_id = p.id
    ORDER BY l.creato_il DESC 
    LIMIT 10
");
$logRecenti = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Prodotti Totali</h5>
                <h2 class="display-4"><?php echo $totalProdotti; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="gestione_prodotti.php">Visualizza Dettagli</a>
                <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Varianti Totali</h5>
                <h2 class="display-4"><?php echo $totalVarianti; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="gestione_prodotti.php?tipo=varianti">Visualizza Dettagli</a>
                <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">In Attesa</h5>
                <h2 class="display-4"><?php echo $prodottiInAttesa; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="gestione_prodotti.php?stato=bozza">Visualizza Dettagli</a>
                <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">EAN Disponibili</h5>
                <h2 class="display-4"><?php echo $eanDisponibili; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="gestione_ean.php">Visualizza Dettagli</a>
                <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-table me-1"></i>
                Prodotti Aggiunti di Recente
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Titolo</th>
                                <th>Stato</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($prodottiRecenti) > 0): ?>
                                <?php foreach ($prodottiRecenti as $prodotto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prodotto['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($prodotto['titolo']); ?></td>
                                    <td>
                                        <?php if ($prodotto['stato'] === 'pubblicato'): ?>
                                            <span class="badge bg-success">Pubblicato</span>
                                        <?php elseif ($prodotto['stato'] === 'bozza'): ?>
                                            <span class="badge bg-warning text-dark">Bozza</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Errore</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($prodotto['creato_il'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">Nessun prodotto trovato</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">
                <a href="gestione_prodotti.php" class="btn btn-sm btn-primary">Visualizza Tutti</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-list-check me-1"></i>
                Attività Recenti
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Prodotto</th>
                                <th>Azione</th>
                                <th>Stato</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logRecenti) > 0): ?>
                                <?php foreach ($logRecenti as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['sku']); ?></td>
                                    <td>
                                        <?php if ($log['azione'] === 'creazione'): ?>
                                            <span class="badge bg-primary">Creazione</span>
                                        <?php elseif ($log['azione'] === 'aggiornamento'): ?>
                                            <span class="badge bg-info">Aggiornamento</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Eliminazione</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['riuscito']): ?>
                                            <span class="badge bg-success">Successo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Fallito</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($log['creato_il'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">Nessuna attività recente trovata</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-lightning-charge me-1"></i>
                Azioni Rapide
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <a href="inserimento_prodotti.php" class="btn btn-outline-primary btn-lg w-100 py-3">
                            <i class="bi bi-plus-circle fs-2"></i><br>
                            Nuovo Prodotto
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="gestione_ean.php?action=importa" class="btn btn-outline-success btn-lg w-100 py-3">
                            <i class="bi bi-upload fs-2"></i><br>
                            Importa EAN
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="sincronizza.php" class="btn btn-outline-info btn-lg w-100 py-3">
                            <i class="bi bi-arrow-repeat fs-2"></i><br>
                            Sincronizza
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-1"></i>
                Stato Connessione
            </div>
            <div class="card-body">
                <div id="api-status">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border text-primary me-3" role="status">
                            <span class="visually-hidden">Caricamento...</span>
                        </div>
                        <p class="mb-0">Verificando la connessione con Smarty...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Script aggiuntivi per la dashboard
$additionalScripts = <<<EOT
<script>
$(document).ready(function() {
    // Verifica lo stato della connessione API
    $.ajax({
        url: 'api_check.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#api-status').html(`
                    <div class="alert alert-success mb-0" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Connessione a Smarty API attiva.
                    </div>
                `);
            } else {
                $('#api-status').html(`
                    <div class="alert alert-danger mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Errore di connessione a Smarty API: ${response.message}
                    </div>
                `);
            }
        },
        error: function() {
            $('#api-status').html(`
                <div class="alert alert-danger mb-0" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Impossibile verificare lo stato della connessione.
                </div>
            `);
        }
    });
});
</script>
EOT;

// Include il footer
include 'footer.php';
?>
