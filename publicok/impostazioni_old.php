<?php
// Abilita reporting completo degli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

try {
    require_once 'config.php';
    require_once 'functions.php';

    // Verifica autenticazione
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        error_log('Utente non autenticato in impostazioni.php');
        header('Location: login.php');
        exit;
    }

    // Titolo della pagina
    $pageTitle = 'Impostazioni';

    // Connessione al database
    $conn = getDbConnection();

    // Verifica connessione database
    if (!$conn) {
        throw new Exception('Impossibile connettersi al database');
    }

    // Messaggio di notifica
    $notification = '';

    // Resto del codice esistente per la gestione dei form e delle azioni

    // Ottieni le configurazioni API
    $stmt = $conn->query("SELECT * FROM impostazioni_api ORDER BY predefinito DESC, nome ASC");
    $apiConfigs = $stmt->fetchAll();

    // Ottieni le tipologie di prodotto
    $stmt = $conn->query("SELECT * FROM tipologie_prodotto ORDER BY nome ASC");
    $productTypes = $stmt->fetchAll();

    // Include l'header
    include 'header.php';

    // Mostra la notifica se presente
    if (!empty($notification)) {
        echo '<div class="alert alert-' . $notification['type'] . ' alert-dismissible fade show mb-4" role="alert">';
        echo $notification['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>';
        echo '</div>';
    }
if (isset($_GET['action']) && $_GET['action'] === 'test_api') {
    error_log("DEBUG: API test chiamato da impostazioni.php");
echo json_encode(["debug" => "API test chiamato"]);
exit;
    require_once 'config.php';
    require_once 'functions.php';
    
require_once 'config.php';
require_once 'functions.php';

$conn = getDbConnection();
$stmt = $conn->query("SELECT api_key FROM impostazioni_api WHERE predefinito = 1 LIMIT 1");
$apiKey = $stmt->fetchColumn();

if (!$apiKey) {
    echo json_encode(['success' => false, 'message' => 'Nessuna API Key configurata']);
    exit;
}

$response = callSmartyApi('Products/list', 'GET', null, $apiKey);
error_log("DEBUG: Sto per chiamare callSmartyApi con API Key: " . ($apiKey ?? 'Nessuna API Key'));
header('Content-Type: application/json');
echo json_encode($response);
exit;    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
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
                <p>Configura le tipologie di prodotto e le relative regole di calcolo del prezzo.</p>
                
                <form action="impostazioni.php" method="post">
                    <input type="hidden" name="save_product_type" value="1">
                    
                    <div class="mb-3">
                        <label for="type_name" class="form-label">Nome tipologia:</label>
                        <input type="text" class="form-control" id="type_name" name="type_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_multiplier" class="form-label">Moltiplicatore prezzo:</label>
                        <input type="number" step="0.1" min="1" class="form-control" id="type_multiplier" name="type_multiplier" value="3.0" required>
                        <div class="form-text">Il prezzo di acquisto verrà moltiplicato per questo valore</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_round_to" class="form-label">Arrotonda a:</label>
                        <input type="number" step="0.1" min="0" class="form-control" id="type_round_to" name="type_round_to" value="9.90" required>
                        <div class="form-text">Il prezzo verrà arrotondato a questo valore (es. 24.90, 29.90, ecc.)</div>
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
                                        <td>×<?php echo number_format($type['moltiplicatore_prezzo'], 1); ?></td>
                                        <td>€<?php echo number_format($type['arrotonda_a'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-type" 
                                                    data-id="<?php echo $type['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($type['nome']); ?>"
                                                    data-multiplier="<?php echo $type['moltiplicatore_prezzo']; ?>"
                                                    data-roundto="<?php echo $type['arrotonda_a']; ?>"
                                                    data-description="<?php echo htmlspecialchars($type['descrizione']); ?>">
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
                <p>Aggiorna le informazioni memorizzate nella cache locale.</p>
                
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" id="refresh-suppliers">
                        <i class="bi bi-arrow-repeat"></i> Aggiorna lista fornitori
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="refresh-brands">
                        <i class="bi bi-arrow-repeat"></i> Aggiorna lista marche
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="refresh-taxes">
                        <i class="bi bi-arrow-repeat"></i> Aggiorna aliquote IVA
                    </button>
                </div>
                
                <div class="mt-3" id="cache-result"></div>
            </div>
        </div>
    </div>
</div>

<?php
    // Definizione degli script aggiuntivi
 $additionalScripts = <<<'EOT'
<script>
$(document).ready(function() {
    // Test della connessione API
    $('#test-api-btn').on('click', function() {
        const resultContainer = $('#api-test-result');
        
        resultContainer.html(`
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Verifica in corso...</span>
                    </div>
                    <span>Verifica della connessione in corso...</span>
                </div>
            </div>
        `);
        
$.ajax({
    url: 'impostazioni.php?action=test_api',
    type: 'GET',
    dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    resultContainer.html(`
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Connessione a Smarty API attiva.
                            ${response.api_version ? `<br>Versione API: ${response.api_version}` : ''}
                        </div>
                    `);
                } else {
                    resultContainer.html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Errore di connessione a Smarty API: ${(response && response.message) || 'Stato sconosciuto'}
                        </div>
                    `);
                }
            },
            error: function() {
                resultContainer.html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Impossibile verificare lo stato della connessione.
                    </div>
                `);
            }
        });
    });
    
    // Aggiornamento della cache
    function updateCache(type) {
        const resultContainer = $('#cache-result');
        
        resultContainer.html(`
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Aggiornamento in corso...</span>
                    </div>
                    <span>Aggiornamento cache in corso...</span>
                </div>
            </div>
        `);
        
        $.ajax({
            url: 'api_update_cache.php',
            type: 'POST',
            data: { type: type },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    resultContainer.html(`
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            ${response.message || 'Aggiornamento completato'}
                        </div>
                    `);
                } else {
                    resultContainer.html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Errore: ${(response && response.message) || 'Errore sconosciuto'}
                        </div>
                    `);
                }
            },
            error: function() {
                resultContainer.html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Errore di comunicazione con il server.
                    </div>
                `);
            }
        });
    }
    
    $('#refresh-suppliers').on('click', function() {
        updateCache('suppliers');
    });
    
    $('#refresh-brands').on('click', function() {
        updateCache('brands');
    });
    
    $('#refresh-taxes').on('click', function() {
        updateCache('taxes');
    });
    
    // Modifica configurazione API
    $('.edit-api').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const key = $(this).data('key');
        const url = $(this).data('url');
        const isDefault = $(this).data('default');
        
        $('#api_name').val(name);
        $('#api_key').val(key);
        $('#api_url').val(url);
        $('#is_default').prop('checked', isDefault === 1);
        
        // Scorri alla form
        $('html, body').animate({
            scrollTop: $('#api_name').offset().top - 100
        }, 500);
    });
    
    // Modifica tipologia prodotto
    $('.edit-type').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const multiplier = $(this).data('multiplier');
        const roundTo = $(this).data('roundto');
        const description = $(this).data('description');
        
        $('#type_name').val(name);
        $('#type_multiplier').val(multiplier);
        $('#type_round_to').val(roundTo);
        $('#type_description').val(description);
        
        // Scorri alla form
        $('html, body').animate({
            scrollTop: $('#type_name').offset().top - 100
        }, 500);
    });
});
</script>
EOT;

// Include il footer
include 'footer.php';

} catch (Exception $e) {
    // Log dell'errore dettagliato
    error_log('Errore critico in impostazioni.php: ' . $e->getMessage());
    error_log('Traccia dello stack: ' . $e->getTraceAsString());

    // Mostra un messaggio di errore generico
    http_response_code(500);
    die("Si è verificato un errore interno. Contatta l'amministratore.");
}
?>
