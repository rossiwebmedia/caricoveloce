/**
 * Funzione migliorata per generare varianti con ordinamento per colore e poi taglia
 */
function generaVariantiOrdinate() {
    const tipoProdotto = document.getElementById('tipo_prodotto').value;
    const nomeModello = document.getElementById('nome_modello').value.trim();
    const prezzoAcquisto = parseFloat(document.getElementById('prezzo_acquisto').value);
    const prezzoVendita = parseFloat(document.getElementById('prezzo_vendita').value);
    
    if (!nomeModello) {
        showToast('Inserisci il nome del modello', 'warning');
        return;
    }
    
    // Importante: lo SKU del prodotto parent sarà il nome del modello
    const parentSku = nomeModello.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
    
    variantiGenerate = [];
    
    if (tipoProdotto === 'taglia') {
        if (taglieSelezionate.length === 0) {
            showToast('Seleziona almeno una taglia', 'warning');
            return;
        }
        
        // Ordina le taglie
        const taglieOrdinate = [...taglieSelezionate].sort(ordinaTaglie);
        
        // Genera varianti per ogni taglia
        taglieOrdinate.forEach(taglia => {
            const sku = generaSKU(nomeModello, taglia, '');
            variantiGenerate.push({
                sku: sku,
                parent_sku: parentSku, // Imposta esplicitamente lo SKU parent
                titolo: document.getElementById('marca').value + ' ' + nomeModello + ' - ' + taglia,
                taglia: taglia,
                colore: '',
                ean: '',
                prezzo_acquisto: prezzoAcquisto,
                prezzo_vendita: prezzoVendita
            });
        });
    } else if (tipoProdotto === 'colore') {
        if (coloriSelezionati.length === 0) {
            showToast('Seleziona almeno un colore', 'warning');
            return;
        }
        
        // Ordina i colori alfabeticamente
        const coloriOrdinati = [...coloriSelezionati].sort();
        
        // Genera varianti per ogni colore
        coloriOrdinati.forEach(colore => {
            const sku = generaSKU(nomeModello, '', colore);
            variantiGenerate.push({
                sku: sku,
                parent_sku: parentSku, // Imposta esplicitamente lo SKU parent
                titolo: document.getElementById('marca').value + ' ' + nomeModello + ' - ' + colore,
                taglia: '',
                colore: colore,
                ean: '',
                prezzo_acquisto: prezzoAcquisto,
                prezzo_vendita: prezzoVendita
            });
        });
    } else if (tipoProdotto === 'taglia_colore') {
        if (taglieSelezionate.length === 0 || coloriSelezionati.length === 0) {
            showToast('Seleziona almeno una taglia e un colore', 'warning');
            return;
        }
        
        // Ordina i colori alfabeticamente
        const coloriOrdinati = [...coloriSelezionati].sort();
        
        // Ordina le taglie
        const taglieOrdinate = [...taglieSelezionate].sort(ordinaTaglie);
        
        // Prima raggruppa per colore, poi per taglia (invertito rispetto alla versione precedente)
        coloriOrdinati.forEach(colore => {
            taglieOrdinate.forEach(taglia => {
                const sku = generaSKU(nomeModello, taglia, colore);
                variantiGenerate.push({
                    sku: sku,
                    parent_sku: parentSku, // Imposta esplicitamente lo SKU parent
                    titolo: document.getElementById('marca').value + ' ' + nomeModello + ' - ' + colore + ' - ' + taglia,
                    taglia: taglia,
                    colore: colore,
                    ean: '',
                    prezzo_acquisto: prezzoAcquisto,
                    prezzo_vendita: prezzoVendita
                });
            });
        });
    }
    
    // Aggiorna l'anteprima delle varianti
    if (variantiGenerate.length > 0) {
        mostraAnteprimaVarianti();
        document.getElementById('varianti_json').value = JSON.stringify(variantiGenerate);
        document.getElementById('reset_varianti').style.display = 'inline-block';
        
        // Mostra un messaggio di successo
        showToast(`Generate ${variantiGenerate.length} varianti con successo!`, 'success');
    }
}

/**
 * Miglioramento per la gestione del prezzo con possibilità di modifica manuale
 */
function setupPrezzoManager() {
    const prezzoAcquistoInput = document.getElementById('prezzo_acquisto');
    const prezzoVenditaInput = document.getElementById('prezzo_vendita');
    const tipologiaSelect = document.getElementById('tipologia');
    const calcolaPrezzoBtn = document.getElementById('calcola_prezzo');
    
    // Calcola prezzo automaticamente quando cambia il prezzo di acquisto
    prezzoAcquistoInput.addEventListener('input', function() {
        const tipologia = tipologiaSelect.value;
        if (tipologia && !isNaN(parseFloat(this.value))) {
            calcolaPrezzoAutomatico();
        }
    });
    
    // Calcola prezzo automaticamente quando cambia la tipologia
    tipologiaSelect.addEventListener('change', function() {
        if (this.value && !isNaN(parseFloat(prezzoAcquistoInput.value))) {
            calcolaPrezzoAutomatico();
        }
    });
    
    // Consente la modifica manuale del prezzo di vendita
    prezzoVenditaInput.addEventListener('focus', function() {
        this.dataset.original = this.value;
    });
    
    prezzoVenditaInput.addEventListener('blur', function() {
        if (this.value !== this.dataset.original) {
            showToast('Prezzo modificato manualmente', 'info');
        }
    });
}

/**
 * Funzione per mostrare toast di notifica
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '5';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    const content = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toast.innerHTML = content;
    document.getElementById('toast-container').appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, {
        delay: 3000
    });
    bsToast.show();
    
    // Rimuovi il toast dopo che è stato nascosto
    toast.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

/**
 * Assistente di digitazione per i campi con suggerimenti
 */
function setupTypeaheadAssistant(inputId, apiEndpoint) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    // Crea il contenitore per i suggerimenti
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'typeahead-suggestions d-none';
    input.parentNode.style.position = 'relative';
    input.parentNode.appendChild(suggestionsContainer);
    
    // Aggiungi un'icona di assistenza
    const helperIcon = document.createElement('span');
    helperIcon.className = 'input-help';
    helperIcon.innerHTML = '<i class="bi bi-question-circle text-info"></i>';
    helperIcon.title = 'Digitare per visualizzare i suggerimenti';
    input.parentNode.appendChild(helperIcon);
    
    let currentRequest = null;
    let selectedIndex = -1;
    
    // Gestisce l'input
    input.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Se la richiesta precedente è ancora in corso, interrompila
        if (currentRequest) {
            currentRequest.abort();
        }
        
        // Se il valore è vuoto, nascondi i suggerimenti
        if (!query) {
            suggestionsContainer.classList.add('d-none');
            suggestionsContainer.innerHTML = '';
            return;
        }
        
        // Mostra un indicatore di caricamento
        suggestionsContainer.classList.remove('d-none');
        suggestionsContainer.innerHTML = '<div class="p-3 text-center"><div class="loading-spinner"></div> Ricerca in corso...</div>';
        
        // Crea una nuova richiesta
        currentRequest = new AbortController();
        
        // Fetch dei suggerimenti
        fetch(`${apiEndpoint}?search=${encodeURIComponent(query)}`, {
            signal: currentRequest.signal
        })
        .then(response => response.json())
        .then(data => {
            suggestionsContainer.innerHTML = '';
            
            if (data.success && data.results && data.results.length > 0) {
                data.results.forEach((item, index) => {
                    const suggestion = document.createElement('div');
                    suggestion.className = 'typeahead-suggestion';
                    suggestion.textContent = item.name || item;
                    suggestion.addEventListener('click', function() {
                        input.value = item.name || item;
                        suggestionsContainer.classList.add('d-none');
                    });
                    suggestionsContainer.appendChild(suggestion);
                });
            } else {
                suggestionsContainer.innerHTML = '<div class="p-3 text-center text-muted">Nessun risultato trovato</div>';
            }
            
            // Resetta l'indice selezionato
            selectedIndex = -1;
        })
        .catch(error => {
            if (error.name !== 'AbortError') {
                suggestionsContainer.innerHTML = '<div class="p-3 text-center text-danger">Errore durante la ricerca</div>';
            }
        })
        .finally(() => {
            currentRequest = null;
        });
    });
    
    // Gestisce i tasti freccia per navigare nei suggerimenti
    input.addEventListener('keydown', function(e) {
        const suggestions = suggestionsContainer.querySelectorAll('.typeahead-suggestion');
        
        if (suggestions.length === 0) return;
        
        // Freccia giù
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
            updateSelectedSuggestion(suggestions);
        }
        // Freccia su
        else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            updateSelectedSuggestion(suggestions);
        }
        // Invio - seleziona il suggerimento corrente
        else if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            input.value = suggestions[selectedIndex].textContent;
            suggestionsContainer.classList.add('d-none');
        }
        // Esc - chiudi i suggerimenti
        else if (e.key === 'Escape') {
            suggestionsContainer.classList.add('d-none');
        }
    });
    
    // Aggiorna lo stile del suggerimento selezionato
    function updateSelectedSuggestion(suggestions) {
        suggestions.forEach((s, i) => {
            s.classList.toggle('active', i === selectedIndex);
            if (i === selectedIndex) {
                s.scrollIntoView({ block: 'nearest' });
            }
        });
    }
    
    // Chiudi i suggerimenti quando si clicca altrove
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.classList.add('d-none');
        }
    });
}