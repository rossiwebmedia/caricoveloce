// Funzioni per la selezione del tipo di prodotto e tipologia
function selectTipoProdotto(value) {
    // Imposta il valore nell'input nascosto
    document.getElementById('tipo_prodotto').value = value;
    
    // Aggiorna lo stato attivo dei pulsanti
    document.querySelectorAll('.tipo-prodotto-btn button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.tipo-prodotto-btn[data-value="${value}"] button`).classList.add('active');
    
    // Mostra/nascondi i container appropriati
    mostraContainerTipoProdotto(value);
}

function selectTipologia(value) {
    // Imposta il valore nell'input nascosto
    document.getElementById('tipologia').value = value;
    
    // Aggiorna lo stato attivo dei pulsanti
    document.querySelectorAll('.tipologia-btn button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.tipologia-btn[data-value="${value}"] button`).classList.add('active');
    
    // Imposta automaticamente il genere in base alla tipologia
    const genereSelect = document.getElementById('genere');
    
    if (value.includes('uomo')) {
        genereSelect.value = 'uomo';
    } else if (value.includes('donna')) {
        genereSelect.value = 'donna';
    } else if (value === 'accessori') {
        genereSelect.value = 'unisex';
    }
    
    // Calcola automaticamente il prezzo di vendita
    calcolaPrezzoAutomatico();
}

// Funzione per calcolare automaticamente il prezzo di vendita
function calcolaPrezzoAutomatico() {
    const prezzoAcquisto = parseFloat(document.getElementById('prezzo_acquisto').value);
    const tipologia = document.getElementById('tipologia').value;
    
    if (isNaN(prezzoAcquisto) || prezzoAcquisto <= 0 || !tipologia) {
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
            document.getElementById('prezzo_vendita').value = data.prezzo_vendita;
            
            if (document.getElementById('tipo_prodotto').value === 'semplice') {
                aggiornaAnteprimaSemplice();
            }
        }
    })
    .catch(error => {
        console.error('Errore:', error);
    });
}

// Inizializzazione di autocompletamento per i campi
document.addEventListener('DOMContentLoaded', function() {
    // Inizializza l'autocompletamento per il fornitore
    initAutocomplete('fornitore', 'get_suppliers.php');
    
    // Inizializza l'autocompletamento per il marchio
    initAutocomplete('marca', 'get_brands.php');
    
    // Collega il calcolo automatico del prezzo all'evento input del prezzo di acquisto
    document.getElementById('prezzo_acquisto').addEventListener('input', function() {
        calcolaPrezzoAutomatico();
    });
    
    // Imposta il tipo di prodotto selezionato all'avvio
    const tipoProdottoIniziale = document.getElementById('tipo_prodotto').value || 'semplice';
    selectTipoProdotto(tipoProdottoIniziale);
    
    // Imposta la tipologia selezionata all'avvio
    const tipologiaIniziale = document.getElementById('tipologia').value;
    if (tipologiaIniziale) {
        selectTipologia(tipologiaIniziale);
    }
    
    // Inizializza l'autocompletamento per nuova taglia
    initTagliaAutocomplete();
    
    // Inizializza l'autocompletamento per nuovo colore
    initColoreAutocomplete();
});

// Funzione per inizializzare l'autocompletamento per i campi
function initAutocomplete(fieldId, apiUrl) {
    const inputElement = document.getElementById(fieldId);
    const datalistId = `${fieldId}_list`;
    
    // Crea un elemento datalist se non esiste
    let datalist = document.getElementById(datalistId);
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = datalistId;
        document.body.appendChild(datalist);
        inputElement.setAttribute('list', datalistId);
    }
    
    // Funzione per caricare i suggerimenti
    function loadSuggestions(query) {
        fetch(`${apiUrl}?search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                // Svuota il datalist
                datalist.innerHTML = '';
                
                if (data.success) {
                    // Aggiungi le opzioni al datalist
                    let items;
                    if (fieldId === 'fornitore') {
                        items = data.fornitori || [];
                    } else if (fieldId === 'marca') {
                        items = data.marche || [];
                    }
                    
                    items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.name || item.business_name || item;
                        datalist.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
    }
    
    // Carica i suggerimenti iniziali (vuoti)
    loadSuggestions('');
    
    // Aggiungi l'event listener per l'input
    inputElement.addEventListener('input', function() {
        loadSuggestions(this.value);
    });
}

// Funzione per inizializzare l'autocompletamento per le taglie
function initTagliaAutocomplete() {
    const inputElement = document.getElementById('nuova_taglia');
    const datalistId = 'taglie_list';
    
    // Crea un elemento datalist se non esiste
    let datalist = document.getElementById(datalistId);
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = datalistId;
        document.body.appendChild(datalist);
        inputElement.setAttribute('list', datalistId);
    }
    
    // Funzione per caricare i suggerimenti
    function loadSuggestions(query) {
        fetch(`api_get_attributes.php?tipo=taglia&search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                // Svuota il datalist
                datalist.innerHTML = '';
                
                if (data.success) {
                    // Aggiungi le opzioni al datalist
                    data.attributi.forEach(taglia => {
                        const option = document.createElement('option');
                        option.value = taglia;
                        datalist.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
    }
    
    // Carica i suggerimenti iniziali (vuoti)
    loadSuggestions('');
    
    // Aggiungi l'event listener per l'input
    inputElement.addEventListener('input', function() {
        loadSuggestions(this.value);
    });
}

// Funzione per inizializzare l'autocompletamento per i colori
function initColoreAutocomplete() {
    const inputElement = document.getElementById('nuovo_colore');
    const datalistId = 'colori_list';
    
    // Crea un elemento datalist se non esiste
    let datalist = document.getElementById(datalistId);
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = datalistId;
        document.body.appendChild(datalist);
        inputElement.setAttribute('list', datalistId);
    }
    
    // Funzione per caricare i suggerimenti
    function loadSuggestions(query) {
        fetch(`api_get_attributes.php?tipo=colore&search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                // Svuota il datalist
                datalist.innerHTML = '';
                
                if (data.success) {
                    // Aggiungi le opzioni al datalist
                    data.attributi.forEach(colore => {
                        const option = document.createElement('option');
                        option.value = colore;
                        datalist.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
    }
    
    // Carica i suggerimenti iniziali (vuoti)
    loadSuggestions('');
    
    // Aggiungi l'event listener per l'input
    inputElement.addEventListener('input', function() {
        loadSuggestions(this.value);
    });
}

// Funzione migliorata per generare le varianti ordinate prima per colore e poi per taglia
function generaVariantiOrdinate() {
    const tipoProdotto = document.getElementById('tipo_prodotto').value;
    const nomeModello = document.getElementById('nome_modello').value.trim();
    const prezzoAcquisto = parseFloat(document.getElementById('prezzo_acquisto').value);
    const prezzoVendita = parseFloat(document.getElementById('prezzo_vendita').value);
    
    if (!nomeModello) {
        alert('Inserisci il nome del modello');
        return;
    }
    
    variantiGenerate = [];
    
    if (tipoProdotto === 'taglia') {
        if (taglieSelezionate.length === 0) {
            alert('Seleziona almeno una taglia');
            return;
        }
        
        // Ordina le taglie
        const taglieOrdinate = [...taglieSelezionate].sort(ordinaTaglie);
        
        // Genera varianti per ogni taglia
        taglieOrdinate.forEach(taglia => {
            const sku = generaSKU(nomeModello, taglia, '');
            variantiGenerate.push({
                sku: sku,
                parent_sku: nomeModello,
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
            alert('Seleziona almeno un colore');
            return;
        }
        
        // Ordina i colori alfabeticamente
        const coloriOrdinati = [...coloriSelezionati].sort();
        
        // Genera varianti per ogni colore
        coloriOrdinati.forEach(colore => {
            const sku = generaSKU(nomeModello, '', colore);
            variantiGenerate.push({
                sku: sku,
                parent_sku: nomeModello,
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
            alert('Seleziona almeno una taglia e un colore');
            return;
        }
        
        // Ordina i colori alfabeticamente
        const coloriOrdinati = [...coloriSelezionati].sort();
        
        // Ordina le taglie
        const taglieOrdinate = [...taglieSelezionate].sort(ordinaTaglie);
        
        // Prima raggruppa per colore, poi per taglia
        coloriOrdinati.forEach(colore => {
            taglieOrdinate.forEach(taglia => {
                const sku = generaSKU(nomeModello, taglia, colore);
                variantiGenerate.push({
                    sku: sku,
                    parent_sku: nomeModello,
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
    }
}

// Funzione per ordinare le taglie correttamente
function ordinaTaglie(a, b) {
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
}

// Funzione modificata per generare uno SKU
function generaSKU(nomeModello, taglia = '', colore = '') {
    // Converti in minuscolo e sostituisci gli spazi con trattini
    let base = nomeModello.toLowerCase().replace(/\s+/g, '-');
    
    // Rimuovi caratteri speciali
    base = base.replace(/[^a-z0-9\-]/g, '');
    
    if (taglia && colore) {
        return `${base}-${colore.toLowerCase().replace(/\s+/g, '-')}-${taglia.toLowerCase()}`;
    } else if (taglia) {
        return `${base}-${taglia.toLowerCase()}`;
    } else if (colore) {
        return `${base}-${colore.toLowerCase().replace(/\s+/g, '-')}`;
    }
    
    return base;
}

// Funzione per inviare i dati a Smarty
function inviaSmarty() {
    // Per prodotti semplici, verifica che i campi siano compilati
    const tipoProdotto = document.getElementById('tipo_prodotto').value;
    const nomeModello = document.getElementById('nome_modello').value.trim();
    const titolo = document.getElementById('titolo').value.trim();
    const tipologia = document.getElementById('tipologia').value;
    const prezzoAcquisto = parseFloat(document.getElementById('prezzo_acquisto').value);
    const prezzoVendita = parseFloat(document.getElementById('prezzo_vendita').value);
    const marca = document.getElementById('marca').value;
    const genere = document.getElementById('genere').value;
    const ean = document.getElementById('ean').value;
    
    if (!nomeModello || !tipologia || isNaN(prezzoAcquisto) || prezzoAcquisto <= 0 || isNaN(prezzoVendita) || prezzoVendita <= 0) {
        alert('Compila tutti i campi obbligatori prima di inviare a Smarty');
        return;
    }
    
    // Verifica se sono state generate varianti per prodotti con varianti
    if (tipoProdotto !== 'semplice' && variantiGenerate.length === 0) {
        alert('Genera prima le varianti del prodotto');
        return;
    }
    
    // Conferma prima di procedere
    if (!confirm('Sei sicuro di voler inviare il prodotto a Smarty?')) {
        return;
    }
    
    let variants = [];
    
    if (tipoProdotto === 'semplice') {
        // Crea una "falsa" variante per uniformare il formato
        variants = [{
            sku: generaSKU(nomeModello),
            parent_sku: null,
            titolo: titolo || (marca + ' ' + nomeModello),
            tipologia: tipologia,
            genere: genere,
            stagione: document.getElementById('stagione').value,
            taglia: '',
            colore: '',
            ean: ean,
            prezzo_acquisto: prezzoAcquisto,
            prezzo_vendita: prezzoVendita,
            aliquota_iva: document.getElementById('aliquota_iva').value,
            fornitore: document.getElementById('fornitore').value,
            marca: marca
        }];
    } else {
        // Usa le varianti generate in precedenza
        // Aggiungi prodotto padre come prima variante
        variants = [{
            sku: nomeModello,
            parent_sku: null,
            titolo: titolo || (marca + ' ' + nomeModello),
            tipologia: tipologia,
            genere: genere,
            stagione: document.getElementById('stagione').value,
            taglia: '',
            colore: '',
            ean: '',
            prezzo_acquisto: prezzoAcquisto,
            prezzo_vendita: prezzoVendita,
            aliquota_iva: document.getElementById('aliquota_iva').value,
            fornitore: document.getElementById('fornitore').value,
            marca: marca
        }, ...variantiGenerate];
    }
    
    // Raccolta dati del prodotto principale
    const datiProdotto = {
        tipo_prodotto: tipoProdotto,
        tipologia: tipologia,
        nome_modello: nomeModello,
        titolo: titolo,
        prezzo_acquisto: prezzoAcquisto,
        prezzo_vendita: prezzoVendita,
        aliquota_iva: document.getElementById('aliquota_iva').value,
        genere: genere,
        stagione: document.getElementById('stagione').value,
        fornitore: document.getElementById('fornitore').value,
        marca: marca,
        ean: ean,
        variants: variants,
        is_simple: tipoProdotto === 'semplice'
    };
    
    // Disabilita il pulsante durante l'invio
    const inviaSmartyBtn = document.getElementById('invia_smarty');
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
}
