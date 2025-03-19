<?php
require_once 'init.php';
require_once 'config.php';
require_once 'functions.php';

// Titolo della pagina
$pageTitle = 'Impostazioni';

// Include l'header
include 'header.php';

// Connessione al database
$conn = getDbConnection();

// Messaggio di notifica
$notification = '';

// Gestione dell'invio del form per le API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api'])) {
    $apiKey = sanitizeInput($_POST['api_key']);
    $apiName = sanitizeInput($_POST['api_name']);
    $apiUrl = sanitizeInput($_POST['api_url']);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    try {
        // Se l'API deve essere predefinita, rimuovi questo flag da tutte le altre API
        if ($isDefault) {
            $stmt = $conn->query("UPDATE impostazioni_api SET predefinito = 0");
        }
        
        // Verifica se esiste già un'impostazione API con lo stesso nome
        $stmt = $conn->prepare("SELECT id FROM impostazioni_api WHERE nome = ?");
        $stmt->execute([$apiName]);
        $existingApi = $stmt->fetch();
        
        if ($existingApi) {
            // Aggiorna l'impostazione esistente
            $stmt = $conn->prepare("
                UPDATE impostazioni_api 
                SET api_key = ?, api_url = ?, predefinito = ?, aggiornato_il = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$apiKey, $apiUrl, $isDefault, $existingApi['id']]);
            
            $notification = [
                'type' => 'success',
                'message' => "Impostazioni API aggiornate con successo."
            ];
        } else {
            // Crea una nuova impostazione
            $stmt = $conn->prepare("
                INSERT INTO impostazioni_api (nome, api_key, api_url, predefinito, utente_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$apiName, $apiKey, $apiUrl, $isDefault, $_SESSION['user_id']]);
            
            $notification = [
                'type' => 'success',
                'message' => "Nuova configurazione API creata con successo."
            ];
        }
    } catch (Exception $e) {
        $notification = [
            'type' => 'danger',
            'message' => "Errore durante il salvataggio delle impostazioni API: " . $e->getMessage()
        ];
    }
}

// Gestione della rimozione di una configurazione API
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_api'])) {
    $apiId = intval($_GET['delete_api']);
    
    try {
        // Verifica se l'API esiste
        $stmt = $conn->prepare("SELECT id, predefinito FROM impostazioni_api WHERE id = ?");
        $stmt->execute([$apiId]);
        $api = $stmt->fetch();
        
        if ($api) {
            // Non permettere l'eliminazione dell'API predefinita
            if ($api['predefinito']) {
                $notification = [
                    'type' => 'warning',
                    'message' => "Non è possibile eliminare la configurazione API predefinita. Imposta prima un'altra API come predefinita."
                ];
            } else {
                // Elimina la configurazione API
                $stmt = $conn->prepare("DELETE FROM impostazioni_api WHERE id = ?");
                $stmt->execute([$apiId]);
                
                $notification = [
                    'type' => 'success',
                    'message' => "Configurazione API eliminata con successo."
                ];
            }
        }
    } catch (Exception $e) {
        $notification = [
            'type' => 'danger',
            'message' => "Errore durante l'eliminazione della configurazione API: " . $e->getMessage()
        ];
    }
}

// Gestione dell'invio del form per le tipologie di prodotto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product_type'])) {
    $typeName = sanitizeInput($_POST['type_name']);
    $typeMultiplier = floatval($_POST['type_multiplier']);
    $typeRoundTo = floatval($_POST['type_round_to']);
    $typeDescription = sanitizeInput($_POST['type_description']);
    
    try {
        // Verifica se esiste già una tipologia con lo stesso nome
        $stmt = $conn->prepare("SELECT id FROM tipologie_prodotto WHERE nome = ?");
        $stmt->execute([$typeName]);
        $existingType = $stmt->fetch();
        
        if ($existingType) {
            // Aggiorna la tipologia esistente
            $stmt = $conn->prepare("
                UPDATE tipologie_prodotto 
                SET moltiplicatore_prezzo = ?, arrotonda_a = ?, descrizione = ?, aggiornato_il = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$typeMultiplier, $typeRoundTo, $typeDescription, $existingType['id']]);
            
            $notification = [
                'type' => 'success',
                'message' => "Tipologia di prodotto aggiornata con successo."
            ];
        } else {
            // Crea una nuova tipologia
            $stmt = $conn->prepare("
                INSERT INTO tipologie_prodotto (nome, moltiplicatore_prezzo, arrotonda_a, descrizione)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$typeName, $typeMultiplier, $typeRoundTo, $typeDescription]);
            
            $notification = [
                'type' => 'success',
                'message' => "Nuova tipologia di prodotto creata con successo."
            ];
        }
    } catch (Exception $e) {
        $notification = [
            'type' => 'danger',
            'message' => "Errore durante il salvataggio della tipologia di prodotto: " . $e->getMessage()
        ];
    }
}

// Gestione della rimozione di una tipologia di prodotto
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_type'])) {
    $typeId = intval($_GET['delete_type']);
    
    try {
        // Elimina la tipologia
        $stmt = $conn->prepare("DELETE FROM tipologie_prodotto WHERE id = ?");
        $stmt->execute([$typeId]);
        
        $notification = [
            'type' => 'success',
            'message' => "Tipologia di prodotto eliminata con successo."
        ];
    } catch (Exception $e) {
        $notification = [
            'type' => 'danger',
            'message' => "Errore durante l'eliminazione della tipologia di prodotto: " . $e->getMessage()
        ];
    }
}

// Ottieni le configurazioni API
$stmt = $conn->query("SELECT * FROM impostazioni_api ORDER BY predefinito DESC, nome ASC");
$apiConfigs = $stmt->fetchAll();

// Ottieni le tipologie di prodotto
$stmt = $conn->query("SELECT * FROM tipologie_prodotto ORDER BY nome ASC");
$productTypes = $stmt->fetchAll();

// Mostra la notifica se presente
if (!empty($notification)) {
    echo '<div class="alert alert-' . $notification['type'] . ' alert-dismissible fade show mb-4" role="alert">';
    echo $notification['message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>';
    echo '</div>';
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Configurazione API Smarty</h5>
            </div>
            <div class="card-body">
                <form action="impostazioni.php" method="post">
                    <input type="hidden" name="save_api" value="1">
                    
                    <div class="mb-3">
                        <label for="api_name" class="form-label">Nome configurazione:</label>
                        <input type="text" class="form-control" id="api_name" name="api_name" required>
                        <div class="form-text">Un nome identificativo per questa configurazione API</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_key" class="form-label">API Key:</label>
                        <input type="text" class="form-control" id="api_key" name="api_key" required>
                        <div class="form-text">La chiave API fornita da Smarty</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_url" class="form-label">URL API:</label>
                        <input type="text" class="form-control" id="api_url" name="api_url" value="https://www.gestionalesmarty.com/titanium/V2/Api/" required>
                        <div class="form-text">L'URL base dell'API Smarty</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                        <label class="form-check-label" for="is_default">Usa come configurazione predefinita</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salva configurazione
                    </button>
                </form>
                
                <hr>
                
                <div class="mt-4">
                    <h6>API configurate:</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>API Key</th>
                                    <th>Predefinita</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($apiConfigs as $api): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($api['nome']); ?></td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 150px;">
                                                <?php echo htmlspecialchars($api['api_key']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($api['predefinito']): ?>
                                                <span class="badge bg-success">Predefinita</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-api" 
                                                    data-id="<?php echo $api['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($api['nome']); ?>"
                                                    data-key="<?php echo htmlspecialchars($api['api_key']); ?>"
                                                    data-url="<?php echo htmlspecialchars($api['api_url']); ?>"
                                                    data-default="<?php echo $api['predefinito']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if (!$api['predefinito']): ?>
                                                <a href="impostazioni.php?delete_api=<?php echo $api['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sei sicuro di voler eliminare questa configurazione API?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($apiConfigs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Nessuna configurazione API trovata.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-outline-info btn-sm" id="test-api-btn">
                        <i class="bi bi-check-circle"></i> Verifica connessione API
                    </button>
                    <div id="api-test-result" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Tipologie di Prodotto</h5>
            </div>
            <div class="card-body">
                <form action="impostazioni.php" method="post">
                    <input type="hidden" name="save_product_type" value="1">
                    
                    <div class="mb-3">
                        <label for="type_name" class="form-label">Nome tipologia:</label>
                        <input type="text" class="form-control" id="type_name" name="type_name" required>
                        <div class="form-text">Il nome della tipologia di prodotto (es. uomo, donna, accessori)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_multiplier" class="form-label">Moltiplicatore prezzo:</label>
                        <input type="number" class="form-control" id="type_multiplier" name="type_multiplier" step="0.01" min="1" value="3.00" required>
                        <div class="form-text">Il moltiplicatore da applicare al prezzo di acquisto</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_round_to" class="form-label">Arrotonda a:</label>
                        <input type="number" class="form-control" id="type_round_to" name="type_round_to" step="0.01" min="0" value="9.90" required>
                        <div class="form-text">Il valore a cui arrotondare il prezzo (es. 9.90, 4.99)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_description" class="form-label">Descrizione:</label>
                        <textarea class="form-control" id="type_description" name="type_description" rows="2"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salva tipologia
                    </button>
                </form>
                
                <hr>
                
                <div class="mt-4">
                    <h6>Tipologie configurate:</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Moltiplicatore</th>
                                    <th>Arrotonda a</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productTypes as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['nome']); ?></td>
                                        <td>x<?php echo number_format($type['moltiplicatore_prezzo'], 2); ?></td>
                                        <td>€<?php echo number_format($type['arrotonda_a'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-product-type" 
                                                    data-id="<?php echo $type['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($type['nome']); ?>"
                                                    data-multiplier="<?php echo $type['moltiplicatore_prezzo']; ?>"
                                                    data-round-to="<?php echo $type['arrotonda_a']; ?>"
                                                    data-description="<?php echo htmlspecialchars($type['descrizione'] ?? ''); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="impostazioni.php?delete_type=<?php echo $type['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sei sicuro di voler eliminare questa tipologia?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($productTypes)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Nessuna tipologia trovata.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Aggiornamento Cache</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary update-cache" data-type="suppliers">
                                <i class="bi bi-arrow-repeat"></i> Fornitori
                            </button>
                            <div id="suppliers_cache_result" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary update-cache" data-type="brands">
                                <i class="bi bi-arrow-repeat"></i> Marche
                            </button>
                            <div id="brands_cache_result" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary update-cache" data-type="taxes">
                                <i class="bi bi-arrow-repeat"></i> Aliquote IVA
                            </button>
                            <div id="taxes_cache_result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary update-cache" data-type="seasons">
                                <i class="bi bi-arrow-repeat"></i> Stagioni
                            </button>
                            <div id="seasons_cache_result" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary update-cache" data-type="genders">
                                <i class="bi bi-arrow-repeat"></i> Generi
                            </button>
                            <div id="genders_cache_result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mb-0">
                    <small>L'aggiornamento della cache sincronizza i dati da Smarty per migliorare le prestazioni.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script JavaScript per la pagina -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pulsante per il test dell'API
    const testApiBtn = document.getElementById('test-api-btn');
    const apiTestResult = document.getElementById('api-test-result');
    
    if (testApiBtn && apiTestResult) {
        testApiBtn.addEventListener('click', function() {
            // Mostra indicatore di caricamento
            apiTestResult.innerHTML = '<div class="spinner-border spinner-border-sm text-info" role="status"><span class="visually-hidden">Verifica in corso...</span></div> Verifica in corso...';
            
            // Esegue la chiamata di test
            fetch('api_check.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        apiTestResult.innerHTML = '<div class="alert alert-success mb-0">' + data.message + ' (Versione API: ' + (data.api_version || "N/A") + ')</div>';
                    } else {
                        apiTestResult.innerHTML = '<div class="alert alert-danger mb-0">Errore: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    apiTestResult.innerHTML = '<div class="alert alert-danger mb-0">Si è verificato un errore durante la verifica: ' + error.message + '</div>';
                });
        });
    }
    
    // Gestione del pulsante di modifica configurazione API
    const editApiButtons = document.querySelectorAll('.edit-api');
    
    editApiButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const key = this.dataset.key;
            const url = this.dataset.url;
            const isDefault = this.dataset.default === '1';
            
            // Popola il form con i dati
            document.getElementById('api_name').value = name;
            document.getElementById('api_key').value = key;
            document.getElementById('api_url').value = url;
            document.getElementById('is_default').checked = isDefault;
            
            // Cambia il testo del pulsante di salvataggio
            const submitBtn = document.querySelector('form[action="impostazioni.php"] button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="bi bi-save"></i> Aggiorna configurazione';
            }
            
            // Scorri fino al form
            document.querySelector('form[action="impostazioni.php"]').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });
    
    // Gestione del pulsante di modifica tipologia prodotto
    const editProductTypeButtons = document.querySelectorAll('.edit-product-type');
    
    editProductTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const multiplier = this.dataset.multiplier;
            const roundTo = this.dataset.roundTo;
            const description = this.dataset.description;
            
            // Popola il form con i dati
            document.getElementById('type_name').value = name;
            document.getElementById('type_multiplier').value = multiplier;
            document.getElementById('type_round_to').value = roundTo;
            document.getElementById('type_description').value = description;
            
            // Cambia il testo del pulsante di salvataggio
            const submitBtn = document.querySelector('form[action="impostazioni.php"] button[type="submit"]');
            if (submitBtn && submitBtn.parentElement.querySelector('input[name="save_product_type"]')) {
                submitBtn.innerHTML = '<i class="bi bi-save"></i> Aggiorna tipologia';
            }
            
            // Scorri fino al form
            document.querySelector('form[action="impostazioni.php"] input[name="save_product_type"]').parentElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });
    
    // Gestione del pulsante per aggiornare la cache
    const updateCacheButtons = document.querySelectorAll('.update-cache');
    
    if (updateCacheButtons.length > 0) {
        updateCacheButtons.forEach(button => {
            button.addEventListener('click', function() {
                const type = this.dataset.type;
                const resultElement = document.getElementById(type + '_cache_result');
                
                // Disabilita il pulsante durante l'operazione
                this.disabled = true;
                
                // Mostra indicatore di caricamento
                resultElement.innerHTML = '<div class="spinner-border spinner-border-sm text-info" role="status"><span class="visually-hidden">Aggiornamento in corso...</span></div> Aggiornamento in corso...';
                
                // Chiama l'API per aggiornare la cache
                fetch('api_update_cache.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'type=' + encodeURIComponent(type)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultElement.innerHTML = '<div class="alert alert-success mb-0">' + data.message + '</div>';
                    } else {
                        resultElement.innerHTML = '<div class="alert alert-danger mb-0">Errore: ' + data.message + '</div>';
                    }
                    // Riabilita il pulsante
                    this.disabled = false;
                })
                .catch(error => {
                    console.error('Errore:', error);
                    resultElement.innerHTML = '<div class="alert alert-danger mb-0">Si è verificato un errore: ' + error.message + '</div>';
                    // Riabilita il pulsante
                    this.disabled = false;
                });
            });
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestisci gli aggiornamenti delle cache
    const updateButtons = document.querySelectorAll('.update-cache-btn');
    
    updateButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const cacheType = this.getAttribute('data-cache-type');
            const resultElement = document.getElementById(cacheType + '-result');
            
            // Mostra messaggio di caricamento
            if (resultElement) {
                resultElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aggiornamento in corso...';
            }
            
            // Esegui la richiesta AJAX
            $.ajax({
                url: 'api_update_cache.php',
                method: 'POST',
                data: { type: cacheType },
                dataType: 'json',
                success: function(response) {
                    console.log('Risposta aggiornamento cache:', response);
                    
                    if (resultElement) {
                        if (response.success) {
                            resultElement.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> ' + response.message + '</span>';
                        } else {
                            resultElement.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> ' + response.message + '</span>';
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Errore AJAX:', error);
                    console.log(xhr.responseText);
                    
                    if (resultElement) {
                        resultElement.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Errore di comunicazione con il server</span>';
                    }
                }
            });
        });
    });
});
</script>
<?php
// Include il footer
include 'footer.php';
?>