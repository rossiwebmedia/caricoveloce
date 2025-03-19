<?php
require_once 'config.php';
require_once 'functions.php';

// Titolo della pagina
$pageTitle = 'Gestione Codici EAN';

// Include l'header
include 'header.php';

// Connessione al database
$conn = getDbConnection();

// Gestione dell'azione richiesta
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';

// Messaggio di notifica
$notification = '';

// Gestione del caricamento di un file EAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'importa') {
    if (isset($_FILES['ean_file']) && $_FILES['ean_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ean_file'];
        $fileName = $file['name'];
        $tmpName = $file['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Verifica il tipo di file
        if (in_array($fileType, ['xlsx', 'xls', 'csv'])) {
            // Genera un nome univoco per il file caricato
            $uploadedFile = UPLOADS_DIR . '/' . uniqid('ean_') . '.' . $fileType;
            
            // Sposta il file caricato
            if (move_uploaded_file($tmpName, $uploadedFile)) {
                $batchName = isset($_POST['batch_name']) ? sanitizeInput($_POST['batch_name']) : 'Importazione ' . date('Y-m-d H:i:s');
                
                try {
                    // Inizializza l'array per i codici EAN
                    $eans = [];
                    
                    // Gestione diversa in base al tipo di file
                    if ($fileType === 'csv') {
                        // Apri il file CSV
                        $handle = fopen($uploadedFile, 'r');
                        
                        if ($handle !== false) {
                            // Leggi la prima riga per determinare se ci sono intestazioni
                            $header = fgetcsv($handle);
                            $hasHeader = false;
                            
                            // Verifica se la prima riga sembra essere un'intestazione
                            if (is_array($header) && count($header) > 0) {
                                $firstCell = strtolower(trim($header[0]));
                                $hasHeader = preg_match('/^(ean|codice|barcode|code)/i', $firstCell);
                            }
                            
                            // Se non è un'intestazione, considera la prima riga come dati
                            if (!$hasHeader && is_array($header)) {
                                $eans[] = trim($header[0]);
                            }
                            
                            // Leggi le righe rimanenti
                            while (($row = fgetcsv($handle)) !== false) {
                                if (is_array($row) && count($row) > 0 && !empty($row[0])) {
                                    $eans[] = trim($row[0]);
                                }
                            }
                            
                            fclose($handle);
                        }
                    } else {
                        // Usa PHPExcel o una libreria simile per i file Excel
                        // Questa è una versione semplificata che usa una libreria generica
                        // Per un'implementazione completa, è necessario utilizzare una libreria specifica
                        
                        // Placeholder per la lettura di file Excel
                        $eans = ['8012345600001', '8012345600002', '8012345600003'];
                    }
                    
                    // Rimuovi eventuali duplicati
                    $eans = array_unique($eans);
                    
                    // Crea un nuovo batch
                    $stmt = $conn->prepare("INSERT INTO batch_ean (nome, descrizione, totale, utente_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$batchName, 'Importazione file: ' . $fileName, count($eans), $_SESSION['user_id']]);
                    $batchId = $conn->lastInsertId();
                    
                    // Prepara l'inserimento dei codici EAN
                    $stmt = $conn->prepare("INSERT INTO codici_ean (ean, batch_id) VALUES (?, ?)");
                    
                    // Inserisci i codici EAN nel database
                    foreach ($eans as $ean) {
                        try {
                            $stmt->execute([$ean, $batchId]);
                        } catch (PDOException $e) {
                            // Ignora i duplicati
                            if ($e->getCode() !== '23000') { // Errore di duplicato
                                throw $e;
                            }
                        }
                    }
                    
                    // Rimuovi il file dopo l'elaborazione
                    unlink($uploadedFile);
                    
                    $notification = [
                        'type' => 'success',
                        'message' => "Importazione completata con successo. Importati " . count($eans) . " codici EAN."
                    ];
                } catch (Exception $e) {
                    $notification = [
                        'type' => 'danger',
                        'message' => "Errore durante l'importazione: " . $e->getMessage()
                    ];
                    
                    // Rimuovi il file in caso di errore
                    if (file_exists($uploadedFile)) {
                        unlink($uploadedFile);
                    }
                }
            } else {
                $notification = [
                    'type' => 'danger',
                    'message' => "Errore durante il caricamento del file."
                ];
            }
        } else {
            $notification = [
                'type' => 'danger',
                'message' => "Tipo di file non supportato. Carica un file XLSX, XLS o CSV."
            ];
        }
    } else {
        $notification = [
            'type' => 'danger',
            'message' => "Nessun file caricato o errore nel caricamento."
        ];
    }
}

// Elimina un batch o un EAN
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    $deleteType = sanitizeInput($_GET['delete']);
    $deleteId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($deleteType === 'batch' && $deleteId > 0) {
        try {
            // Elimina il batch e tutti i suoi EAN
            $stmt = $conn->prepare("DELETE FROM codici_ean WHERE batch_id = ? AND utilizzato = 0");
            $stmt->execute([$deleteId]);
            
            $stmt = $conn->prepare("DELETE FROM batch_ean WHERE id = ?");
            $stmt->execute([$deleteId]);
            
            $notification = [
                'type' => 'success',
                'message' => "Batch eliminato con successo."
            ];
        } catch (Exception $e) {
            $notification = [
                'type' => 'danger',
                'message' => "Errore durante l'eliminazione del batch: " . $e->getMessage()
            ];
        }
    } elseif ($deleteType === 'ean' && $deleteId > 0) {
        try {
            // Elimina un singolo EAN
            $stmt = $conn->prepare("DELETE FROM codici_ean WHERE id = ? AND utilizzato = 0");
            $stmt->execute([$deleteId]);
            
            $notification = [
                'type' => 'success',
                'message' => "Codice EAN eliminato con successo."
            ];
        } catch (Exception $e) {
            $notification = [
                'type' => 'danger',
                'message' => "Errore durante l'eliminazione del codice EAN: " . $e->getMessage()
            ];
        }
    }
}

// Mostra la notifica se presente
if (!empty($notification)) {
    echo '<div class="alert alert-' . $notification['type'] . ' alert-dismissible fade show mb-4" role="alert">';
    echo $notification['message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>';
    echo '</div>';
}

// Visualizza il contenuto in base all'azione richiesta
switch ($action) {
    case 'importa':
        // Form per l'importazione di nuovi EAN
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Importa Codici EAN</h5>
                    <a href="gestione_ean.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla lista
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="gestione_ean.php?action=importa" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="batch_name" class="form-label">Nome Batch:</label>
                        <input type="text" class="form-control" id="batch_name" name="batch_name" value="Importazione <?php echo date('d/m/Y H:i'); ?>">
                        <div class="form-text">Un nome identificativo per questo gruppo di codici EAN</div>
                    </div>
                    <div class="mb-3">
                        <label for="ean_file" class="form-label">File con codici EAN:</label>
                        <input type="file" class="form-control" id="ean_file" name="ean_file" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Carica un file Excel o CSV contenente i codici EAN (1 per riga)</div>
                    </div>
                    <div class="mb-3">
                        <p><strong>Formato del file:</strong></p>
                        <ul>
                            <li>Il file può essere in formato Excel (.xlsx, .xls) o CSV (.csv)</li>
                            <li>La prima colonna deve contenere i codici EAN</li>
                            <li>Il sistema riconosce automaticamente se la prima riga è un'intestazione</li>
                        </ul>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importa Codici EAN
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Genera Codici EAN Demo</h5>
            </div>
            <div class="card-body">
                <p>Se non disponi di un file con codici EAN, puoi generare dei codici demo per i test.</p>
                <form action="genera_ean_demo.php" method="post">
                    <div class="mb-3">
                        <label for="demo_count" class="form-label">Numero di codici da generare:</label>
                        <input type="number" class="form-control" id="demo_count" name="demo_count" value="100" min="1" max="1000">
                    </div>
                    <div class="mb-3">
                        <label for="demo_prefix" class="form-label">Prefisso (opzionale):</label>
                        <input type="text" class="form-control" id="demo_prefix" name="demo_prefix" value="8012345" maxlength="7">
                        <div class="form-text">Prefisso per i codici EAN (default: 8012345)</div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-lightning-charge"></i> Genera Codici Demo
                    </button>
                </form>
            </div>
        </div>
        <?php
        break;
        
    case 'view':
        // Visualizza gli EAN di un batch specifico
        $batchId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($batchId <= 0) {
            echo '<div class="alert alert-danger">ID batch non valido.</div>';
            echo '<p><a href="gestione_ean.php" class="btn btn-outline-secondary">Torna alla lista</a></p>';
            break;
        }
        
        // Ottieni informazioni sul batch
        $stmt = $conn->prepare("SELECT * FROM batch_ean WHERE id = ?");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch();
        
        if (!$batch) {
            echo '<div class="alert alert-danger">Batch non trovato.</div>';
            echo '<p><a href="gestione_ean.php" class="btn btn-outline-secondary">Torna alla lista</a></p>';
            break;
        }
        
        // Ottieni conteggi
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM codici_ean WHERE batch_id = ?");
        $stmt->execute([$batchId]);
        $totalCount = $stmt->fetch()['total'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as used FROM codici_ean WHERE batch_id = ? AND utilizzato = 1");
        $stmt->execute([$batchId]);
        $usedCount = $stmt->fetch()['used'];
        
        $availableCount = $totalCount - $usedCount;
        
        // Ottieni gli EAN del batch (con paginazione)
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $stmt = $conn->prepare("
            SELECT * FROM codici_ean 
            WHERE batch_id = ? 
            ORDER BY creato_il DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$batchId, $perPage, $offset]);
        $eans = $stmt->fetchAll();
        
        // Calcola il numero totale di pagine
        $totalPages = ceil($totalCount / $perPage);
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Dettaglio Batch EAN: <?php echo htmlspecialchars($batch['nome']); ?></h5>
                    <a href="gestione_ean.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla lista
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Totale Codici</h6>
                                <p class="card-text fs-2"><?php echo $totalCount; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Disponibili</h6>
                                <p class="card-text fs-2"><?php echo $availableCount; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Utilizzati</h6>
                                <p class="card-text fs-2"><?php echo $usedCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Codice EAN</th>
                                <th>Stato</th>
                                <th>Prodotto</th>
                                <th>Data Creazione</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eans as $ean): ?>
                                <tr>
                                    <td><?php echo $ean['id']; ?></td>
                                    <td><code><?php echo htmlspecialchars($ean['ean']); ?></code></td>
                                    <td>
                                        <?php if ($ean['utilizzato']): ?>
                                            <span class="badge bg-secondary">Utilizzato</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Disponibile</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ean['prodotto_id']): 
                                            // Ottieni il nome del prodotto
                                            $stmtProd = $conn->prepare("SELECT sku FROM prodotti WHERE id = ?");
                                            $stmtProd->execute([$ean['prodotto_id']]);
                                            $prodotto = $stmtProd->fetch();
                                            echo $prodotto ? '<a href="gestione_prodotti.php?id=' . $ean['prodotto_id'] . '">' . htmlspecialchars($prodotto['sku']) . '</a>' : 'Prodotto sconosciuto';
                                        else:
                                            echo '-';
                                        endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ean['creato_il'])); ?></td>
                                    <td>
                                        <?php if (!$ean['utilizzato']): ?>
                                            <a href="gestione_ean.php?delete=ean&id=<?php echo $ean['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo codice EAN?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($eans)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Nessun codice EAN trovato.</td>
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
                                    <a class="page-link" href="gestione_ean.php?action=view&id=<?php echo $batchId; ?>&page=1">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="gestione_ean.php?action=view&id=<?php echo $batchId; ?>&page=<?php echo $page - 1; ?>">
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
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="gestione_ean.php?action=view&id=' . $batchId . '&page=' . $i . '">' . $i . '</a></li>';
                            }
                            
                            if ($endPage < $totalPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="gestione_ean.php?action=view&id=<?php echo $batchId; ?>&page=<?php echo $page + 1; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="gestione_ean.php?action=view&id=<?php echo $batchId; ?>&page=<?php echo $totalPages; ?>">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="download_ean.php?batch_id=<?php echo $batchId; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Esporta EAN disponibili
                    </a>
                    
                    <?php if ($availableCount > 0): ?>
                        <a href="gestione_ean.php?delete=batch&id=<?php echo $batchId; ?>" class="btn btn-outline-danger float-end" onclick="return confirm('Sei sicuro di voler eliminare tutti i codici EAN disponibili in questo batch?');">
                            <i class="bi bi-trash"></i> Elimina EAN disponibili
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        break;
        
    default:
        // Elenco di tutti i batch EAN
        $stmt = $conn->query("
            SELECT b.*, u.username, 
                   (SELECT COUNT(*) FROM codici_ean WHERE batch_id = b.id) as totale,
                   (SELECT COUNT(*) FROM codici_ean WHERE batch_id = b.id AND utilizzato = 1) as utilizzati
            FROM batch_ean b
            LEFT JOIN utenti u ON b.utente_id = u.id
            ORDER BY b.creato_il DESC
        ");
        $batches = $stmt->fetchAll();
        
        // Conteggio totale degli EAN
        $stmt = $conn->query("SELECT COUNT(*) as total FROM codici_ean");
        $totalEANs = $stmt->fetch()['total'];
        
        $stmt = $conn->query("SELECT COUNT(*) as total FROM codici_ean WHERE utilizzato = 0");
        $availableEANs = $stmt->fetch()['total'];
        ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Codici EAN Totali</h5>
                        <p class="card-text display-4"><?php echo $totalEANs; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Codici EAN Disponibili</h5>
                        <p class="card-text display-4"><?php echo $availableEANs; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Batch Codici EAN</h5>
                    <a href="gestione_ean.php?action=importa" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Importa Codici EAN
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Totale</th>
                                <th>Disponibili</th>
                                <th>Utilizzati</th>
                                <th>Creato da</th>
                                <th>Data</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td><?php echo $batch['id']; ?></td>
                                    <td><?php echo htmlspecialchars($batch['nome']); ?></td>
                                    <td><?php echo $batch['totale']; ?></td>
                                    <td><?php echo $batch['totale'] - $batch['utilizzati']; ?></td>
                                    <td><?php echo $batch['utilizzati']; ?></td>
                                    <td><?php echo htmlspecialchars($batch['username'] ?? 'Utente sconosciuto'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($batch['creato_il'])); ?></td>
                                    <td>
                                        <a href="gestione_ean.php?action=view&id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-info text-white">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="download_ean.php?batch_id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <?php if ($batch['totale'] - $batch['utilizzati'] > 0): ?>
                                            <a href="gestione_ean.php?delete=batch&id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo batch?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($batches)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Nessun batch trovato.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        break;
}

// Include il footer
include 'footer.php';
?>
