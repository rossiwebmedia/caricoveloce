<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'funzioni_cache.php'; // File contenente le funzioni che abbiamo creato

// Titolo della pagina
$pageTitle = 'Inserimento Prodotti';

// Include l'header
include 'header.php';

// Connessione al database
$conn = getDbConnection();

// Carica i preset di taglie e colori
$tagliePresets = getAllTagliePreset();
$coloriPresets = getAllColoriPreset();

// Ottieni le aliquote IVA dalla cache
$aliquoteIVA = getAliquoteIVA();
if (empty($aliquoteIVA)) {
    $aliquoteIVA = [
        ['id' => 1, 'name' => 'IVA 22%', 'value' => 22],
        ['id' => 2, 'name' => 'IVA 10%', 'value' => 10],
        ['id' => 3, 'name' => 'IVA 4%', 'value' => 4],
        ['id' => 4, 'name' => 'IVA 0%', 'value' => 0]
    ];
}

// Ottieni generi e stagioni
$generi = getGeneri();
$stagioni = getStagioni();
// Verifica se sono vuoti e nel caso caricali dal database
if (empty($generi)) {
    $generi = [
        '' => 'Seleziona...',
        'uomo' => 'Uomo', 
        'donna' => 'Donna',
        'unisex' => 'Unisex',
        'bambino' => 'Bambino',
        'bambina' => 'Bambina'
    ];
}

if (empty($stagioni)) {
    $stagioni = [
        '' => 'Seleziona...',
        'primavera_estate' => 'Primavera/Estate',
        'autunno_inverno' => 'Autunno/Inverno',
        'quattro_stagioni' => 'Quattro Stagioni'
    ];
}
// Ottieni le marche dalla cache
$marche = getMarche();
$marcheLista = [];
foreach ($marche as $marca) {
    if (isset($marca['name']) && !empty($marca['name'])) {
        $marcheLista[$marca['name']] = $marca['name'];
    }
}
if (empty($marcheLista)) {
    $marcheLista = ['Ciabalù' => 'Ciabalù'];
}

// Messaggio di notifica
if (isset($_SESSION['notification'])) {
    $type = $_SESSION['notification']['type'];
    $message = $_SESSION['notification']['message'];
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show mb-4" role="alert">';
    echo $message;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    unset($_SESSION['notification']);
}

// Modalità modifica
$isEditMode = false;
$prodotto = null;

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $prodottoId = intval($_GET['edit']);
    
    // Carica il prodotto dal database
    $stmt = $conn->prepare("SELECT * FROM prodotti WHERE id = ?");
    $stmt->execute([$prodottoId]);
    $prodotto = $stmt->fetch();
    
    if ($prodotto) {
        $isEditMode = true;
        
        // Carica le varianti se il prodotto è parent
        $varianti = [];
        if (empty($prodotto['parent_sku'])) {
            $stmt = $conn->prepare("SELECT * FROM prodotti WHERE parent_sku = ?");
            $stmt->execute([$prodotto['sku']]);
            $varianti = $stmt->fetchAll();
        }
    }
}
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo $isEditMode ? 'Modifica Prodotto' : 'Inserimento Prodotto'; ?></h5>
        <?php if ($isEditMode): ?>
        <a href="inserimento_prodotti.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-circle"></i> Nuovo Prodotto
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form action="salva_prodotto.php" method="post" id="productForm">
            <?php if ($isEditMode): ?>
            <input type="hidden" name="product_id" value="<?php echo $prodotto['id']; ?>">
            <input type="hidden" name="is_edit" value="1">
            <?php endif; ?>
            
            <!-- Tipo di prodotto -->
          
PHP
<div class="row mb-3">
    <div class="col-md-6">
        <label for="tipo_prodotto" class="form-label">Tipo di Prodotto:</label>
        <select id="tipo_prodotto" name="tipo_prodotto" class="form-select" required <?php echo $isEditMode ? 'disabled' : ''; ?>>
            <option value="">Seleziona...</option>
            <option value="semplice" <?php echo ($isEditMode && empty($varianti)) ? 'selected' : ''; ?>>Prodotto Semplice (senza varianti)</option>
            <option value="taglia" <?php echo ($isEditMode && !empty($varianti) && !empty($varianti[0]['taglia']) && empty($varianti[0]['colore'])) ? 'selected' : ''; ?>>Prodotto con Varianti di Taglia</option>
            <option value="colore" <?php echo ($isEditMode && !empty($varianti) && empty($varianti[0]['taglia']) && !empty($varianti[0]['colore'])) ? 'selected' : ''; ?>>Prodotto con Varianti di Colore</option>
            <option value="taglia_colore" <?php echo ($isEditMode && !empty($varianti) && !empty($varianti[0]['taglia']) && !empty($varianti[0]['colore'])) ? 'selected' : ''; ?>>Prodotto con Varianti di Taglia e Colore</option>
        </select>
        <?php if ($isEditMode): ?>
        <input type="hidden" name="tipo_prodotto" value="<?php echo !empty($varianti) ? (!empty($varianti[0]['taglia']) ? (!empty($varianti[0]['colore']) ? 'taglia_colore' : 'taglia') : 'colore') : 'semplice'; ?>">
        <?php endif; ?>
    </div>
                
                <div class="col-md-6">
    <label for="tipologia" class="form-label">Tipologia:</label>
    <select id="tipologia" name="tipologia" class="form-select" required>
        <option value="">Seleziona...</option>
        <?php
        // Carica le tipologie di prodotto dal database
        $stmt = $conn->query("SELECT nome FROM tipologie_prodotto ORDER BY nome ASC");
        $tipologie_db = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Se non ci sono tipologie nel database, usa le opzioni predefinite
        if (empty($tipologie_db)) {
            $tipologie_db = ['uomo', 'donna', 'scarpe_uomo', 'scarpe_donna', 'accessori'];
        }
        
        foreach($tipologie_db as $tipologia_nome): 
            $selected = ($isEditMode && $prodotto['tipologia'] == $tipologia_nome) ? 'selected' : '';
        ?>
            <option value="<?php echo $tipologia_nome; ?>" <?php echo $selected; ?>>
                <?php echo ucfirst(str_replace('_', ' ', $tipologia_nome)); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nome_modello" class="form-label">Nome Modello:</label>
                    <input type="text" id="nome_modello" name="nome_modello" class="form-control" required <?php echo $isEditMode ? 'readonly' : ''; ?> value="<?php echo $isEditMode ? htmlspecialchars($prodotto['sku']) : ''; ?>">
                    <small class="form-text text-muted">Utilizzare nomi senza spazi (es. NomeModello)</small>
                </div>
                
                <div class="col-md-6">
                    <label for="titolo" class="form-label">Titolo Prodotto:</label>
                    <input type="text" id="titolo" name="titolo" class="form-control" value="<?php echo $isEditMode ? htmlspecialchars($prodotto['titolo']) : ''; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="prezzo_acquisto" class="form-label">Prezzo di Acquisto (Imponibile):</label>
                    <div class="input-group">
                        <span class="input-group-text">€</span>
                        <input type="number" id="prezzo_acquisto" name="prezzo_acquisto" class="form-control" step="0.01" min="0.01" required value="<?php echo $isEditMode ? number_format($prodotto['prezzo_acquisto'], 2, '.', '') : ''; ?>">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label for="prezzo_vendita" class="form-label">Prezzo di Vendita:</label>
                    <div class="input-group">
                        <span class="input-group-text">€</span>
                        <input type="number" id="prezzo_vendita" name="prezzo_vendita" class="form-control" step="0.01" min="0.01" required value="<?php echo $isEditMode ? number_format($prodotto['prezzo_vendita'], 2, '.', '') : ''; ?>">
                        <button type="button" id="calcola_prezzo" class="btn btn-outline-secondary">Calcola</button>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="aliquota_iva" class="form-label">Aliquota IVA:</label>
                    <select id="aliquota_iva" name="aliquota_iva" class="form-select">
                        <?php foreach($aliquoteIVA as $aliquota): ?>
                            <?php $aliquotaValue = isset($aliquota['value']) ? $aliquota['value'] : (isset($aliquota['id']) ? $aliquota['id'] : $aliquota['name']); ?>
                            <option value="<?php echo $aliquotaValue; ?>" <?php echo ($isEditMode && $prodotto['aliquota_iva'] == $aliquotaValue) ? 'selected' : ($aliquotaValue == 22 ? 'selected' : ''); ?>>
                                <?php echo isset($aliquota['name']) ? $aliquota['name'] : $aliquotaValue . '%'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="genere" class="form-label">Genere:</label>
                    <select id="genere" name="genere" class="form-select">
                        <option value="">Seleziona...</option>
                        <?php foreach($generi as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($isEditMode && $prodotto['genere'] == $key) ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
               <div class="col-md-6">
    <label for="stagione" class="form-label">Stagione:</label>
    <div class="input-group">
        <select id="stagione" name="stagione" class="form-select">
            <option value="">Seleziona...</option>
            <option value="SS25" <?php echo ($isEditMode && $prodotto['stagione'] == 'SS25') ? 'selected' : ''; ?>>SS25</option>
            <option value="FW25" <?php echo ($isEditMode && $prodotto['stagione'] == 'FW25') ? 'selected' : ''; ?>>FW25</option>
            <option value="SS26" <?php echo ($isEditMode && $prodotto['stagione'] == 'SS26') ? 'selected' : ''; ?>>SS26</option>
            <option value="FW26" <?php echo ($isEditMode && $prodotto['stagione'] == 'FW26') ? 'selected' : ''; ?>>FW26</option>
            <option value="Accessori" <?php echo ($isEditMode && $prodotto['stagione'] == 'Accessori') ? 'selected' : ''; ?>>Accessori</option>
            <?php 
            // Aggiungi stagioni personalizzate dal db
            $stmt = $conn->query("SELECT DISTINCT stagione FROM prodotti WHERE stagione NOT IN ('', 'SS25', 'FW25', 'SS26', 'FW26', 'Accessori') ORDER BY stagione");
            while ($row = $stmt->fetch()) {
                if (!empty($row['stagione'])) {
                    echo '<option value="' . htmlspecialchars($row['stagione']) . '" ' . 
                         ($isEditMode && $prodotto['stagione'] == $row['stagione'] ? 'selected' : '') . '>' . 
                         htmlspecialchars($row['stagione']) . '</option>';
                }
            }
            ?>
            <option value="altro">Altro...</option>
        </select>
        <input type="text" id="altra_stagione" name="altra_stagione" class="form-control" style="display: none;" placeholder="Inserisci nuova stagione">
        <button class="btn btn-outline-secondary" type="button" id="conferma_altra_stagione" style="display: none;">Conferma</button>
    </div>
</div>
                
               <div class="col-md-6">
    <label for="fornitore" class="form-label">Fornitore:</label>
    <div class="input-group">
        <select id="fornitore" name="fornitore" class="form-select">
            <option value="">Seleziona...</option>
            <?php
            // Carica fornitori dal database
            $stmtFornitori = $conn->query("SELECT DISTINCT fornitore FROM prodotti WHERE fornitore != '' ORDER BY fornitore");
            $fornitori = $stmtFornitori->fetchAll(PDO::FETCH_COLUMN);
            
            // Aggiungi anche dalla cache
            if ($conn->query("SHOW TABLES LIKE 'cache_fornitori'")->rowCount() > 0) {
                $stmtCache = $conn->query("SELECT nome, business_name FROM cache_fornitori ORDER BY nome");
                while ($fornitore = $stmtCache->fetch()) {
                    $nome = $fornitore['nome'] ?? $fornitore['business_name'] ?? '';
                    if (!empty($nome) && !in_array($nome, $fornitori)) {
                        $fornitori[] = $nome;
                    }
                }
            }
            
            // Rimuovi duplicati e ordina
            $fornitori = array_unique($fornitori);
            sort($fornitori);
            
            foreach ($fornitori as $fornitore) {
                echo '<option value="' . htmlspecialchars($fornitore) . '" ' . 
                     ($isEditMode && $prodotto['fornitore'] == $fornitore ? 'selected' : '') . '>' . 
                     htmlspecialchars($fornitore) . '</option>';
            }
            ?>
            <option value="altro">Altro...</option>
        </select>
        <input type="text" id="altro_fornitore" name="altro_fornitore" class="form-control" style="display: none;" placeholder="Inserisci nuovo fornitore">
        <button class="btn btn-outline-secondary" type="button" id="conferma_altro_fornitore" style="display: none;">Conferma</button>
    </div>
</div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="marca" class="form-label">Marca:</label>
                    <select id="marca" name="marca" class="form-select">
                        <?php foreach($marcheLista as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($isEditMode && $prodotto['marca'] == $key) ? 'selected' : ($key == 'Ciabalù' ? 'selected' : ''); ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="ean" class="form-label">Codice EAN:</label>
                    <div class="input-group">
                        <input type="text" id="ean" name="ean" class="form-control" value="<?php echo $isEditMode ? htmlspecialchars($prodotto['ean']) : ''; ?>">
                        <button type="button" class="btn btn-outline-secondary" id="genera_ean">Genera EAN</button>
                    </div>
                </div>
            </div>
            <!-- Anteprima prodotto semplice -->
            <div id="anteprima_semplice" class="mt-4 mb-4" style="display: none;">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Anteprima Prodotto</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>SKU:</strong> <span id="preview_sku"></span></p>
                                <p><strong>Titolo:</strong> <span id="preview_titolo"></span></p>
                                <p><strong>Tipologia:</strong> <span id="preview_tipologia"></span></p>
                                <p><strong>Prezzo:</strong> <span id="preview_prezzo"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Genere:</strong> <span id="preview_genere"></span></p>
                                <p><strong>Stagione:</strong> <span id="preview_stagione"></span></p>
                                <p><strong>Marca:</strong> <span id="preview_marca"></span></p>
                                <p><strong>EAN:</strong> <span id="preview_ean"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sezione Varianti: Inizialmente nascosta, mostrata in base al tipo di prodotto -->
            <div id="varianti_container" style="display: none;">
                <hr class="my-4">
                <h5 class="mb-3">Varianti del Prodotto</h5>
                
                <!-- Sezione Taglie -->
                <div id="taglie_container" style="display: none;" class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Taglie:</label>
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#nuovoPresetTaglieModal">
                            <i class="bi bi-plus-circle"></i> Nuovo Preset
                        </button>
                    </div>
                    <div class="mb-2">
                        <?php foreach($tagliePresets as $id => $preset): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary preset-taglie me-1 mb-1" data-preset="<?php echo $id; ?>">
                                <?php echo htmlspecialchars($preset['nome']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="taglie-selezionate mb-2" id="taglie_selezionate">
                        <!-- Le taglie selezionate verranno mostrate qui dinamicamente -->
                        <?php if ($isEditMode && !empty($varianti)): ?>
                            <?php 
                            $taglieDaVarianti = [];
                            foreach ($varianti as $variante) {
                                if (!empty($variante['taglia']) && !in_array($variante['taglia'], $taglieDaVarianti)) {
                                    $taglieDaVarianti[] = $variante['taglia'];
                                }
                            }
                            ?>
                            <?php foreach($taglieDaVarianti as $taglia): ?>
                                <span class="badge bg-primary me-1 mb-1">
                                    <?php echo htmlspecialchars($taglia); ?>
                                    <button class="btn-close btn-close-white ms-1" type="button" aria-label="Remove" style="font-size: 0.5rem;"></button>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="input-group">
                        <input type="text" id="nuova_taglia" class="form-control" placeholder="Aggiungi taglia...">
                        <button type="button" class="btn btn-outline-secondary" id="aggiungi_taglia">
                            <i class="bi bi-plus"></i> Aggiungi
                        </button>
                    </div>
                    <input type="hidden" name="taglie" id="taglie_json" value="<?php echo $isEditMode && !empty($taglieDaVarianti) ? htmlspecialchars(json_encode($taglieDaVarianti)) : '[]'; ?>">
                </div>
                
                <!-- Sezione Colori -->
                <div id="colori_container" style="display: none;" class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Colori:</label>
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#nuovoPresetColoriModal">
                            <i class="bi bi-plus-circle"></i> Nuovo Preset
                        </button>
                    </div>
                    <div class="mb-2">
                        <?php foreach($coloriPresets as $id => $preset): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary preset-colori me-1 mb-1" data-preset="<?php echo $id; ?>">
                                <?php echo htmlspecialchars($preset['nome']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="colori-selezionati mb-2" id="colori_selezionati">
                        <!-- I colori selezionati verranno mostrati qui dinamicamente -->
                        <?php if ($isEditMode && !empty($varianti)): ?>
                            <?php 
                            $coloriDaVarianti = [];
                            foreach ($varianti as $variante) {
                                if (!empty($variante['colore']) && !in_array($variante['colore'], $coloriDaVarianti)) {
                                    $coloriDaVarianti[] = $variante['colore'];
                                }
                            }
                            ?>
                            <?php foreach($coloriDaVarianti as $colore): ?>
                                <span class="badge bg-secondary me-1 mb-1">
                                    <?php echo htmlspecialchars($colore); ?>
                                    <button class="btn-close btn-close-white ms-1" type="button" aria-label="Remove" style="font-size: 0.5rem;"></button>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="input-group">
                        <input type="text" id="nuovo_colore" class="form-control" placeholder="Aggiungi colore...">
                        <button type="button" class="btn btn-outline-secondary" id="aggiungi_colore">
                            <i class="bi bi-plus"></i> Aggiungi
                        </button>
                    </div>
                    <input type="hidden" name="colori" id="colori_json" value="<?php echo $isEditMode && !empty($coloriDaVarianti) ? htmlspecialchars(json_encode($coloriDaVarianti)) : '[]'; ?>">
                </div>
                
                <!-- Anteprima delle varianti generate -->
                <div id="anteprima_varianti" class="mt-4" style="display:<?php echo ($isEditMode && !empty($varianti)) ? 'block' : 'none'; ?>;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Anteprima Varianti</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="assegna_ean_tutte">
                            <i class="bi bi-upc"></i> Assegna EAN a tutte
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Variante</th>
                                    <th>EAN</th>
                                    <th>Prezzo</th>
                                </tr>
                            </thead>
                            <tbody id="varianti_preview">
                                <!-- Le varianti generate verranno mostrate qui dinamicamente -->
                                <?php if ($isEditMode && !empty($varianti)): ?>
                                    <?php foreach($varianti as $index => $variante): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($variante['sku']); ?></td>
                                            <td><?php echo htmlspecialchars($variante['titolo']); ?></td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm ean-input" 
                                                           value="<?php echo htmlspecialchars($variante['ean']); ?>" 
                                                           data-index="<?php echo $index; ?>">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm genera-ean" 
                                                            data-index="<?php echo $index; ?>" title="Genera EAN">
                                                        <i class="bi bi-upc"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>€<?php echo number_format($variante['prezzo_vendita'], 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary" id="genera_varianti">
                        <i class="bi bi-gear"></i> Genera Varianti
                    </button>
                    <button type="button" class="btn btn-outline-warning" id="reset_varianti" style="display: <?php echo ($isEditMode && !empty($varianti)) ? 'inline-block' : 'none'; ?>;">
                        <i class="bi bi-arrow-repeat"></i> Reset Varianti
                    </button>
                </div>
            </div>
            
            <input type="hidden" name="varianti_json" id="varianti_json" value="<?php echo $isEditMode && !empty($varianti) ? htmlspecialchars(json_encode($varianti)) : '[]'; ?>">
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="salva_prodotto">
                    <i class="bi bi-save"></i> <?php echo $isEditMode ? 'Aggiorna Prodotto' : 'Salva Prodotto'; ?>
                </button>
                <button type="button" class="btn btn-success" id="invia_smarty">
                    <i class="bi bi-cloud-upload"></i> Invia a Smarty
                </button>
                <a href="gestione_prodotti.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-x-circle"></i> Annulla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nuovo Preset Taglie -->
<div class="modal fade" id="nuovoPresetTaglieModal" tabindex="-1" aria-labelledby="nuovoPresetTaglieModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nuovoPresetTaglieModalLabel">Nuovo Preset Taglie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="nuovoPresetTaglieForm">
                    <div class="mb-3">
                        <label for="preset_taglie_nome" class="form-label">Nome del preset:</label>
                        <input type="text" class="form-control" id="preset_taglie_nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Taglie selezionate:</label>
                        <div id="preset_taglie_anteprima" class="border p-2 rounded mb-2 min-height-100"></div>
                        <small class="form-text text-muted">Le taglie attualmente selezionate verranno salvate come preset</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="salva_preset_taglie">Salva preset</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuovo Preset Colori -->
<div class="modal fade" id="nuovoPresetColoriModal" tabindex="-1" aria-labelledby="nuovoPresetColoriModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nuovoPresetColoriModalLabel">Nuovo Preset Colori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="nuovoPresetColoriForm">
                    <div class="mb-3">
                        <label for="preset_colori_nome" class="form-label">Nome del preset:</label>
                        <input type="text" class="form-control" id="preset_colori_nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Colori selezionati:</label>
                        <div id="preset_colori_anteprima" class="border p-2 rounded mb-2 min-height-100"></div>
                        <small class="form-text text-muted">I colori attualmente selezionati verranno salvati come preset</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="salva_preset_colori">Salva preset</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Fornitori -->
<div class="modal fade" id="fornitoriModal" tabindex="-1" aria-labelledby="fornitoriModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fornitoriModalLabel">Seleziona Fornitore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="search_fornitori" placeholder="Cerca fornitore...">
                    <button class="btn btn-outline-secondary" type="button" id="do_search_fornitori"><button class="btn btn-outline-secondary" type="button" id="do_search_fornitori">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="list-group" id="risultati_fornitori">
                    <!-- Risultati ricerca -->
                </div>
            </div>
        </div>
    </div>
</div>
<!-- JavaScript per gestire la logica della pagina -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementi del form
    const tipoProdottoSelect = document.getElementById('tipo_prodotto');
    const tipologiaSelect = document.getElementById('tipologia');
    const nomeModelloInput = document.getElementById('nome_modello');
    const titoloInput = document.getElementById('titolo');
    const prezzoAcquistoInput = document.getElementById('prezzo_acquisto');
    const prezzoVenditaInput = document.getElementById('prezzo_vendita');
    const calcolaPrezzoBtn = document.getElementById('calcola_prezzo');
    const genereSelect = document.getElementById('genere');
    const stagioneSelect = document.getElementById('stagione');
    const marcaSelect = document.getElementById('marca');
    const eanInput = document.getElementById('ean');
    const variantiContainer = document.getElementById('varianti_container');
    const taglieContainer = document.getElementById('taglie_container');
    const coloriContainer = document.getElementById('colori_container');
    const generaEanBtn = document.getElementById('genera_ean');
    const generaVariantiBtn = document.getElementById('genera_varianti');
    const resetVariantiBtn = document.getElementById('reset_varianti');
    const anteprimaVarianti = document.getElementById('anteprima_varianti');
    const assegnaEanTutteBtn = document.getElementById('assegna_ean_tutte');
    const inviaSmartyBtn = document.getElementById('invia_smarty');
    const anteprimaSemplice = document.getElementById('anteprima_semplice');
    
    // Generazione automatica del titolo
function generaTitoloAutomatico() {
    const tipologia = tipologiaSelect.options[tipologiaSelect.selectedIndex].text;
    const nomeModello = nomeModelloInput.value.trim();
    
    if (tipologia !== 'Seleziona...' && nomeModello !== '') {
        // Crea il titolo nel formato "Tipologia Nome Modello"
        const titoloGenerato = tipologia + ' ' + nomeModello;
        titoloInput.value = titoloGenerato;
        
        // Aggiorna anche l'anteprima se è un prodotto semplice
        if (tipoProdottoSelect.value === 'semplice') {
            aggiornaAnteprimaSemplice();
        }
    }
}

// Aggiungi gli eventi per aggiornare il titolo quando cambiano i campi
nomeModelloInput.addEventListener('blur', generaTitoloAutomatico);
tipologiaSelect.addEventListener('change', generaTitoloAutomatico);
    
    // Preset di taglie e colori
    const tagliePreset = <?php echo json_encode($tagliePresets); ?>;
    const coloriPreset = <?php echo json_encode($coloriPresets); ?>;
    
    // Variabili per tenere traccia delle taglie e colori selezionati
    let taglieSelezionate = <?php echo $isEditMode && !empty($taglieDaVarianti) ? json_encode($taglieDaVarianti) : '[]'; ?>;
    let coloriSelezionati = <?php echo $isEditMode && !empty($coloriDaVarianti) ? json_encode($coloriDaVarianti) : '[]'; ?>;
    let variantiGenerate = <?php echo $isEditMode && !empty($varianti) ? json_encode($varianti) : '[]'; ?>;
    
    // Flag per verificare se è modalità modifica
    const isEditMode = <?php echo $isEditMode ? 'true' : 'false'; ?>;
    
    // All'avvio, imposta correttamente i container in base al tipo di prodotto
    if (tipoProdottoSelect.value) {
        mostraContainerTipoProdotto(tipoProdottoSelect.value);
    }
    
    // Aggiorna l'anteprima del prodotto semplice quando i campi vengono modificati
    function aggiornaAnteprimaSemplice() {
        const sku = nomeModelloInput.value.trim();
        const titolo = titoloInput.value.trim() || (marcaSelect.options[marcaSelect.selectedIndex].text + ' ' + sku);
        const tipologia = tipologiaSelect.options[tipologiaSelect.selectedIndex].text;
        const prezzo = prezzoVenditaInput.value ? '€' + parseFloat(prezzoVenditaInput.value).toFixed(2) : '';
        const genere = genereSelect.options[genereSelect.selectedIndex].text;
        const stagione = stagioneSelect.options[stagioneSelect.selectedIndex].text;
        const marca = marcaSelect.options[marcaSelect.selectedIndex].text;
        const ean = eanInput.value;
        
        document.getElementById('preview_sku').textContent = sku;
        document.getElementById('preview_titolo').textContent = titolo;
        document.getElementById('preview_tipologia').textContent = tipologia;
        document.getElementById('preview_prezzo').textContent = prezzo;
        document.getElementById('preview_genere').textContent = genere !== 'Seleziona...' ? genere : '';
        document.getElementById('preview_stagione').textContent = stagione !== 'Seleziona...' ? stagione : '';
        document.getElementById('preview_marca').textContent = marca;
        document.getElementById('preview_ean').textContent = ean;
    }
    
    // Quando cambiano i campi, aggiorna l'anteprima
    [nomeModelloInput, titoloInput, tipologiaSelect, prezzoVenditaInput, genereSelect, stagioneSelect, marcaSelect, eanInput].forEach(el => {
        el.addEventListener('change', function() {
            if (tipoProdottoSelect.value === 'semplice') {
                aggiornaAnteprimaSemplice();
            }
        });
    });
    
    // Mostra/nasconde sezioni in base al tipo di prodotto
    tipoProdottoSelect.addEventListener('change', function() {
        const tipoProdotto = this.value;
        mostraContainerTipoProdotto(tipoProdotto);
    });
    
    function mostraContainerTipoProdotto(tipoProdotto) {
        // Resetta lo stato
        variantiContainer.style.display = 'none';
        taglieContainer.style.display = 'none';
        coloriContainer.style.display = 'none';
        anteprimaVarianti.style.display = 'none';
        resetVariantiBtn.style.display = 'none';
        anteprimaSemplice.style.display = 'none';
        
        // Mostra sezioni appropriate in base al tipo di prodotto
        if (tipoProdotto === 'taglia' || tipoProdotto === 'taglia_colore') {
            variantiContainer.style.display = 'block';
            taglieContainer.style.display = 'block';
        }
        
        if (tipoProdotto === 'colore' || tipoProdotto === 'taglia_colore') {
            variantiContainer.style.display = 'block';
            coloriContainer.style.display = 'block';
        }
        
        if (tipoProdotto === 'semplice') {
            anteprimaSemplice.style.display = 'block';
            aggiornaAnteprimaSemplice();
        }
        
        // Se ci sono varianti già generate, mostra l'anteprima
        if (variantiGenerate.length > 0) {
            anteprimaVarianti.style.display = 'block';
            resetVariantiBtn.style.display = 'inline-block';
        }
    }
    
    // Calcola prezzo di vendita in base alla tipologia e prezzo di acquisto
    calcolaPrezzoBtn.addEventListener('click', function() {
        const prezzoAcquisto = parseFloat(prezzoAcquistoInput.value);
        const tipologia = tipologiaSelect.value;
        
        if (isNaN(prezzoAcquisto) || prezzoAcquisto <= 0 || !tipologia) {
            alert('Inserisci un prezzo di acquisto valido e seleziona una tipologia');
            return;
        }
        
        // Chiamata AJAX per calcolare il prezzo
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
                if (tipoProdottoSelect.value === 'semplice') {
                    aggiornaAnteprimaSemplice();
                }
            } else {
                alert('Errore nel calcolo del prezzo: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Si è verificato un errore nel calcolo del prezzo');
        });
    });
    
    // Generazione EAN
    generaEanBtn.addEventListener('click', function() {
        // Usa getNextEAN
        fetch('getNextEAN.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ean').value = data.ean;
                if (tipoProdottoSelect.value === 'semplice') {
                    aggiornaAnteprimaSemplice();
                }
            } else {
                // Fallback: genera un EAN demo
                generateDemoEAN();
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            // Fallback: genera un EAN demo
            generateDemoEAN();
        });
    });
    
    // Funzione per generare un EAN demo
    function generateDemoEAN() {
        // Prefisso standard per l'Italia (80)
        const prefix = '80';
        
        // Genera 10 cifre casuali
        let numbers = '';
        for (let i = 0; i < 10; i++) {
            numbers += Math.floor(Math.random() * 10);
        }
        
        // Codice parziale (senza check digit)
        const partialCode = prefix + numbers;
        
        // Calcola il check digit
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(partialCode[i]) * (i % 2 === 0 ? 1 : 3);
        }
        const checkDigit = (10 - (sum % 10)) % 10;
        
        // Concatena il check digit al codice
        const ean = partialCode + checkDigit;
        document.getElementById('ean').value = ean;
        
        if (tipoProdottoSelect.value === 'semplice') {
            aggiornaAnteprimaSemplice();
        }
    }
    
    // Gestione preset taglie
    document.querySelectorAll('.preset-taglie').forEach(button => {
        button.addEventListener('click', function() {
            const presetId = this.dataset.preset;
            if (tagliePreset[presetId] && tagliePreset[presetId].taglie) {
                taglieSelezionate = [...tagliePreset[presetId].taglie];
                aggiornaTaglieUI();
            }
        });
    });
    
    // Aggiunta di una nuova taglia
    document.getElementById('aggiungi_taglia').addEventListener('click', function() {
        const nuovaTaglia = document.getElementById('nuova_taglia').value.trim();
        if (nuovaTaglia && !taglieSelezionate.includes(nuovaTaglia)) {
            taglieSelezionate.push(nuovaTaglia);
            aggiornaTaglieUI();
            document.getElementById('nuova_taglia').value = '';
        }
    });
    
    // Gestione preset colori
    document.querySelectorAll('.preset-colori').forEach(button => {
        button.addEventListener('click', function() {
            const presetId = this.dataset.preset;
            if (coloriPreset[presetId] && coloriPreset[presetId].colori) {
                coloriSelezionati = [...coloriPreset[presetId].colori];
                aggiornaColoriUI();
            }
        });
    });
    
    // Aggiunta di un nuovo colore
    document.getElementById('aggiungi_colore').addEventListener('click', function() {
        const nuovoColore = document.getElementById('nuovo_colore').value.trim();
        if (nuovoColore && !coloriSelezionati.includes(nuovoColore)) {
            coloriSelezionati.push(nuovoColore);
            aggiornaColoriUI();
            document.getElementById('nuovo_colore').value = '';
        }
    });
    
 // Generazione varianti
generaVariantiBtn.addEventListener('click', function() {
    const tipoProdotto = tipoProdottoSelect.value;
    const nomeModello = document.getElementById('nome_modello').value.trim();
    const prezzoAcquisto = parseFloat(prezzoAcquistoInput.value);
    const prezzoVendita = parseFloat(prezzoVenditaInput.value);
    
    if (!nomeModello) {
        alert('Inserisci il nome del modello');
        return;
    }
    
    // IMPORTANTE: Crea lo sku parent dal nome modello
    const parentSku = nomeModello.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
    
    variantiGenerate = [];
    
    if (tipoProdotto === 'taglia') {
        if (taglieSelezionate.length === 0) {
            alert('Seleziona almeno una taglia');
            return;
        }
        
        // Genera varianti per ogni taglia
        taglieSelezionate.forEach(taglia => {
            const sku = generaSKU(nomeModello, taglia, '');
            variantiGenerate.push({
                sku: sku,
                parent_sku: parentSku, // Aggiungi il campo parent_sku
                titolo: marcaSelect.value + ' ' + nomeModello + ' - ' + taglia,
                taglia: taglia,
                colore: '',
                ean: '',
                prezzo_acquisto: prezzoAcquisto,
                prezzo_vendita: prezzoVendita
            });
        });
    } else if (tipoProdotto === 'colore') {
        if (coloriSelezionati.length === 0) {
            alert('Seleziona almeno un colore');
            return;
        }
        
        // Genera varianti per ogni colore
        coloriSelezionati.forEach(colore => {
            const sku = generaSKU(nomeModello, '', colore);
            variantiGenerate.push({
                sku: sku,
                parent_sku: parentSku, // Aggiungi il campo parent_sku
                titolo: marcaSelect.value + ' ' + nomeModello + ' - ' + colore,
                taglia: '',
                colore: colore,
                ean: '',
                prezzo_acquisto: prezzoAcquisto,
                prezzo_vendita: prezzoVendita
            });
        });
    } else if (tipoProdotto === 'taglia_colore') {
        if (taglieSelezionate.length === 0 || coloriSelezionati.length === 0) {
            alert('Seleziona almeno una taglia e un colore');
            return;
        }
        
        // Ordina i colori alfabeticamente
        const coloriOrdinati = [...coloriSelezionati].sort();
        
        // Ordina le taglie correttamente
        const taglieOrdinate = [...taglieSelezionate].sort(function(a, b) {
            const taglieMappate = {
                'XXS': 1, 'XS': 2, 'S': 3, 'M': 4, 'L': 5, 'XL': 6, 'XXL': 7, 'XXXL': 8
            };
            
            // Se entrambe le taglie sono numeriche, confrontale come numeri
            if (!isNaN(a) && !isNaN(b)) {
                return parseInt(a) - parseInt(b);
            }
            
            // Se entrambe le taglie sono nella mappatura, usa l'ordine predefinito
            if (taglieMappate[a] && taglieMappate[b]) {
                return taglieMappate[a] - taglieMappate[b];
            }
            
            // Altrimenti, confronta alfabeticamente
            return a.localeCompare(b);
        });
        
        // Prima raggruppa per colore, poi per taglia
        coloriOrdinati.forEach(colore => {
            taglieOrdinate.forEach(taglia => {
                const sku = generaSKU(nomeModello, taglia, colore);
                variantiGenerate.push({
                    sku: sku,
                    parent_sku: parentSku, // Aggiungi il campo parent_sku
                    titolo: marcaSelect.value + ' ' + nomeModello + ' - ' + colore + ' - ' + taglia,
                    taglia: taglia,
                    colore: colore,
                    ean: '',
                    prezzo_acquisto: prezzoAcquisto,
                    prezzo_vendita: prezzoVendita
                });
            });
        });
    }
    
    // Assegna automaticamente gli EAN alle varianti
if (variantiGenerate.length > 0) {
    // Mostra indicatore di caricamento
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'text-center mb-3';
    loadingIndicator.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Assegnazione EAN...</span></div><p>Assegnazione automatica EAN in corso...</p>';
    document.getElementById('varianti_container').appendChild(loadingIndicator);
    
    // Assegna EAN a tutte le varianti che non ne hanno già uno
    Promise.all(variantiGenerate.map((variante, index) => {
        return new Promise((resolve) => {
            if (!variante.ean || variante.ean.trim() === '') {
                fetch('getNextEAN.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            variantiGenerate[index].ean = data.ean;
                        } else {
                            // Fallback: genera un EAN demo
                            const ean = generateDemoEANForVariant(index);
                            variantiGenerate[index].ean = ean;
                        }
                        resolve();
                    })
                    .catch(() => {
                        // Fallback: genera un EAN demo
                        const ean = generateDemoEANForVariant(index);
                        variantiGenerate[index].ean = ean;
                        resolve();
                    });
            } else {
                resolve();
            }
        });
    })).then(() => {
        // Rimuovi indicatore di caricamento
        if (document.getElementById('varianti_container').contains(loadingIndicator)) {
            document.getElementById('varianti_container').removeChild(loadingIndicator);
        }
        
        // Mostra l'anteprima delle varianti
        mostraAnteprimaVarianti();
        document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
        resetVariantiBtn.style.display = 'inline-block';
    });
} else {
    resetVariantiBtn.style.display = 'none';
}
    // Aggiorna l'anteprima delle varianti
    if (variantiGenerate.length > 0) {
        mostraAnteprimaVarianti();
        document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
        resetVariantiBtn.style.display = 'inline-block';
    }
});
    
    // Reset varianti
    resetVariantiBtn.addEventListener('click', function() {
        variantiGenerate = [];
        document.getElementById('varianti_json').value = '[]';
        anteprimaVarianti.style.display = 'none';
        resetVariantiBtn.style.display = 'none';
    });
    
    // Funzione per assegnare EAN a tutte le varianti
    assegnaEanTutteBtn.addEventListener('click', function() {
        // Conferma prima di procedere
        if (!confirm('Verranno assegnati EAN a tutte le varianti. Procedere?')) {
            return;
        }
        
        // Per ogni variante, genera un EAN
        const varianti = [...document.querySelectorAll('.ean-input')];
        
        Promise.all(varianti.map((input, index) => {
            return new Promise((resolve) => {
                if (!input.value.trim()) {
                    fetch('getNextEAN.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                resolve({index, ean: data.ean});
                            } else {
                                resolve({index, ean: generateDemoEAN()});
                            }
                        })
                        .catch(() => {
                            resolve({index, ean: generateDemoEAN()});
                        });
                } else {
                    resolve({index, ean: input.value});
                }
            });
        })).then(results => {
            results.forEach(result => {
                const input = varianti[result.index];
                input.value = result.ean;
                variantiGenerate[result.index].ean = result.ean;
            });
            
            document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
            alert('EAN assegnati con successo a tutte le varianti!');
        });
    });
    
    // Funzione per aggiornare la UI delle taglie
    function aggiornaTaglieUI() {
        const container = document.getElementById('taglie_selezionate');
        container.innerHTML = '';
        
        taglieSelezionate.forEach(taglia => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary me-1 mb-1';
            badge.textContent = taglia;
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn-close btn-close-white ms-1';
            removeBtn.setAttribute('type', 'button');
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.style.fontSize = '0.5rem';
            removeBtn.addEventListener('click', function() {
                taglieSelezionate = taglieSelezionate.filter(t => t !== taglia);
                aggiornaTaglieUI();
            });
            
            badge.appendChild(removeBtn);
            container.appendChild(badge);
        });
        
        document.getElementById('taglie_json').value = JSON.stringify(taglieSelezionate);
        
        // Aggiorna anche l'anteprima nel modal
        const presetAnteprima = document.getElementById('preset_taglie_anteprima');
        presetAnteprima.innerHTML = '';
        
        taglieSelezionate.forEach(taglia => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary me-1 mb-1';
            badge.textContent = taglia;
            presetAnteprima.appendChild(badge);
        });
    }
    
    // Funzione per aggiornare la UI dei colori
    function aggiornaColoriUI() {
        const container = document.getElementById('colori_selezionati');
        container.innerHTML = '';
        
        coloriSelezionati.forEach(colore => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary me-1 mb-1';
            badge.textContent = colore;
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn-close btn-close-white ms-1';
            removeBtn.setAttribute('type', 'button');
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.style.fontSize = '0.5rem';
            removeBtn.addEventListener('click', function() {
                coloriSelezionati = coloriSelezionati.filter(c => c !== colore);
                aggiornaColoriUI();
            });
            
            badge.appendChild(removeBtn);
            container.appendChild(badge);
        });
        
        document.getElementById('colori_json').value = JSON.stringify(coloriSelezionati);
        
        // Aggiorna anche l'anteprima nel modal
        const presetAnteprima = document.getElementById('preset_colori_anteprima');
        presetAnteprima.innerHTML = '';
        
        coloriSelezionati.forEach(colore => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary me-1 mb-1';
            badge.textContent = colore;
            presetAnteprima.appendChild(badge);
        });
    }
    
    // Funzione per mostrare l'anteprima delle varianti
    function mostraAnteprimaVarianti() {
        const container = document.getElementById('varianti_preview');
        container.innerHTML = '';
        
        variantiGenerate.forEach((variante, index) => {
            const row = document.createElement('tr');
            
            const skuCell = document.createElement('td');
            skuCell.textContent = variante.sku;
            row.appendChild(skuCell);
            
            const nomeCell = document.createElement('td');
            nomeCell.textContent = variante.titolo;
            row.appendChild(nomeCell);
            
            const eanCell = document.createElement('td');
            const eanBtnGroup = document.createElement('div');
            eanBtnGroup.className = 'input-group input-group-sm';
            
            const eanInput = document.createElement('input');
            eanInput.type = 'text';
            eanInput.className = 'form-control form-control-sm ean-input';
            eanInput.value = variante.ean;
            eanInput.dataset.index = index;
            eanInput.addEventListener('change', function() {
                variantiGenerate[index].ean = this.value;
                document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
            });
            
            eanBtnGroup.appendChild(eanInput);
            
            const eanBtn = document.createElement('button');
            eanBtn.type = 'button';
            eanBtn.className = 'btn btn-outline-secondary btn-sm genera-ean';
            eanBtn.innerHTML = '<i class="bi bi-upc"></i>';
            eanBtn.title = 'Genera EAN';
            eanBtn.dataset.index = index;
            eanBtn.addEventListener('click', function() {
                generaEANPerVariante(index);
            });
            
            eanBtnGroup.appendChild(eanBtn);
            eanCell.appendChild(eanBtnGroup);
            row.appendChild(eanCell);
            
            const prezzoCell = document.createElement('td');
            prezzoCell.textContent = `€${variante.prezzo_vendita.toFixed(2)}`;
            row.appendChild(prezzoCell);
            
            container.appendChild(row);
        });
        
        anteprimaVarianti.style.display = 'block';
        
        // Aggiungi eventi ai pulsanti di generazione EAN
        document.querySelectorAll('.genera-ean').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                generaEANPerVariante(index);
            });
        });
    }
    
    // Funzione per generare un EAN per una variante specifica
    function generaEANPerVariante(index) {
        fetch('getNextEAN.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                variantiGenerate[index].ean = data.ean;
                document.querySelectorAll('.ean-input')[index].value = data.ean;
                document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
            } else {
                // Fallback a EAN demo
                generateDemoEANForVariant(index);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            // Fallback a EAN demo
            generateDemoEANForVariant(index);
        });
    }
    
    // Funzione per generare un EAN demo per una variante
    function generateDemoEANForVariant(index) {
        // Prefisso standard per l'Italia (80)
        const prefix = '80';
        
        // Genera 10 cifre casuali
        let numbers = '';
        for (let i = 0; i < 10; i++) {
            numbers += Math.floor(Math.random() * 10);
        }
        
        // Codice parziale (senza check digit)
        const partialCode = prefix + numbers;
        
        // Calcola il check digit
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(partialCode[i]) * (i % 2 === 0 ? 1 : 3);
        }
        const checkDigit = (10 - (sum % 10)) % 10;
        
        // Concatena il check digit al codice
        const ean = partialCode + checkDigit;
        
        // Aggiorna la variante
        variantiGenerate[index].ean = ean;
        document.querySelectorAll('.ean-input')[index].value = ean;
        document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
    }
    
    // Funzione per generare uno SKU
    function generaSKU(nomeModello, taglia, colore) {
        // Converti in minuscolo e sostituisci gli spazi con trattini
        let base = nomeModello.toLowerCase().replace(/\s+/g, '-');
        
        // Rimuovi caratteri speciali
        base = base.replace(/[^a-z0-9\-]/g, '');
        
        if (taglia && colore) {
            return `${base}-${taglia.toLowerCase()}-${colore.toLowerCase().replace(/\s+/g, '-')}`;
        } else if (taglia) {
            return `${base}-${taglia.toLowerCase()}`;
        } else if (colore) {
            return `${base}-${colore.toLowerCase().replace(/\s+/g, '-')}`;
        }
        
        return base;
    }
    
    // Invio a Smarty
inviaSmartyBtn.addEventListener('click', function() {
    // Per prodotti semplici, verifica che i campi siano compilati
    if (tipoProdottoSelect.value === 'semplice') {
        const nomeModello = document.getElementById('nome_modello').value.trim();
        const prezzoAcquisto = parseFloat(prezzoAcquistoInput.value);
        const prezzoVendita = parseFloat(prezzoVenditaInput.value);
        
        if (!nomeModello || isNaN(prezzoAcquisto) || prezzoAcquisto <= 0 || isNaN(prezzoVendita) || prezzoVendita <= 0) {
            alert('Compila tutti i campi obbligatori prima di inviare a Smarty');
            return;
        }
        
        // Genera lo SKU del prodotto parent (il nome modello formattato)
        const parentSku = nomeModello.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
        
        // Usa il titolo inserito, o genera uno se non disponibile
        const titoloProdotto = titoloInput.value.trim() || (marcaSelect.options[marcaSelect.selectedIndex].text + ' ' + nomeModello);
        
        // Crea una "falsa" variante per uniformare il formato
        variantiGenerate = [{
            sku: parentSku, // Usa il parent SKU anche per lo SKU del prodotto
            parent_sku: null,
            titolo: titoloProdotto, // Usa il titolo specificato
            taglia: '',
            colore: '',
            ean: eanInput.value,
            prezzo_acquisto: prezzoAcquisto,
            prezzo_vendita: prezzoVendita
        }];
    } else if (variantiGenerate.length === 0) {
        alert('Genera prima le varianti del prodotto');
        return;
    }
    
    // Conferma prima di procedere
    if (!confirm('Sei sicuro di voler inviare il prodotto a Smarty?')) {
        return;
    }
    
    // Assicurati che tutte le varianti abbiano il parent_sku corretto
    const nomeModello = document.getElementById('nome_modello').value.trim();
    const parentSku = nomeModello.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
    
    // Se è un prodotto con varianti, aggiungi il prodotto parent
    if (tipoProdottoSelect.value !== 'semplice') {
        const titoloProdotto = titoloInput.value.trim() || (marcaSelect.options[marcaSelect.selectedIndex].text + ' ' + nomeModello);
        
        // Aggiungi il prodotto parent come prima variante
        variantiGenerate = [
            {
                sku: parentSku, // Parent SKU come SKU
                parent_sku: null,
                titolo: titoloProdotto, // Titolo specificato
                taglia: '',
                colore: '',
                ean: eanInput.value,
                prezzo_acquisto: parseFloat(prezzoAcquistoInput.value),
                prezzo_vendita: parseFloat(prezzoVenditaInput.value),
            },
            ...variantiGenerate.map(v => ({...v, parent_sku: parentSku})) // Assicura che tutte le varianti abbiano il parent_sku corretto
        ];
    }
    
    // Raccolta dati del prodotto principale
    const datiProdotto = {
        tipo_prodotto: tipoProdottoSelect.value,
        tipologia: tipologiaSelect.value,
        nome_modello: nomeModello,
        titolo: titoloInput.value,
        prezzo_acquisto: parseFloat(prezzoAcquistoInput.value),
        prezzo_vendita: parseFloat(prezzoVenditaInput.value),
        aliquota_iva: document.getElementById('aliquota_iva').value,
        genere: document.getElementById('genere').value,
        stagione: document.getElementById('stagione').value,
        fornitore: document.getElementById('fornitore').value,
        marca: document.getElementById('marca').value,
        ean: document.getElementById('ean').value,
        variants: variantiGenerate,
        is_simple: tipoProdottoSelect.value === 'semplice'
    };
    
    // Disabilita il pulsante durante l'invio
    inviaSmartyBtn.disabled = true;
    inviaSmartyBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Invio in corso...';
    
    // Invio dati a Smarty via API
    fetch('api_send_to_smarty.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datiProdotto)
    })
    .then(response => response.json())
    .then(data => {
        // Riabilita il pulsante
        inviaSmartyBtn.disabled = false;
        inviaSmartyBtn.innerHTML = '<i class="bi bi-cloud-upload"></i> Invia a Smarty';
        
        if (data.success) {
            alert('Prodotto inviato con successo a Smarty!');
            // Reindirizza alla pagina di gestione prodotti
            window.location.href = 'gestione_prodotti.php';
        } else {
            alert('Errore nell\'invio a Smarty: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Si è verificato un errore nell\'invio a Smarty');
        
        // Riabilita il pulsante
        inviaSmartyBtn.disabled = false;
        inviaSmartyBtn.innerHTML = '<i class="bi bi-cloud-upload"></i> Invia a Smarty';
    });
});
    // Cerca fornitore
   document.getElementById('cerca_fornitore').addEventListener('click', function() {
    const searchTerm = document.getElementById('fornitore').value.trim();
    if (!searchTerm) {
        alert('Inserisci un termine di ricerca');
        return;
    }
    
    // Mostra il modal
    const fornitoriModal = new bootstrap.Modal(document.getElementById('fornitoriModal'));
    fornitoriModal.show();
    
    const risultatiContainer = document.getElementById('risultati_fornitori');
    risultatiContainer.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Caricamento...</span></div></div>';
    
    // Utilizziamo i dati in cache
    fetch('get_suppliers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `search=${encodeURIComponent(searchTerm)}`
    })
    .then(response => response.json())
    .then(data => {
        risultatiContainer.innerHTML = '';
        
        console.log('Risposta fornitore:', data); // Debug
        
        if (data.success && data.fornitori && data.fornitori.length > 0) {
            data.fornitori.forEach(fornitore => {
                const fornitoreName = fornitore.name || fornitore.nome || 'Nome non disponibile';
                
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `<strong>${fornitoreName}</strong>`;
                if (fornitore.email) {
                    item.innerHTML += `<br><small>${fornitore.email}</small>`;
                }
                
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('fornitore').value = fornitoreName;
                    bootstrap.Modal.getInstance(document.getElementById('fornitoriModal')).hide();
                });
                
                risultatiContainer.appendChild(item);
            });
        } else {
            risultatiContainer.innerHTML = '<div class="alert alert-info">Nessun fornitore trovato</div>';
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        risultatiContainer.innerHTML = '<div class="alert alert-danger">Si è verificato un errore nella ricerca</div>';
    });
});
    // Gestione ricerca fornitori nel modal
    document.getElementById('do_search_fornitori').addEventListener('click', function() {
        const searchTerm = document.getElementById('search_fornitori').value.trim();
        if (searchTerm) {
            cercaFornitori(searchTerm);
        }
    });
    
    document.getElementById('search_fornitori').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchTerm = this.value.trim();
            if (searchTerm) {
                cercaFornitori(searchTerm);
            }
        }
    });
    
    // Salvataggio preset taglie
    document.getElementById('salva_preset_taglie').addEventListener('click', function() {
        const nomePreset = document.getElementById('preset_taglie_nome').value.trim();
        
        if (!nomePreset) {
            alert('Inserisci un nome per il preset');
            return;
        }
        
        if (taglieSelezionate.length === 0) {
            alert('Seleziona almeno una taglia');
            return;
        }
        
        // Invia richiesta per salvare il preset
        fetch('salva_preset_taglie.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nome: nomePreset,
                taglie: taglieSelezionate
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Preset salvato con successo!');
                // Chiudi il modal
                bootstrap.Modal.getInstance(document.getElementById('nuovoPresetTaglieModal')).hide();
                // Ricarica la pagina per aggiornare i preset
                window.location.reload();
            } else {
                alert('Errore nel salvataggio del preset: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Si è verificato un errore nel salvataggio del preset');
        });
    });
    
    // Salvataggio preset colori
    document.getElementById('salva_preset_colori').addEventListener('click', function() {
        const nomePreset = document.getElementById('preset_colori_nome').value.trim();
        
        if (!nomePreset) {
            alert('Inserisci un nome per il preset');
            return;
        }
        
        if (coloriSelezionati.length === 0) {
            alert('Seleziona almeno un colore');
            return;
        }
        
        // Invia richiesta per salvare il preset
        fetch('salva_preset_colori.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nome: nomePreset,
                colori: coloriSelezionati
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Preset salvato con successo!');
                // Chiudi il modal
                bootstrap.Modal.getInstance(document.getElementById('nuovoPresetColoriModal')).hide();
                // Ricarica la pagina per aggiornare i preset
                window.location.reload();
            } else {
                alert('Errore nel salvataggio del preset: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Si è verificato un errore nel salvataggio del preset');
        });
    });
    
    // Verifica se lo SKU esiste già quando cambia il nome modello
    nomeModelloInput.addEventListener('blur', function() {
        const sku = generaSKU(this.value.trim());
        
        if (!sku) return;
        
        // Verifica se lo SKU esiste già
        fetch('verifica_sku.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `sku=${encodeURIComponent(sku)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                alert('Attenzione: esiste già un prodotto con questo SKU. Modifica il nome modello per evitare conflitti.');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
        });
    });
    
    // Se è in modalità modifica, mostra i container appropriati
    if (isEditMode) {
        // Imposta il tipo di prodotto corretto
        const tipoProdotto = document.getElementById('tipo_prodotto').value;
        mostraContainerTipoProdotto(tipoProdotto);
    }
});
// Calcolo automatico del prezzo di vendita
document.getElementById('prezzo_acquisto').addEventListener('input', function() {
    const tipologia = document.getElementById('tipologia').value;
    
    if (tipologia && !isNaN(parseFloat(this.value))) {
        // Invece di cliccare sul pulsante, chiama direttamente la funzione di calcolo
        fetch('api_calcola_prezzo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `prezzo_acquisto=${this.value}&tipologia=${tipologia}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('prezzo_vendita').value = data.prezzo_vendita;
                if (tipoProdottoSelect.value === 'semplice') {
                    aggiornaAnteprimaSemplice();
                }
            }
        })
        .catch(error => console.error('Errore:', error));
    }
});

// Calcola anche quando cambia la tipologia
document.getElementById('tipologia').addEventListener('change', function() {
    const prezzoAcquisto = document.getElementById('prezzo_acquisto').value;
    
    if (this.value && !isNaN(parseFloat(prezzoAcquisto))) {
        document.getElementById('calcola_prezzo').click(); // Simula il click sul pulsante calcola
    }
});
// Gestione dell'opzione "Altro..." per la stagione
document.getElementById('stagione').addEventListener('change', function() {
    const altraStagioneInput = document.getElementById('altra_stagione');
    const confermaBtn = document.getElementById('conferma_altra_stagione');
    
    if (this.value === 'altro') {
        this.style.display = 'none';
        altraStagioneInput.style.display = 'block';
        confermaBtn.style.display = 'block';
        altraStagioneInput.focus();
    }
});

document.getElementById('conferma_altra_stagione').addEventListener('click', function() {
    const altraStagioneInput = document.getElementById('altra_stagione');
    const stagioneSelect = document.getElementById('stagione');
    const nuovaStagione = altraStagioneInput.value.trim();
    
    if (nuovaStagione) {
        // Aggiungi la nuova opzione
        const option = document.createElement('option');
        option.value = nuovaStagione;
        option.textContent = nuovaStagione;
        option.selected = true;
        
        // Inserisci prima dell'opzione "Altro..."
        const altroOption = stagioneSelect.querySelector('option[value="altro"]');
        stagioneSelect.insertBefore(option, altroOption);
        
        // Ripristina l'interfaccia
        stagioneSelect.style.display = 'block';
        altraStagioneInput.style.display = 'none';
        this.style.display = 'none';
        altraStagioneInput.value = '';
    }
});
// Gestione dell'opzione "Altro..." per il fornitore
document.getElementById('fornitore').addEventListener('change', function() {
    const altroFornitoreInput = document.getElementById('altro_fornitore');
    const confermaBtn = document.getElementById('conferma_altro_fornitore');
    
    if (this.value === 'altro') {
        this.style.display = 'none';
        altroFornitoreInput.style.display = 'block';
        confermaBtn.style.display = 'block';
        altroFornitoreInput.focus();
    }
});

document.getElementById('conferma_altro_fornitore').addEventListener('click', function() {
    const altroFornitoreInput = document.getElementById('altro_fornitore');
    const fornitoreSelect = document.getElementById('fornitore');
    const nuovoFornitore = altroFornitoreInput.value.trim();
    
    if (nuovoFornitore) {
        // Aggiungi la nuova opzione
        const option = document.createElement('option');
        option.value = nuovoFornitore;
        option.textContent = nuovoFornitore;
        option.selected = true;
        
        // Inserisci prima dell'opzione "Altro..."
        const altroOption = fornitoreSelect.querySelector('option[value="altro"]');
        fornitoreSelect.insertBefore(option, altroOption);
        
        // Ripristina l'interfaccia
        fornitoreSelect.style.display = 'block';
        altroFornitoreInput.style.display = 'none';
        this.style.display = 'none';
        altroFornitoreInput.value = '';
    }
});
</script>

<style>
.min-height-100 {
    min-height: 100px;
}
</style>

<?php
// Include il footer
include 'footer.php';
?>