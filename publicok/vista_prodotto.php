<?php
/**
 * Vista dettaglio prodotto con supporto formato JSON
 */
require_once 'init.php';
require_once 'config.php';
require_once 'functions.php';

// Verifica che sia stato fornito un ID valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        // Restituisci errore in formato JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'ID prodotto non valido'
        ]);
    } else {
        // Reindirizza alla pagina di gestione prodotti
        $_SESSION['notification'] = [
            'type' => 'danger',
            'message' => 'ID prodotto non valido'
        ];
        header('Location: gestione_prodotti.php');
    }
    exit;
}

$prodottoId = intval($_GET['id']);
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Connessione al database
$conn = getDbConnection();

// Ottieni le informazioni sul prodotto
$stmt = $conn->prepare("
    SELECT p.*, u.username 
    FROM prodotti p
    LEFT JOIN utenti u ON p.utente_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$prodottoId]);
$prodotto = $stmt->fetch();

if (!$prodotto) {
    if ($format === 'json') {
        // Restituisci errore in formato JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Prodotto non trovato'
        ]);
    } else {
        // Reindirizza alla pagina di gestione prodotti
        $_SESSION['notification'] = [
            'type' => 'danger',
            'message' => 'Prodotto non trovato'
        ];
        header('Location: gestione_prodotti.php');
    }
    exit;
}

// Se il formato richiesto è JSON, restituisci i dati in JSON
if ($format === 'json') {
    // Prepara i dati per il formato JSON
    $result = [
        'success' => true,
        'prodotto' => $prodotto
    ];
    
    // Aggiungi dati formattati per la visualizzazione
    $result['prodotto']['creato_il_formatted'] = date('d/m/Y H:i', strtotime($prodotto['creato_il']));
    
    // Mappa stati del prodotto
    $statoLabels = [
        'bozza' => '<span class="badge bg-warning text-dark">Bozza</span>',
        'pubblicato' => '<span class="badge bg-success">Pubblicato</span>',
        'errore' => '<span class="badge bg-danger">Errore</span>'
    ];
    $result['prodotto']['stato_html'] = $statoLabels[$prodotto['stato']] ?? $prodotto['stato'];
    
    // Mappa tipologie per la visualizzazione
    $tipologie = [
        'uomo' => 'Abbigliamento Uomo',
        'donna' => 'Abbigliamento Donna',
        'scarpe_uomo' => 'Scarpe Uomo',
        'scarpe_donna' => 'Scarpe Donna',
        'accessori' => 'Accessori'
    ];
    $result['prodotto']['tipologia_nome'] = $tipologie[$prodotto['tipologia']] ?? $prodotto['tipologia'];
    
    // Ottieni il prodotto parent se il prodotto è una variante
    if (!empty($prodotto['parent_sku'])) {
        $stmt = $conn->prepare("SELECT id FROM prodotti WHERE sku = ?");
        $stmt->execute([$prodotto['parent_sku']]);
        $result['prodotto']['parent_id'] = $stmt->fetchColumn();
    }
    
    // Ottieni le varianti del prodotto se è un prodotto parent
    $varianti = [];
    $isParent = empty($prodotto['parent_sku']);
    
    if ($isParent) {
        $stmt = $conn->prepare("SELECT * FROM prodotti WHERE parent_sku = ? ORDER BY colore, taglia");
        $stmt->execute([$prodotto['sku']]);
        $varianti = $stmt->fetchAll();
        
        // Aggiungi dati formattati per le varianti
        foreach ($varianti as &$variante) {
            $variante['stato_html'] = $statoLabels[$variante['stato']] ?? $variante['stato'];
        }
        
        $result['varianti'] = $varianti;
    }
    
    // Verifica se ci sono log di sincronizzazione per questo prodotto
    $stmt = $conn->prepare("
        SELECT * FROM log_sincronizzazione
        WHERE prodotto_id = ?
        ORDER BY creato_il DESC
        LIMIT 10
    ");
    $stmt->execute([$prodottoId]);
    $logs = $stmt->fetchAll();
    
    // Aggiungi i dati formattati per i log
    if (!empty($logs)) {
        foreach ($logs as &$log) {
            $log['creato_il_formatted'] = date('d/m/Y H:i:s', strtotime($log['creato_il']));
            
            // Formatta l'azione
            switch ($log['azione']) {
                case 'creazione':
                    $log['azione_html'] = '<span class="badge bg-primary">Creazione</span>';
                    break;
                case 'aggiornamento':
                    $log['azione_html'] = '<span class="badge bg-info">Aggiornamento</span>';
                    break;
                case 'eliminazione':
                    $log['azione_html'] = '<span class="badge bg-danger">Eliminazione</span>';
                    break;
                default:
                    $log['azione_html'] = $log['azione'];
            }
        }
        
        $result['logs'] = $logs;
    }
    
    // Restituisci i dati in formato JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Se il formato è HTML, continua con la visualizzazione normale
// Titolo della pagina
$pageTitle = 'Dettaglio Prodotto';

// Include l'header
include 'header.php';

// Mappa stati del prodotto
$statoLabel = [
    'bozza' => '<span class="badge bg-warning text-dark">Bozza</span>',
    'pubblicato' => '<span class="badge bg-success">Pubblicato</span>',
    'errore' => '<span class="badge bg-danger">Errore</span>'
];

// Ottieni le tipologie per la visualizzazione
$tipologie = [
    'uomo' => 'Abbigliamento Uomo',
    'donna' => 'Abbigliamento Donna',
    'scarpe_uomo' => 'Scarpe Uomo',
    'scarpe_donna' => 'Scarpe Donna',
    'accessori' => 'Accessori'
];

// Ottieni le varianti del prodotto se è un prodotto parent
$varianti = [];
$isParent = empty($prodotto['parent_sku']);

if ($isParent) {
    $stmt = $conn->prepare("SELECT * FROM prodotti WHERE parent_sku = ? ORDER BY colore, taglia");
    $stmt->execute([$prodotto['sku']]);
    $varianti = $stmt->fetchAll();
}

// Verifica se ci sono log di sincronizzazione per questo prodotto
$stmt = $conn->prepare("
    SELECT * FROM log_sincronizzazione
    WHERE prodotto_id = ?
    ORDER BY creato_il DESC
    LIMIT 10
");
$stmt->execute([$prodottoId]);
$logs = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-box-seam me-2"></i>
                    <?php echo htmlspecialchars($prodotto['titolo']); ?>
                </h5>
                <div>
                    <a href="gestione_prodotti.php" class="btn btn-sm btn-light">
                        <i class="bi bi-arrow-left"></i> Torna alla lista
                    </a>
                    <?php if ($prodotto['stato'] !== 'pubblicato'): ?>
                    <button type="button" class="btn btn-sm btn-success ms-2" id="sincronizza-btn" onclick="syncProduct(<?php echo $prodottoId; ?>)">
                        <i class="bi bi-cloud-upload"></i> Sincronizza
                    </button>
                    <?php endif; ?>
                    <a href="inserimento_prodotti.php?edit=<?php echo $prodottoId; ?>" class="btn btn-sm btn-warning ms-2">
                        <i class="bi bi-pencil"></i> Modifica
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Informazioni Prodotto</h6>
                        <table class="table">
                            <tr>
                                <th class="w-25">SKU:</th>
                                <td><?php echo htmlspecialchars($prodotto['sku']); ?></td>
                            </tr>
                            <?php if (!$isParent): ?>
                            <tr>
                                <th>Prodotto principale:</th>
                                <td>
                                    <?php 
                                    // Ottieni l'ID del prodotto parent
                                    $stmt = $conn->prepare("SELECT id FROM prodotti WHERE sku = ?");
                                    $stmt->execute([$prodotto['parent_sku']]);
                                    $parentId = $stmt->fetchColumn();
                                    if ($parentId) {
                                        echo '<a href="vista_prodotto.php?id=' . $parentId . '">';
                                        echo htmlspecialchars($prodotto['parent_sku']);
                                        echo '</a>';
                                    } else {
                                        echo htmlspecialchars($prodotto['parent_sku']);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Tipologia:</th>
                                <td><?php echo htmlspecialchars($tipologie[$prodotto['tipologia']] ?? $prodotto['tipologia']); ?></td>
                            </tr>
                            <tr>
                                <th>Marca:</th>
                                <td><?php echo htmlspecialchars($prodotto['marca']); ?></td>
                            </tr>
                            <tr>
                                <th>Fornitore:</th>
                                <td><?php echo htmlspecialchars($prodotto['fornitore']); ?></td>
                            </tr>
                            <?php if (!empty($prodotto['taglia'])): ?>
                            <tr>
                                <th>Taglia:</th>
                                <td><?php echo htmlspecialchars($prodotto['taglia']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($prodotto['colore'])): ?>
                            <tr>
                                <th>Colore:</th>
                                <td><?php echo htmlspecialchars($prodotto['colore']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>EAN:</th>
                                <td><?php echo htmlspecialchars($prodotto['ean']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Prezzi e Stato</h6>
                        <table class="table">
                            <tr>
                                <th class="w-25">Prezzo Acquisto:</th>
                                <td>€<?php echo number_format($prodotto['prezzo_acquisto'], 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <th>Prezzo Vendita:</th>
                                <td>€<?php echo number_format($prodotto['prezzo_vendita'], 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <th>Aliquota IVA:</th>
                                <td><?php echo $prodotto['aliquota_iva']; ?>%</td>
                            </tr>
                            <tr>
                                <th>Stato:</th>
                                <td><?php echo $statoLabel[$prodotto['stato']] ?? $prodotto['stato']; ?></td>
                            </tr>
                            <?php if ($prodotto['stato'] === 'errore' && !empty($prodotto['messaggio_errore'])): ?>
                            <tr>
                                <th>Errore:</th>
                                <td class="text-danger"><?php echo htmlspecialchars($prodotto['messaggio_errore']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Creato da:</th>
                                <td><?php echo htmlspecialchars($prodotto['username'] ?? 'Utente sconosciuto'); ?></td>
                            </tr>
                            <tr>
                                <th>Data creazione:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($prodotto['creato_il'])); ?></td>
                            </tr>
                            <?php if (!empty($prodotto['smarty_id'])): ?>
                            <tr>
                                <th>ID Smarty:</th>
                                <td><?php echo $prodotto['smarty_id']; ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($varianti)): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">Varianti (<?php echo count($varianti); ?>)</h6>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Titolo</th>
                                        <th>Taglia</th>
                                        <th>Colore</th>
                                        <th>EAN</th>
                                        <th>Prezzo</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($varianti as $variante): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($variante['sku']); ?></td>
                                        <td><?php echo htmlspecialchars($variante['titolo']); ?></td>
                                        <td><?php echo htmlspecialchars($variante['taglia']); ?></td>
                                        <td><?php echo htmlspecialchars($variante['colore']); ?></td>
                                        <td><?php echo htmlspecialchars($variante['ean']); ?></td>
                                        <td>€<?php echo number_format($variante['prezzo_vendita'], 2, ',', '.'); ?></td>
                                        <td><?php echo $statoLabel[$variante['stato']] ?? $variante['stato']; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="vista_prodotto.php?id=<?php echo $variante['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($variante['stato'] !== 'pubblicato'): ?>
                                                <button type="button" class="btn btn-outline-success" onclick="syncProduct(<?php echo $variante['id']; ?>)">
                                                    <i class="bi bi-cloud-upload"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($logs)): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">Log di Sincronizzazione</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Azione</th>
                                        <th>Risultato</th>
                                        <th>Utente</th>
                                        <th>Dettagli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['creato_il'])); ?></td>
                                        <td>
                                            <?php
                                            switch ($log['azione']) {
                                                case 'creazione':
                                                    echo '<span class="badge bg-primary">Creazione</span>';
                                                    break;
                                                case 'aggiornamento':
                                                    echo '<span class="badge bg-info">Aggiornamento</span>';
                                                    break;
                                                case 'eliminazione':
                                                    echo '<span class="badge bg-danger">Eliminazione</span>';
                                                    break;
                                                default:
                                                    echo $log['azione'];
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($log['riuscito']): ?>
                                                <span class="badge bg-success">Successo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Fallito</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            // Ottieni il nome dell'utente
                                            $stmt = $conn->prepare("SELECT username FROM utenti WHERE id = ?");
                                            $stmt->execute([$log['utente_id']]);
                                            $username = $stmt->fetchColumn();
                                            echo htmlspecialchars($username ?? 'Utente sconosciuto');
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewLogDetails('<?php echo $log['id']; ?>')">
                                                <i class="bi bi-info-circle"></i> Dettagli
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Includere le funzioni JavaScript necessarie per sincronizzare e visualizzare i log
<?php include 'assets/js/prodotti.js'; ?>
</script>

<?php
// Include il footer
include 'footer.php';
?>