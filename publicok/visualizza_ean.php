<?php
// Inizia l'output buffering per risolvere il problema "headers already sent"
ob_start();

require_once 'config.php';
require_once 'functions.php';

// Titolo della pagina
$pageTitle = 'Visualizza Codici EAN';

// Include l'header
include 'header.php';

// Connessione al database
$conn = getDbConnection();

// Gestione delle azioni di massa
if (isset($_POST['bulk_action']) && isset($_POST['selected_ean'])) {
    $action = $_POST['bulk_action'];
    $selectedEans = $_POST['selected_ean'];
    
    if (!empty($selectedEans) && in_array($action, ['set_available', 'set_used'])) {
        $usedValue = ($action === 'set_used') ? 1 : 0;
        
        // Creazione di parametri nominali per l'array di ID
        $placeholders = [];
        $params = [':used_value' => $usedValue];
        
        foreach ($selectedEans as $index => $id) {
            $paramName = ":id$index";
            $placeholders[] = $paramName;
            $params[$paramName] = (int)$id;
        }
        
        $updateQuery = "UPDATE codici_ean SET utilizzato = :used_value, 
                        aggiornato_il = NOW() 
                        WHERE id IN (" . implode(',', $placeholders) . ")";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute($params);
        
        $count = count($selectedEans);
        $status = ($action === 'set_used') ? 'utilizzati' : 'disponibili';
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => "$count codici EAN sono stati impostati come $status con successo."
        ];
        
        // Utilizziamo JavaScript per redirezionare invece del PHP header()
        echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET) . "';</script>";
        exit;
    }
}

// Parametri di paginazione e filtro
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filter_batch = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Calcolo offset per paginazione
$offset = ($page - 1) * $per_page;

// Costruzione query base
$query = "SELECT e.id, e.ean, e.batch_id, e.utilizzato, e.prodotto_id, e.creato_il, e.aggiornato_il,
          b.nome AS batch_nome,
          p.sku AS prodotto_sku, 
          p.titolo AS prodotto_nome,
          COALESCE(v.sku, p.sku) AS sku_variante,
          COALESCE(v.titolo, p.titolo) AS titolo_variante,
          CASE 
              WHEN v.id IS NOT NULL THEN 'Variante' 
              WHEN p.id IS NOT NULL THEN 'Prodotto Singolo' 
              ELSE 'Non Assegnato' 
          END AS tipo_assegnazione
          FROM codici_ean e 
          LEFT JOIN batch_ean b ON e.batch_id = b.id
          LEFT JOIN prodotti p ON e.prodotto_id = p.id
          LEFT JOIN prodotti v ON v.parent_sku = p.sku AND v.ean = e.ean";

$countQuery = "SELECT COUNT(*) as total FROM codici_ean e 
               LEFT JOIN batch_ean b ON e.batch_id = b.id
               LEFT JOIN prodotti p ON e.prodotto_id = p.id";

$whereConditions = [];
$bindParams = [];

// Applicazione filtri
if (!empty($search)) {
    $whereConditions[] = "(e.ean LIKE :search OR p.sku LIKE :search OR p.titolo LIKE :search)";
    $bindParams[':search'] = "%$search%";
}

if ($filter_status === 'used') {
    $whereConditions[] = "e.utilizzato = 1";
} elseif ($filter_status === 'available') {
    $whereConditions[] = "e.utilizzato = 0";
}

if ($filter_batch > 0) {
    $whereConditions[] = "e.batch_id = :batch_id";
    $bindParams[':batch_id'] = $filter_batch;
}

if (!empty($filter_date_from)) {
    $whereConditions[] = "e.creato_il >= :date_from";
    $bindParams[':date_from'] = $filter_date_from . ' 00:00:00';
}

if (!empty($filter_date_to)) {
    $whereConditions[] = "e.creato_il <= :date_to";
    $bindParams[':date_to'] = $filter_date_to . ' 23:59:59';
}

// Aggiunta delle condizioni WHERE
if (count($whereConditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
    $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
}

// Ordinamento
$query .= " ORDER BY e.creato_il DESC LIMIT :offset, :per_page";

// Esecuzione query per conteggio totale
$stmt = $conn->prepare($countQuery);
foreach ($bindParams as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $per_page);

// Esecuzione query principale
$stmt = $conn->prepare($query);
foreach ($bindParams as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$eans = $stmt->fetchAll();

// Recupero batch per filtro
$batchQuery = "SELECT id, nome, totale FROM batch_ean ORDER BY creato_il DESC";
$batchResult = $conn->query($batchQuery);
$batches = $batchResult->fetchAll();

// Recupera statistiche generali
$statsQuery = "SELECT 
               COUNT(*) as total_ean,
               SUM(CASE WHEN utilizzato = 1 THEN 1 ELSE 0 END) as used_ean,
               COUNT(DISTINCT batch_id) as total_batches
               FROM codici_ean";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch();

// Mostra la notifica se presente in sessione
if (isset($_SESSION['notification'])) {
    echo '<div class="alert alert-' . $_SESSION['notification']['type'] . ' alert-dismissible fade show mb-4" role="alert">';
    echo $_SESSION['notification']['message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>';
    echo '</div>';
    
    // Rimuovi la notifica dalla sessione
    unset($_SESSION['notification']);
}
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Totale Codici EAN</h5>
                <p class="card-text fs-2"><?php echo number_format($stats['total_ean']); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Disponibili</h5>
                <p class="card-text fs-2"><?php echo number_format($stats['total_ean'] - $stats['used_ean']); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <h5 class="card-title">Utilizzati</h5>
                <p class="card-text fs-2"><?php echo number_format($stats['used_ean']); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Filtri</h5>
            <div>
                <a href="gestione_ean.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-upload"></i> Importa EAN
                </a>
                <button id="export-csv" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="bi bi-download"></i> Esporta CSV
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="get" action="" id="filter-form">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Ricerca</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Codice EAN o SKU">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Stato</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tutti</option>
                        <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Disponibili</option>
                        <option value="used" <?php echo $filter_status === 'used' ? 'selected' : ''; ?>>Utilizzati</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="batch_id" class="form-label">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id">
                        <option value="0">Tutti</option>
                        <?php foreach ($batches as $batch) : ?>
                        <option value="<?php echo $batch['id']; ?>" <?php echo $filter_batch == $batch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($batch['nome']); ?> (<?php echo $batch['totale']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Data da</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Data a</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtra</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Form per le azioni di massa -->
        <form method="post" id="bulk-actions-form">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="select-all">Seleziona tutti</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all">Deseleziona tutti</button>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <label for="bulk_action" class="me-2">Azione di massa:</label>
                    <select class="form-select form-select-sm me-2" id="bulk_action" name="bulk_action" style="width: auto;">
                        <option value="">-- Seleziona azione --</option>
                        <option value="set_available">Imposta come disponibili</option>
                        <option value="set_used">Imposta come utilizzati</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" id="apply-bulk-action" disabled>Applica</button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="check-all"></th>
                            <th>ID</th>
                            <th>Codice EAN</th>
                            <th>Prodotto</th>
                            <th>SKU</th>
                            <th>Stato</th>
                            <th>Data Utilizzo</th>
                            <th>Data Creazione</th>
                            <th>Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eans)) : ?>
                        <tr>
                            <td colspan="9" class="text-center">Nessun codice EAN trovato</td>
                        </tr>
                        <?php else : ?>
                            <?php foreach ($eans as $ean) : ?>
                            <tr>
                                <td><input type="checkbox" class="ean-checkbox" name="selected_ean[]" value="<?php echo $ean['id']; ?>"></td>
                                <td><?php echo $ean['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($ean['ean']); ?></code></td>
                                <td>
    <?php if (!empty($ean['prodotto_id'])) : ?>
        <?php if ($ean['tipo_assegnazione'] === 'Variante') : ?>
            <a href="gestione_prodotti.php?id=<?php echo $ean['prodotto_id']; ?>">
                <?php echo htmlspecialchars($ean['titolo_variante']); ?>
            </a>
        <?php else : ?>
            <a href="gestione_prodotti.php?id=<?php echo $ean['prodotto_id']; ?>">
                <?php echo htmlspecialchars($ean['prodotto_nome'] ?: 'ID: ' . $ean['prodotto_id']); ?>
            </a>
        <?php endif; ?>
    <?php else : ?>
    -
    <?php endif; ?>
</td>
<td>
    <?php 
    if (!empty($ean['sku_variante'])) {
        echo htmlspecialchars($ean['sku_variante']);
    } elseif (!empty($ean['prodotto_sku'])) {
        echo htmlspecialchars($ean['prodotto_sku']);
    } else {
        echo '-';
    }
    ?>
</td>
                                <td>
                                    <?php if ($ean['utilizzato']) : ?>
                                        <span class="badge bg-secondary">Utilizzato</span>
                                    <?php else : ?>
                                        <span class="badge bg-success">Disponibile</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $ean['utilizzato'] && $ean['aggiornato_il'] ? date('d/m/Y H:i', strtotime($ean['aggiornato_il'])) : '-'; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ean['creato_il'])); ?></td>
                                <td><?php echo htmlspecialchars($ean['batch_nome'] ?? 'N/D'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <?php if ($totalPages > 1) : ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                Mostrando <?php echo $offset + 1 ?> - <?php echo min($offset + $per_page, $totalRecords) ?> di <?php echo $totalRecords ?> risultati
            </div>
            <nav aria-label="Paginazione">
                <ul class="pagination">
                    <?php if ($page > 1) : ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1&per_page=<?php echo $per_page ?>&search=<?php echo urlencode($search) ?>&status=<?php echo urlencode($filter_status) ?>&batch_id=<?php echo $filter_batch ?>&date_from=<?php echo urlencode($filter_date_from) ?>&date_to=<?php echo urlencode($filter_date_to) ?>">
                            <i class="bi bi-chevron-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1 ?>&per_page=<?php echo $per_page ?>&search=<?php echo urlencode($search) ?>&status=<?php echo urlencode($filter_status) ?>&batch_id=<?php echo $filter_batch ?>&date_from=<?php echo urlencode($filter_date_from) ?>&date_to=<?php echo urlencode($filter_date_to) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $active = ($i === $page) ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . '&search=' . urlencode($search) . '&status=' . urlencode($filter_status) . '&batch_id=' . $filter_batch . '&date_from=' . urlencode($filter_date_from) . '&date_to=' . urlencode($filter_date_to) . '">' . $i . '</a></li>';
                    }
                    
                    if ($endPage < $totalPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    ?>
                    
                    <?php if ($page < $totalPages) : ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1 ?>&per_page=<?php echo $per_page ?>&search=<?php echo urlencode($search) ?>&status=<?php echo urlencode($filter_status) ?>&batch_id=<?php echo $filter_batch ?>&date_from=<?php echo urlencode($filter_date_from) ?>&date_to=<?php echo urlencode($filter_date_to) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $totalPages ?>&per_page=<?php echo $per_page ?>&search=<?php echo urlencode($search) ?>&status=<?php echo urlencode($filter_status) ?>&batch_id=<?php echo $filter_batch ?>&date_from=<?php echo urlencode($filter_date_from) ?>&date_to=<?php echo urlencode($filter_date_to) ?>">
                            <i class="bi bi-chevron-double-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="form-inline">
                <label class="me-2" for="per_page_select">Per pagina:</label>
                <select class="form-select form-select-sm" id="per_page_select" style="width: auto;" onchange="updatePerPage(this.value)">
                    <option value="25" <?php echo $per_page == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?php echo $per_page == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?php echo $per_page == 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?php echo $per_page == 200 ? 'selected' : '' ?>>200</option>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reset paginazione quando cambia un filtro
    document.getElementById('filter-form').addEventListener('submit', function(e) {
        const form = e.target;
        const pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        pageInput.value = '1';
        form.appendChild(pageInput);
    });
    
    // Gestione esportazione CSV
    document.getElementById('export-csv').addEventListener('click', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const search = urlParams.get('search') || '';
        const status = urlParams.get('status') || '';
        const batchId = urlParams.get('batch_id') || '0';
        const dateFrom = urlParams.get('date_from') || '';
        const dateTo = urlParams.get('date_to') || '';
        
        window.location.href = `export_ean.php?format=csv&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&batch_id=${encodeURIComponent(batchId)}&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}`;
    });
    
    // Gestione checkbox e azioni di massa
    const checkAll = document.getElementById('check-all');
    const eanCheckboxes = document.querySelectorAll('.ean-checkbox');
    const bulkActionSelect = document.getElementById('bulk_action');
    const applyBulkActionButton = document.getElementById('apply-bulk-action');
    
    // Seleziona/deseleziona tutti
    checkAll.addEventListener('change', function() {
        eanCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActionButton();
    });
    
    // Aggiorna stato pulsante "Applica"
    function updateBulkActionButton() {
        const checkedBoxes = document.querySelectorAll('.ean-checkbox:checked');
        const actionSelected = bulkActionSelect.value !== '';
        applyBulkActionButton.disabled = checkedBoxes.length === 0 || !actionSelected;
    }
    
    // Ascolta cambiamenti nelle singole checkbox
    eanCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButton);
    });
    
    // Ascolta cambiamenti nell'azione selezionata
    bulkActionSelect.addEventListener('change', updateBulkActionButton);
    
    // Pulsanti di selezione/deselezione
    document.getElementById('select-all').addEventListener('click', function() {
        eanCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        checkAll.checked = true;
        updateBulkActionButton();
    });
    
    document.getElementById('deselect-all').addEventListener('click', function() {
        eanCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        checkAll.checked = false;
        updateBulkActionButton();
    });
    
    // Conferma prima di applicare l'azione di massa
    document.getElementById('bulk-actions-form').addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.ean-checkbox:checked');
        const action = bulkActionSelect.value;
        
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Seleziona almeno un codice EAN.');
            return;
        }
        
        if (action === '') {
            e.preventDefault();
            alert('Seleziona un\'azione da eseguire.');
            return;
        }
        
        if (!confirm(`Sei sicuro di voler ${action === 'set_available' ? 'impostare come disponibili' : 'impostare come utilizzati'} ${checkedBoxes.length} codici EAN?`)) {
            e.preventDefault();
        }
    });
});

function updatePerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', 1);
    window.location = url.toString();
}
</script>

<?php include 'footer.php'; ?>
<?php ob_end_flush(); // Flush del buffer di output alla fine ?>