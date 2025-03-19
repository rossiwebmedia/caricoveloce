/**
 * File: prodotti.js
 * Contiene le funzioni JavaScript per la gestione dei prodotti
 */

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
    fetch(`vista_prodotto.php?id=${productId}&format=json`)
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
                                    <td>${data.prodotto.sku}</td>
                                </tr>`;
                                
                if (data.prodotto.parent_sku) {
                    html += `
                                <tr>
                                    <th>Prodotto principale:</th>
                                    <td><a href="vista_prodotto.php?id=${data.prodotto.parent_id}">${data.prodotto.parent_sku}</a></td>
                                </tr>`;
                }
                
                html += `
                                <tr>
                                    <th>Tipologia:</th>
                                    <td>${data.prodotto.tipologia_nome}</td>
                                </tr>
                                <tr>
                                    <th>Marca:</th>
                                    <td>${data.prodotto.marca}</td>
                                </tr>
                                <tr>
                                    <th>Fornitore:</th>
                                    <td>${data.prodotto.fornitore || '-'}</td>
                                </tr>`;
                                
                if (data.prodotto.taglia) {
                    html += `
                                <tr>
                                    <th>Taglia:</th>
                                    <td>${data.prodotto.taglia}</td>
                                </tr>`;
                }
                
                if (data.prodotto.colore) {
                    html += `
                                <tr>
                                    <th>Colore:</th>
                                    <td>${data.prodotto.colore}</td>
                                </tr>`;
                }
                
                html += `
                                <tr>
                                    <th>EAN:</th>
                                    <td>${data.prodotto.ean || '-'}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Prezzi e Stato</h6>
                            <table class="table">
                                <tr>
                                    <th class="w-25">Prezzo Acquisto:</th>
                                    <td>€${parseFloat(data.prodotto.prezzo_acquisto).toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <th>Prezzo Vendita:</th>
                                    <td>€${parseFloat(data.prodotto.prezzo_vendita).toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <th>Aliquota IVA:</th>
                                    <td>${data.prodotto.aliquota_iva}%</td>
                                </tr>
                                <tr>
                                    <th>Stato:</th>
                                    <td>${data.prodotto.stato_html}</td>
                                </tr>`;
                                
                if (data.prodotto.stato === 'errore' && data.prodotto.messaggio_errore) {
                    html += `
                                <tr>
                                    <th>Errore:</th>
                                    <td class="text-danger">${data.prodotto.messaggio_errore}</td>
                                </tr>`;
                }
                
                html += `
                                <tr>
                                    <th>Data creazione:</th>
                                    <td>${data.prodotto.creato_il_formatted}</td>
                                </tr>
                                <tr>
                                    <th>ID Smarty:</th>
                                    <td>${data.prodotto.smarty_id || '-'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>`;
                
                // Se ci sono varianti, mostrale
                if (data.varianti && data.varianti.length > 0) {
                    html += `
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6 class="border-bottom pb-2">Varianti (${data.varianti.length})</h6>
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
                                                <td>${variante.sku}</td>
                                                <td>${variante.titolo}</td>
                                                <td>${variante.taglia || '-'}</td>
                                                <td>${variante.colore || '-'}</td>
                                                <td>${variante.ean || '-'}</td>
                                                <td>€${parseFloat(variante.prezzo_vendita).toFixed(2)}</td>
                                                <td>${variante.stato_html}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="vista_prodotto.php?id=${variante.id}" class="btn btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-success" onclick="syncProduct(${variante.id})">
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
                                                <td>${log.creato_il_formatted}</td>
                                                <td>${log.azione_html}</td>
                                                <td>${log.riuscito ? '<span class="badge bg-success">Successo</span>' : '<span class="badge bg-danger">Fallito</span>'}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-info view-log-btn" onclick="viewLogDetails('${log.id}')">
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
                            <a href="inserimento_prodotti.php?edit=${data.prodotto.id}" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Modifica
                            </a>
                            <button type="button" class="btn btn-success" onclick="syncProduct(${data.prodotto.id})">
                                <i class="bi bi-cloud-upload"></i> Sincronizza
                            </button>
                        </div>
                    </div>`;
                
                document.getElementById("productModalBody").innerHTML = html;
            } else {
                document.getElementById("productModalBody").innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        ${data.message || 'Errore nel caricamento dei dettagli del prodotto'}
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
    fetch(`get_log_details.php?id=${logId}`)
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
        body: `id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        // Rimuovi l'overlay di caricamento
        document.body.removeChild(loadingOverlay);
        
        if (data.success) {
            const message = data.varianti ? 
                `Prodotto sincronizzato con successo! ${data.varianti.filter(v => v.success).length} varianti sincronizzate.` :
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
            alert(`Sincronizzazione completata con successo! ${data.success_count} prodotti sincronizzati, ${data.error_count} errori.`);
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
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="export_format" id="export_excel" value="excel">
            <label class="form-check-label" for="export_excel">
                Esporta in Excel
            </label>
        </div>
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
                    ${opzioni}
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
        let exportUrl = `esporta_prodotti.php?format=${format}&include_variants=${includeVariants}`;
        for (const [key, value] of Object.entries(filters)) {
            if (value) {
                exportUrl += `&${key}=${encodeURIComponent(value)}`;
            }
        }
        
        // Chiudi il modal
        exportModal.hide();
        
        // Avvia il download
        window.location.href = exportUrl;
    });
}