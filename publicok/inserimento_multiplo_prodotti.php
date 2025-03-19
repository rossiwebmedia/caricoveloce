<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'funzioni_cache.php'; // Aggiungi questa riga per risolvere l'errore getAliquoteIVA

// Oppure definisci direttamente la funzione se non vuoi includere il file
if (!function_exists('getAliquoteIVA')) {
    function getAliquoteIVA() {
        return [
            ['id' => 1, 'name' => 'IVA 22%', 'value' => 22],
            ['id' => 2, 'name' => 'IVA 10%', 'value' => 10],
            ['id' => 3, 'name' => 'IVA 4%', 'value' => 4],
            ['id' => 4, 'name' => 'IVA 0%', 'value' => 0]
        ];
    }
}

// Controlla se l'utente è autenticato
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Ottieni la lista dei fornitori per il menu a tendina
$fornitori = [];
try {
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT id, business_name, nome FROM cache_fornitori ORDER BY business_name");
    while ($row = $stmt->fetch()) {
        $fornitori[$row['id']] = $row['business_name'] ?: $row['nome'];
    }
} catch (Exception $e) {
    logMessage("Errore nel recupero fornitori: " . $e->getMessage(), 'ERROR');
}

// Recupera altri dati necessari per i form
$stagioni = getStagioni();
$generi = getGeneri();
$marche = [];
try {
    $stmt = $conn->query("SELECT brand_id, name FROM cache_marche ORDER BY name");
    while ($row = $stmt->fetch()) {
        $marche[$row['brand_id']] = $row['name'];
    }
} catch (Exception $e) {
    logMessage("Errore nel recupero marche: " . $e->getMessage(), 'ERROR');
}

// Recupera le tipologie di prodotto disponibili
$tipologie = [];
try {
    $stmt = $conn->query("SELECT nome FROM tipologie_prodotto ORDER BY nome");
    while ($row = $stmt->fetch()) {
        $tipologie[] = $row['nome'];
    }
} catch (Exception $e) {
    logMessage("Errore nel recupero tipologie: " . $e->getMessage(), 'ERROR');
    // Tipologie predefinite in caso di errore
    $tipologie = ['uomo', 'donna', 'bambino', 'bambina', 'accessori'];
}

// Recupera le aliquote IVA disponibili
$aliquoteIVA = [];
try {
    $aliquoteList = getAliquoteIVA();
    foreach ($aliquoteList as $aliquota) {
        $value = isset($aliquota['value']) ? floatval($aliquota['value']) : 22;
        $name = isset($aliquota['name']) ? $aliquota['name'] : ($value . '%');
        $aliquoteIVA[$value] = $name;
    }
} catch (Exception $e) {
    logMessage("Errore nel recupero aliquote IVA: " . $e->getMessage(), 'ERROR');
    $aliquoteIVA = [22 => '22%', 10 => '10%', 4 => '4%', 0 => '0%'];
}

// Header e menu
include 'header.php';
?>

<div class="container-fluid pt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Inserimento Multiplo Prodotti</h1>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Seleziona Fornitore e Configurazione</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="fornitore_id" class="form-label">Fornitore</label>
                                <select id="fornitore_id" class="form-control form-select" required>
                                    <option value="">Seleziona un fornitore</option>
                                    <?php foreach ($fornitori as $id => $nome): ?>
                                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="default_tipologia" class="form-label">Tipologia predefinita</label>
                                <select id="default_tipologia" class="form-control form-select">
                                    <option value="">Seleziona tipologia...</option>
                                    <?php foreach ($tipologie as $tipologia): ?>
                                        <option value="<?= htmlspecialchars($tipologia) ?>"><?= htmlspecialchars($tipologia) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Verrà applicata a tutti i nuovi prodotti</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="default_aliquota" class="form-label">Aliquota IVA predefinita</label>
                                <select id="default_aliquota" class="form-control form-select">
                                    <?php foreach ($aliquoteIVA as $value => $name): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= ($value == 22) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Verrà applicata a tutti i nuovi prodotti</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Seleziona prima il fornitore e le configurazioni predefinite, poi potrai inserire i prodotti.
                    </div>
                </div>
            </div>
            
            <div id="prodotti_container" class="card mb-4" style="display:none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Prodotti</h5>
                    <button type="button" id="add_row_btn" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Aggiungi Prodotto
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="prodotti_table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px">#</th>
                                    <th>Marca</th>
                                    <th>Modello</th>
                                    <th>Genere</th>
                                    <th>Tipologia</th>
                                    <th>Stagione</th>
                                    <th>Quantità</th>
                                    <th>Prezzo Acquisto</th>
                                    <th>Prezzo Vendita</th>
                                    <th style="width: 100px">Stato</th>
                                    <th style="width: 80px">Azioni</th>
                                </tr>
                            </thead>
                            <tbody id="prodotti_tbody">
                                <!-- Le righe dei prodotti saranno aggiunte qui dinamicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <button type="button" id="save_all_btn" class="btn btn-success">
                            <i class="fas fa-save"></i> Salva Tutti
                        </button>
                        <span id="autosave_status" class="text-muted">
                            <i class="fas fa-circle-notch fa-spin" style="display:none"></i>
                            <span class="autosave-text">Autosalvataggio attivo</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template per nuova riga prodotto -->
<template id="product_row_template">
    <tr class="product-row" data-row-id="{ROW_ID}" data-saved="0">
        <td class="row-number">{NUMBER}</td>
        <td>
            <select class="form-control form-select marca-select" name="marca_id" required>
                <option value="">Seleziona...</option>
                <?php foreach ($marche as $id => $nome): ?>
                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($nome) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" class="form-control modello-input" name="modello" required>
        </td>
        <td>
            <select class="form-control form-select genere-select" name="genere" required>
                <option value="">Seleziona...</option>
                <?php foreach ($generi as $key => $nome): 
                    if (empty($key)) continue; ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($nome) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-control form-select tipologia-select" name="tipologia" required>
                <option value="">Seleziona...</option>
                <?php foreach ($tipologie as $tipologia): ?>
                    <option value="<?= htmlspecialchars($tipologia) ?>"><?= htmlspecialchars($tipologia) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-control form-select stagione-select" name="stagione" required>
                <option value="">Seleziona...</option>
                <?php foreach ($stagioni as $key => $nome): 
                    if (empty($key)) continue; ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($nome) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control qty-input" name="quantita" value="1" min="1" required>
        </td>
        <td>
            <div class="input-group">
                <input type="number" step="0.01" class="form-control prezzo-acquisto-input" name="prezzo_acquisto" required>
                <span class="input-group-text">€</span>
            </div>
        </td>
        <td>
            <div class="input-group">
                <input type="number" step="0.01" class="form-control prezzo-vendita-input" name="prezzo_vendita">
                <span class="input-group-text">€</span>
            </div>
        </td>
        <td class="product-status">
            <span class="badge bg-secondary">Non salvato</span>
        </td>
        <td class="text-center">
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary save-row-btn" title="Salva">
                    <i class="fas fa-save"></i>
                </button>
                <button type="button" class="btn btn-outline-danger delete-row-btn" title="Elimina">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>
</template>

<!-- JavaScript per la gestione dinamica -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variabili globali
    const prodottiContainer = document.getElementById('prodotti_container');
    const prodottiTbody = document.getElementById('prodotti_tbody');
    const rowTemplate = document.getElementById('product_row_template').innerHTML;
    let rowCounter = 1;
    let autoSaveTimer = null;
    let selectedFornitoreId = null;
    let defaultTipologia = '';
    let defaultAliquotaIva = 22;
    
    // Gestione della selezione del fornitore
    document.getElementById('fornitore_id').addEventListener('change', function() {
        selectedFornitoreId = this.value;
        updateProdottiContainerVisibility();
    });
    
    // Gestione della tipologia predefinita
    document.getElementById('default_tipologia').addEventListener('change', function() {
        defaultTipologia = this.value;
        updateProdottiContainerVisibility();
        
        // Aggiorna la tipologia in tutte le righe esistenti
        if (defaultTipologia) {
            document.querySelectorAll('.tipologia-select').forEach(select => {
                if (!select.value) {
                    select.value = defaultTipologia;
                }
            });
        }
    });
    
    // Gestione dell'aliquota IVA predefinita
    document.getElementById('default_aliquota').addEventListener('change', function() {
        defaultAliquotaIva = this.value;
    });
    
    // Funzione per verificare se mostrare il container dei prodotti
    function updateProdottiContainerVisibility() {
        if (selectedFornitoreId) {
            prodottiContainer.style.display = 'block';
            // Aggiungi la prima riga se non ce ne sono
            if (prodottiTbody.children.length === 0) {
                addNewRow();
            }
        } else {
            prodottiContainer.style.display = 'none';
        }
    }
    
    // Pulsante per aggiungere una nuova riga
    document.getElementById('add_row_btn').addEventListener('click', function() {
        addNewRow();
    });
    
    // Pulsante salva tutti
    document.getElementById('save_all_btn').addEventListener('click', function() {
        saveAllProducts();
    });
    
    // Funzione per aggiungere una nuova riga
  function addNewRow() {
    const rowId = 'row_' + Date.now();
    const newRow = rowTemplate
        .replace('{ROW_ID}', rowId)
        .replace('{NUMBER}', rowCounter);
    
    // Usa una table temporanea per creare un elemento TR correttamente
    const tempTable = document.createElement('table');
    tempTable.innerHTML = newRow.trim();
    const rowNode = tempTable.querySelector('tr');
    
    prodottiTbody.appendChild(rowNode);
    rowCounter++;
    
    // Aggiungi gli event listeners alla nuova riga
    setupRowEventListeners(rowNode);
}
    
    // Configura gli eventi per una riga
    function setupRowEventListeners(row) {
        // Calcola prezzo vendita quando cambia il prezzo acquisto
        const prezzoAcquistoInput = row.querySelector('.prezzo-acquisto-input');
        const prezzoVenditaInput = row.querySelector('.prezzo-vendita-input');
        const genereSelect = row.querySelector('.genere-select');
        const tipologiaSelect = row.querySelector('.tipologia-select');
        
        prezzoAcquistoInput.addEventListener('change', function() {
            let tipologia = tipologiaSelect.value || 'unisex';
            let prezzoAcquisto = parseFloat(this.value) || 0;
            
            // Calcola il prezzo di vendita chiamando l'API
            fetch('api_calcola_prezzo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `prezzo_acquisto=${prezzoAcquisto}&tipologia=${tipologia}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    prezzoVenditaInput.value = data.prezzo_vendita;
                } else {
                    // Fallback al calcolo locale
                    let moltiplicatore = 3.0;
                    let arrotondaA = 9.90;
                    let prezzo = prezzoAcquisto * moltiplicatore;
                    
                    // Arrotonda al valore più vicino (es. 24.90, 29.90, ecc.)
                    let resto = prezzo % 10;
                    if (resto > arrotondaA) {
                        prezzo = Math.floor(prezzo / 10) * 10 + arrotondaA;
                    } else {
                        prezzo = Math.floor(prezzo / 10) * 10 - (10 - arrotondaA);
                        if (prezzo < 0) prezzo = arrotondaA;
                    }
                    
                    prezzoVenditaInput.value = prezzo.toFixed(2);
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                // Fallback al calcolo locale in caso di errore
                let prezzo = prezzoAcquisto * 3.0;
                prezzoVenditaInput.value = Math.ceil(prezzo).toFixed(2);
            });
        });
        
        // Pulsante salva riga
        row.querySelector('.save-row-btn').addEventListener('click', function() {
            saveProductRow(row);
        });
        
        // Pulsante elimina riga
        row.querySelector('.delete-row-btn').addEventListener('click', function() {
            if (confirm('Sei sicuro di voler eliminare questo prodotto?')) {
                row.remove();
                updateRowNumbers();
            }
        });
        
        // Attiva autosave quando un campo cambia
        row.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('change', function() {
                scheduleAutoSave(row);
            });
        });
    }
    
    // Aggiorna i numeri di riga dopo eliminazione
    function updateRowNumbers() {
        let counter = 1;
        document.querySelectorAll('#prodotti_tbody .row-number').forEach(cell => {
            cell.textContent = counter++;
        });
        rowCounter = counter;
    }
    
    // Pianifica autosalvataggio dopo inattività
    function scheduleAutoSave(row) {
        // Imposta lo stato come "modificato"
        const statusCell = row.querySelector('.product-status');
        statusCell.innerHTML = '<span class="badge bg-warning">Modificato</span>';
        row.setAttribute('data-saved', '0');
        
        // Rimuovi il timer precedente e imposta uno nuovo
        clearTimeout(autoSaveTimer);
        document.querySelector('#autosave_status .fa-spin').style.display = 'inline-block';
        document.querySelector('.autosave-text').textContent = 'Modifiche in attesa di salvataggio...';
        
        autoSaveTimer = setTimeout(() => {
            saveAllProducts();
        }, 3000); // Autosalva dopo 3 secondi di inattività
    }
    
    // Salva una riga prodotto
    function saveProductRow(row) {
        if (!selectedFornitoreId) {
            alert('Seleziona prima un fornitore');
            return;
        }
        
        const statusCell = row.querySelector('.product-status');
        statusCell.innerHTML = '<span class="badge bg-info"><i class="fas fa-circle-notch fa-spin"></i> Salvataggio...</span>';
        
        // Raccogli i dati
        const rowId = row.getAttribute('data-row-id');
        const productId = row.getAttribute('data-product-id') || '';
        const marcaId = row.querySelector('.marca-select').value;
        const modello = row.querySelector('.modello-input').value;
        const genere = row.querySelector('.genere-select').value;
        const tipologia = row.querySelector('.tipologia-select').value;
        const stagione = row.querySelector('.stagione-select').value;
        const quantita = row.querySelector('.qty-input').value;
        const prezzoAcquisto = row.querySelector('.prezzo-acquisto-input').value;
        const prezzoVendita = row.querySelector('.prezzo-vendita-input').value;
        
        // Valida i campi obbligatori
        if (!marcaId || !modello || !genere || !tipologia || !stagione || !quantita || !prezzoAcquisto) {
            statusCell.innerHTML = '<span class="badge bg-danger">Dati incompleti</span>';
            return;
        }
        
        // Prepara i dati per l'invio
        const formData = new FormData();
        formData.append('action', 'save_product');
        formData.append('fornitore_id', selectedFornitoreId);
        formData.append('product_id', productId);
        formData.append('marca_id', marcaId);
        formData.append('modello', modello);
        formData.append('genere', genere);
        formData.append('tipologia', tipologia);
        formData.append('stagione', stagione);
        formData.append('quantita', quantita);
        formData.append('prezzo_acquisto', prezzoAcquisto);
        formData.append('prezzo_vendita', prezzoVendita);
        formData.append('aliquota_iva', defaultAliquotaIva);
        
        // Invia i dati
        fetch('ajax_prodotti_multipli.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusCell.innerHTML = '<span class="badge bg-success">Salvato</span>';
                row.setAttribute('data-saved', '1');
                row.setAttribute('data-product-id', data.product_id);
                
                // Se è stato generato un EAN, mostralo
                if (data.ean) {
                    statusCell.innerHTML = '<span class="badge bg-success">Salvato</span>' +
                        '<br><small class="text-muted">EAN: ' + data.ean + '</small>';
                }
            } else {
                statusCell.innerHTML = '<span class="badge bg-danger" title="' + (data.message || 'Errore sconosciuto') + '">Errore</span>';
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            statusCell.innerHTML = '<span class="badge bg-danger">Errore di rete</span>';
        });
    }
    
    // Salva tutti i prodotti
    function saveAllProducts() {
        clearTimeout(autoSaveTimer);
        document.querySelector('#autosave_status .fa-spin').style.display = 'inline-block';
        document.querySelector('.autosave-text').textContent = 'Salvataggio di tutti i prodotti...';
        
        const rows = document.querySelectorAll('#prodotti_tbody .product-row[data-saved="0"]');
        let savedCount = 0;
        
        rows.forEach(row => {
            saveProductRow(row);
        });
        
        setTimeout(() => {
            document.querySelector('#autosave_status .fa-spin').style.display = 'none';
            document.querySelector('.autosave-text').textContent = 'Autosalvataggio attivo';
        }, 2000);
    }
});
</script>

<?php include 'footer.php'; ?>