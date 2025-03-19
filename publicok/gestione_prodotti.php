<?php
ob_start();
require_once 'config.php';
require_once 'functions.php';

// Titolo della pagina
$pageTitle = 'Gestione Prodotti';

// Include l'header
include 'header.php';

// Connessione al database
$conn = getDbConnection();

// Parametri di paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtri
$filters = [];
$filterSql = '';
$filterParams = [];

// Filtro per stato
$stato = isset($_GET['stato']) ? sanitizeInput($_GET['stato']) : '';
if (!empty($stato)) {
    $filters[] = 'p.stato = ?';
    $filterParams[] = $stato;
}

// Filtro per tipologia
$tipologia = isset($_GET['tipologia']) ? sanitizeInput($_GET['tipologia']) : '';
if (!empty($tipologia)) {
    $filters[] = 'p.tipologia = ?';
    $filterParams[] = $tipologia;
}

// Filtro per solo prodotti parent
$soloParent = isset($_GET['solo_parent']) && $_GET['solo_parent'] == '1';
if ($soloParent) {
    $filters[] = 'p.parent_sku IS NULL';
}

// Filtro per ricerca testo
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
if (!empty($search)) {
    $filters[] = '(p.sku LIKE ? OR p.titolo LIKE ? OR p.ean LIKE ?)';
    $filterParams[] = "%{$search}%";
    $filterParams[] = "%{$search}%";
    $filterParams[] = "%{$search}%";
}

// Combina i filtri
if (!empty($filters)) {
    $filterSql = ' WHERE ' . implode(' AND ', $filters);
}

// Conta i prodotti totali con i filtri applicati
$countSql = "SELECT COUNT(*) FROM prodotti p" . $filterSql;
$stmt = $conn->prepare($countSql);
if (!empty($filterParams)) {
    $stmt->execute($filterParams);
} else {
    $stmt->execute();
}
$totalProducts = $stmt->fetchColumn();

// Calcola il numero totale di pagine
$totalPages = ceil($totalProducts / $perPage);

// Ottieni i prodotti con paginazione
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM prodotti WHERE parent_sku = p.sku) as variant_count
        FROM prodotti p
        " . $filterSql . "
        ORDER BY p.creato_il DESC
        LIMIT :perPage OFFSET :offset";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

foreach ($filterParams as $index => $param) {
    $stmt->bindValue($index + 1, $param);
}

$stmt->execute();
$products = $stmt->fetchAll();

// Ottieni le tipologie per il filtro
$stmt = $conn->query("SELECT DISTINCT tipologia FROM prodotti WHERE tipologia != '' ORDER BY tipologia");
$tipologie = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Gestione dell'azione di eliminazione
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $productId = intval($_GET['delete']);
    
    try {
        // Ottieni informazioni sul prodotto prima di eliminarlo
        $stmt = $conn->prepare("SELECT sku, parent_sku FROM prodotti WHERE id = ?");
        $stmt->execute([$productId]);
        $productToDelete = $stmt->fetch();
        
        if ($productToDelete) {
            // Se è un prodotto parent, elimina anche le varianti
            if (empty($productToDelete['parent_sku'])) {
                $stmt = $conn->prepare("DELETE FROM prodotti WHERE parent_sku = ?");
                $stmt->execute([$productToDelete['sku']]);
            }
            
            // Elimina il prodotto
            $stmt = $conn->prepare("DELETE FROM prodotti WHERE id = ?");
            $stmt->execute([$productId]);
            
            // Reindirizza per evitare il ricaricamento del form
            echo '<script>window.location.href = "gestione_prodotti.php?deleted=1";</script>';
exit;
        }
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'danger',
            'message' => "Errore durante l'eliminazione del prodotto: " . $e->getMessage()
        ];
    }
}

// Messaggio di conferma eliminazione
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo 'Prodotto eliminato con successo.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filtri</h5>
                <form action="gestione_prodotti.php" method="get" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Ricerca:</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="SKU, Titolo o EAN">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="tipologia" class="form-label">Tipologia:</label>
                        <select class="form-control" id="tipologia" name="tipologia">
                            <option value="">Tutte le tipologie</option>
                            <?php foreach ($tipologie as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo ($tipo == $tipologia) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($tipo)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="stato" class="form-label">Stato:</label>
                        <select class="form-control" id="stato" name="stato">
                            <option value="">Tutti gli stati</option>
                            <option value="bozza" <?php echo ($stato == 'bozza') ? 'selected' : ''; ?>>Bozza</option>
                            <option value="pubblicato" <?php echo ($stato == 'pubblicato') ? 'selected' : ''; ?>>Pubblicato</option>
                            <option value="errore" <?php echo ($stato == 'errore') ? 'selected' : ''; ?>>Errore</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="solo_parent" name="solo_parent" value="1" <?php echo $soloParent ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="solo_parent">
                                Mostra solo prodotti principali
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Applica Filtri
                        </button>
                        <a href="gestione_prodotti.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Resetta Filtri
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Azioni</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="inserimento_prodotti.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Nuovo Prodotto
                    </a>
                    
                    <a href="#" class="btn btn-outline-success" onclick="sincronizzaTuttiClick()">
                        <i class="bi bi-arrow-repeat"></i> Sincronizza Tutti
                    </a>
                    
                    <a href="#" class="btn btn-outline-info" onclick="esportaTuttiClick()">
                        <i class="bi bi-download"></i> Esporta Prodotti
                    </a>
                </div>
                
                <div class="mt-3">
                    <div class="alert alert-info mb-0">
                        <p class="mb-0"><strong>Totale prodotti:</strong> <?php echo $totalProducts; ?></p>
                        <?php if ($search || $tipologia || $stato || $soloParent): ?>
                            <p class="mb-0"><em>Filtri attivi</em></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Elenco Prodotti</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Titolo</th>
                        <th>Tipologia</th>
                        <th>Prezzo</th>
                        <th>Varianti</th>
                        <th>EAN</th>
                        <th>Stato</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($product['sku']); ?>
                                    <?php if (!empty($product['parent_sku']) && $product['parent_sku'] != $product['sku']): ?>
                                        <small class="text-muted d-block">Parent: <?php echo htmlspecialchars($product['parent_sku']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['titolo']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($product['tipologia'])); ?></td>
                                <td>
                                    <span class="text-success">€<?php echo number_format($product['prezzo_vendita'], 2, ',', '.'); ?></span>
                                    <small class="text-muted d-block">Acquisto: €<?php echo number_format($product['prezzo_acquisto'], 2, ',', '.'); ?></small>
                                </td>
                                <td>
                                    <?php if (empty($product['parent_sku'])): ?>
                                        <?php if ($product['variant_count'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $product['variant_count']; ?> varianti</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nessuna</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">È una variante</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['ean']); ?></td>
                                <td>
                                    <?php if ($product['stato'] === 'pubblicato'): ?>
                                        <span class="badge bg-success">Pubblicato</span>
                                    <?php elseif ($product['stato'] === 'bozza'): ?>
                                        <span class="badge bg-warning text-dark">Bozza</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Errore</span>
                                        <?php if (!empty($product['messaggio_errore'])): ?>
                                            <a href="#" class="text-danger ms-1" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($product['messaggio_errore']); ?>">
                                                <i class="bi bi-info-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($product['creato_il'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="syncProduct(<?php echo $product['id']; ?>)">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
<a href="gestione_prodotti.php?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sei sicuro di voler eliminare questo prodotto?')">
    <i class="bi bi-trash"></i>
</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">Nessun prodotto trovato</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginazione">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="gestione_prodotti.php?page=1<?php echo (!empty($search)) ? '&search=' . urlencode($search) : ''; ?><?php echo (!empty($tipologia)) ? '&tipologia=' . urlencode($tipologia) : ''; ?><?php echo (!empty($stato)) ? '&stato=' . urlencode($stato) : ''; ?><?php echo ($soloParent) ? '&solo_parent=1' : ''; ?>">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="gestione_prodotti.php?page=<?php echo $page - 1; ?><?php echo (!empty($search)) ? '&search=' . urlencode($search) : ''; ?><?php echo (!empty($tipologia)) ? '&tipologia=' . urlencode($tipologia) : ''; ?><?php echo (!empty($stato)) ? '&stato=' . urlencode($stato) : ''; ?><?php echo ($soloParent) ? '&solo_parent=1' : ''; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    // Mostra solo un numero limitato di pagine
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $active = ($i === $page) ? 'active' : '';
                        echo '<li class="page-item ' . $active . '">';
                        echo '<a class="page-link" href="gestione_prodotti.php?page=' . $i . 
                            ((!empty($search)) ? '&search=' . urlencode($search) : '') . 
                            ((!empty($tipologia)) ? '&tipologia=' . urlencode($tipologia) : '') . 
                            ((!empty($stato)) ? '&stato=' . urlencode($stato) : '') . 
                            (($soloParent) ? '&solo_parent=1' : '') . 
                            '">' . $i . '</a>';
                        echo '</li>';
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="gestione_prodotti.php?page=<?php echo $page + 1; ?><?php echo (!empty($search)) ? '&search=' . urlencode($search) : ''; ?><?php echo (!empty($tipologia)) ? '&tipologia=' . urlencode($tipologia) : ''; ?><?php echo (!empty($stato)) ? '&stato=' . urlencode($stato) : ''; ?><?php echo ($soloParent) ? '&solo_parent=1' : ''; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="gestione_prodotti.php?page=<?php echo $totalPages; ?><?php echo (!empty($search)) ? '&search=' . urlencode($search) : ''; ?><?php echo (!empty($tipologia)) ? '&tipologia=' . urlencode($tipologia) : ''; ?><?php echo (!empty($stato)) ? '&stato=' . urlencode($stato) : ''; ?><?php echo ($soloParent) ? '&solo_parent=1' : ''; ?>">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal visualizzazione prodotto -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel">Dettaglio Prodotto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="productModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per visualizzare i dettagli del log -->
<div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logModalLabel">Dettagli Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="logDetails" class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<?php
// Script aggiuntivi per la pagina di gestione prodotti
$additionalScripts = <<<EOT
<script>
// Funzioni JavaScript per la gestione prodotti

/**
 * Visualizza i dettagli di un prodotto in un modal
 * @param {number} productId ID del prodotto da visualizzare
 */
function viewProduct(productId) {
    // Mostra il modal
    const modal = document.getElementById("productModal");
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Mostra l'indicatore di caricamento
    document.getElementById("productModalBody").innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Caricamento...</span>
            </div>
        </div>
    `;
    
    // Carica i dettagli del prodotto tramite AJAX
    fetch(`vista_prodotto.php?id=\${productId}&format=json`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Costruisci l'HTML per visualizzare i dettagli del prodotto
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Informazioni Prodotto</h6>
                            <table class="table">
                                <tr>
                                    <th class="w-25">SKU:</th>
                                    <td>\${data.prodotto.sku}</td>
                                </tr>`;
                                
                if (data.prodotto.parent_sku) {
                    html += `
                                <tr>
                                    <th>Prodotto principale:</th>
                                    <td><a href="vista_prodotto.php?id=\${data.prodotto.parent_id}">\${data.prodotto.parent_sku}</a></td>
                                </tr>`;
                }
                
                html += `
                                <tr>
                                    <th>Tipologia:</th>
                                    <td>\${data.prodotto.tipologia_nome}</td>
                                </tr>
                                <tr>
                                    <th>Marca:</th>
                                    <td>\${data.prodotto.marca}</td>
                                </tr>
                                <tr>
                                    <th>Fornitore:</th>
                                    <td>\${data.prodotto.fornitore || '-'}</td>
                                </tr>`;
                                
                if (data.prodotto.taglia) {
                    html += `
                                <tr>
                                    <th>Taglia:</th>
                                    <td>\${data.prodotto.taglia}</td>
                                </tr>`;
                }
                
                if (data.prodotto.colore) {
                    html += `
                                <tr>
                                    <th>Colore:</th>
                                    <td>\${data.prodotto.colore}</td>
                                </tr>`;
                }
                
                html += `
                                <tr>
                                    <th>EAN:</th>
                                    <td>\${data.prodotto.ean || '-'}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Prezzi e Stato</h6>
                            <table class="table">
                                <tr>
                                    <th class="w-25">Prezzo Acquisto:</th>
                                    <td>€\${parseFloat(data.prodotto.prezzo_acquisto).toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <th>Prezzo Vendita:</th>
                                    <td>€\${parseFloat(data.prodotto.prezzo_vendita).toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <th>Aliquota IVA:</th>
                                    <td>\${data.prodotto.aliquota_iva}%</td>
                                </tr>
                                <tr>
                                    <th>Stato:</th>
                                    <td>\${data.prodotto.stato_html}</td>
                                </tr>`;
                                
                if (data.prodotto.stato === 'errore' && data.prodotto.messaggio_errore) {
                    html += `
                                <tr>
                                    <th>Errore:</th>
                                    <td class="text-danger">\${data.prodotto.messaggio_errore}</td>
                                </tr>`;
                }
                
                html += `
                                <tr>
                                    <th>Data creazione:</th>
                                    <td>\${data.prodotto.creato_il_formatted}</td>
                                </tr>
                                <tr>
                                    <th>ID Smarty:</th>
                                    <td>\${data.prodotto.smarty_id || '-'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>`;
                
                // Se ci sono varianti, mostrale
                if (data.varianti && data.varianti.length > 0) {
                    html += `
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6 class="border-bottom pb-2">Varianti (\${data.varianti.length})</h6>
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
                                        <tbody>`;
                    
                    data.varianti.forEach(variante => {
                        html += `
                                            <tr>
                                                <td>\${variante.sku}</td>
                                                <td>\${variante.titolo}</td>
                                                <td>\${variante.taglia || '-'}</td>
                                                <td>\${variante.colore || '-'}</td>
                                                <td>\${variante.ean || '-'}</td>
                                                <td>€\${parseFloat(variante.prezzo_vendita).toFixed(2)}</td>
                                                <td>\${variante.stato_html}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="vista_prodotto.php?id=\${variante.id}" class="btn btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-success" onclick="syncProduct(\${variante.id})">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>`;
                    });
                    
                    html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                }
                
                // Se ci sono log, mostrali
                if (data.logs && data.logs.length > 0) {
                    html += `
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
                                                <th>Dettagli</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                    
                    data.logs.forEach(log => {
                        html += `
                                            <tr>
                                                <td>\${log.creato_il_formatted}</td>
                                                <td>\${log.azione_html}</td>
                                                <td>\${log.riuscito ? '<span class="badge bg-success">Successo</span>' : '<span class="badge bg-danger">Fallito</span>'}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-info view-log-btn" onclick="viewLogDetails('\${log.id}')">
                                                        <i class="bi bi-info-circle"></i> Dettagli
                                                    </button>
                                                </td>
                                            </tr>`;
                    });
                    
                    html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                }
                
                // Azioni per il prodotto
                html += `
                    <div class="mt-4">
                        <div class="d-flex justify-content-between">
                            <a href="inserimento_prodotti.php?edit=\${data.prodotto.id}" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Modifica
                            </a>
                            <button type="button" class="btn btn-success" onclick="syncProduct(\${data.prodotto.id})">
                                <i class="bi bi-cloud-upload"></i> Sincronizza
                            </button>
                        </div>
                    </div>`;
                
                document.getElementById("productModalBody").innerHTML = html;
            } else {
                document.getElementById("productModalBody").innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        \${data.message || 'Errore nel caricamento dei dettagli del prodotto'}
                    </div>`;
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            document.getElementById("productModalBody").innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Si è verificato un errore nella richiesta. Riprova più tardi.
                </div>`;
        });
}

/**
 * Visualizza i dettagli di un log di sincronizzazione
 * @param {string} logId ID del log da visualizzare
 */
function viewLogDetails(logId) {
    // Carica i dettagli del log tramite AJAX
    fetch(`get_log_details.php?id=\${logId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostra il modal con i dettagli del log
                const logModal = document.getElementById("logModal") || createLogModal();
                const bsModal = new bootstrap.Modal(logModal);
                
                // Formatta la risposta JSON per una migliore leggibilità
                let rispostaFormatted = '';
                try {
                    rispostaFormatted = JSON.stringify(JSON.parse(data.risposta_api), null, 2);
                } catch (e) {
                    rispostaFormatted = data.risposta_api;
                }
                
                document.getElementById("logDetails").textContent = rispostaFormatted;
                bsModal.show();
            } else {
                alert('Errore nel recupero dei dettagli del log: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Si è verificato un errore nella richiesta. Riprova più tardi.');
        });
}

/**
 * Crea un modal per visualizzare i dettagli del log se non esiste
 * @returns {HTMLElement} L'elemento del modal
 */
function createLogModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'logModal';
    modal.tabIndex = '-1';
    modal.setAttribute('aria-labelledby', 'logModalLabel');
    modal.setAttribute('aria-hidden', 'true');
    
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logModalLabel">Dettagli Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="logDetails" class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    return modal;
}

/**
 * Sincronizza un prodotto con Smarty
 * @param {number} productId ID del prodotto da sincronizzare
 */
function syncProduct(productId) {
    if (!confirm('Sei sicuro di voler sincronizzare questo prodotto con Smarty?')) {
        return;
    }
    
    // Mostra un indicatore di caricamento
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-dark bg-opacity-50';
    loadingOverlay.style.zIndex = '9999';
    loadingOverlay.innerHTML = `
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Sincronizzazione in corso...</span>
        </div>
    `;
    document.body.appendChild(loadingOverlay);
    
    // Chiama l'API di sincronizzazione
    fetch('api_sincronizza_prodotto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=\${productId}`
    })
    .then(response => response.json())
    .then(data => {
        // Rimuovi l'overlay di caricamento
        document.body.removeChild(loadingOverlay);
        
        if (data.success) {
            const message = data.varianti ? 
                `Prodotto sincronizzato con successo! \${data.varianti.filter(v => v.success).length} varianti sincronizzate.` :
                'Prodotto sincronizzato con successo!';
                
            alert(message);
            window.location.reload(); // Ricarica la pagina per mostrare lo stato aggiornato
        } else {
            alert('Errore nella sincronizzazione: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Si è verificato un errore durante la sincronizzazione');
        
        // Rimuovi l'overlay di caricamento
        document.body.removeChild(loadingOverlay);
    });
}

/**
 * Sincronizza tutti i prodotti selezionati o filtrati
 */
function sincronizzaTuttiClick() {
    // Chiedi conferma all'utente
    if (!confirm('Vuoi sincronizzare tutti i prodotti (con i filtri attuali) con Smarty?')) {
        return;
    }
    
    // Recupera i parametri di filtro attuali dall'URL
    const urlParams = new URLSearchParams(window.location.search);
    const filters = {
        stato: urlParams.get('stato') || '',
        tipologia: urlParams.get('tipologia') || '',
        solo_parent: urlParams.get('solo_parent') || '',
        search: urlParams.get('search') || ''
    };
    
    // Mostra un indicatore di caricamento
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-dark bg-opacity-50';
    loadingOverlay.style.zIndex = '9999';
    loadingOverlay.innerHTML = `
        <div class="text-center bg-white p-4 rounded">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Sincronizzazione in corso...</span>
            </div>
            <h5>Sincronizzazione in corso...</h5>
            <p class="text-muted">Questo processo potrebbe richiedere alcuni minuti.</p>
        </div>
    `;
    document.body.appendChild(loadingOverlay);
    
    // Chiama l'API di sincronizzazione multipla
    fetch('api_sincronizza_multipli.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(filters)
    })
    .then(response => response.json())
    .then(data => {
        // Rimuovi l'overlay di caricamento
        document.body.removeChild(loadingOverlay);
        
        if (data.success) {
            alert(`Sincronizzazione completata con successo! \${data.success_count} prodotti sincronizzati, \${data.error_count} errori.`);
            window.location.reload(); // Ricarica la pagina per mostrare lo stato aggiornato
        } else {
            alert('Errore nella sincronizzazione multipla: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Si è verificato un errore durante la sincronizzazione multipla');
        
        // Rimuovi l'overlay di caricamento
        document.body.removeChild(loadingOverlay);
    });
}

/**
 * Esporta i prodotti filtrati in formato CSV o Excel
 */
function esportaTuttiClick() {
    // Prepara le opzioni di esportazione
    const opzioni = `
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="export_format" id="export_csv" value="csv" checked>
            <label class="form-check-label" for="export_csv">
                Esporta in CSV
            </label>
        </div>
        <!-- Temporaneamente disabilitato
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="export_format" id="export_excel" value="excel">
            <label class="form-check-label" for="export_excel">
                Esporta in Excel
            </label>
        </div>
        -->
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="export_include_variants" value="1" checked>
            <label class="form-check-label" for="export_include_variants">
                Includi varianti
            </label>
        </div>
    `;
    
    // Mostra un modal con le opzioni di esportazione
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'exportModal';
    modal.tabIndex = '-1';
    modal.setAttribute('aria-labelledby', 'exportModalLabel');
    modal.setAttribute('aria-hidden', 'true');
    
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Esporta Prodotti</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Seleziona le opzioni di esportazione:</p>
                    \${opzioni}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="start-export">Esporta</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const exportModal = new bootstrap.Modal(modal);
    exportModal.show();
    
    // Gestisci il click sul pulsante di esportazione
    document.getElementById('start-export').addEventListener('click', function() {
        // Recupera le opzioni selezionate
        const format = document.querySelector('input[name="export_format"]:checked').value;
        const includeVariants = document.getElementById('export_include_variants').checked ? '1' : '0';
        
        // Recupera i parametri di filtro attuali dall'URL
        const urlParams = new URLSearchParams(window.location.search);
        const filters = {
            stato: urlParams.get('stato') || '',
            tipologia: urlParams.get('tipologia') || '',
            solo_parent: urlParams.get('solo_parent') || '',
            search: urlParams.get('search') || ''
        };
        
        // Costruisci l'URL di esportazione con tutti i parametri
        let exportUrl = `esporta_prodotti.php?format=\${format}&include_variants=\${includeVariants}`;
        for (const [key, value] of Object.entries(filters)) {
            if (value) {
                exportUrl += `&\${key}=\${encodeURIComponent(value)}`;
            }
        }
        
        // Chiudi il modal
        exportModal.hide();
        
        // Avvia il download
        window.location.href = exportUrl;
    });
}

// Inizializza i tooltip
$(document).ready(function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
EOT;

// Include il footer
include 'footer.php';
?>