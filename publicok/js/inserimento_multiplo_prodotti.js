/**
 * Script per la gestione dell'inserimento multiplo di prodotti
 */
document.addEventListener('DOMContentLoaded', function() {
    // Variabili globali
    const prodottiContainer = document.getElementById('prodotti_container');
    const prodottiTbody = document.getElementById('prodotti_tbody');
    const rowTemplate = document.getElementById('product_row_template').innerHTML;
    let rowCounter = 1;
    let autoSaveTimer = null;
    let selectedFornitoreId = null;
    
    // Gestione della selezione del fornitore
    document.getElementById('fornitore_id').addEventListener('change', function() {
        selectedFornitoreId = this.value;
        if (selectedFornitoreId) {
            prodottiContainer.style.display = 'block';
            // Aggiungi la prima riga se non ce ne sono
            if (prodottiTbody.children.length === 0) {
                addNewRow();
            }
        } else {
            prodottiContainer.style.display = 'none';
        }
    });
    
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
            .replace(/{ROW_ID}/g, rowId)
            .replace(/{NUMBER}/g, rowCounter);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newRow.trim();
        const rowNode = tempDiv.firstChild;
        
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
        
        prezzoAcquistoInput.addEventListener('change', function() {
            let tipologia = genereSelect.value || 'unisex';
            let prezzoAcquisto = parseFloat(this.value) || 0;
            
            // Calcola il prezzo di vendita (simile alla funzione PHP)
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
        });
        
        // Pulsante salva riga
        row.querySelector('.save-row-btn').addEventListener('click', function() {
            saveProductRow(row);
        });
        
        // Pulsante elimina riga
        row.querySelector('.delete-row-btn').addEventListener('click', function() {
            if (confirm('Sei sicuro di voler eliminare questo prodotto?')) {
                // Se la riga ha un ID prodotto, elimina anche dal database
                const productId = row.getAttribute('data-product-id');
                if (productId) {
                    deleteProductRow(productId, row);
                } else {
                    // Altrimenti rimuovi solo dalla tabella
                    row.remove();
                    updateRowNumbers();
                }
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
    
    // Elimina un prodotto dal database
    function deleteProductRow(productId, row) {
        const formData = new FormData();
        formData.append('action', 'delete_product');
        formData.append('product_id', productId);
        
        fetch('ajax_prodotti_multipli.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                row.remove();
                updateRowNumbers();
            } else {
                alert('Errore nella cancellazione: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore di rete durante la cancellazione');
        });
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
        const stagione = row.querySelector('.stagione-select').value;
        const quantita = row.querySelector('.qty-input').value;
        const prezzoAcquisto = row.querySelector('.prezzo-acquisto-input').value;
        const prezzoVendita = row.querySelector('.prezzo-vendita-input').value;
        
        // Valida i campi obbligatori
        if (!marcaId || !modello || !genere || !stagione || !quantita || !prezzoAcquisto) {
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
        formData.append('stagione', stagione);
        formData.append('quantita', quantita);
        formData.append('prezzo_acquisto', prezzoAcquisto);
        formData.append('prezzo_vendita', prezzoVendita);
        
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
                    const eanCell = document.createElement('small');
                    eanCell.textContent = 'EAN: ' + data.ean;
                    eanCell.className = 'text-muted d-block';
                    
                    // Aggiungi l'EAN sotto il modello
                    const modelloCell = row.querySelector('.modello-input').parentNode;
                    if (!modelloCell.querySelector('.text-muted')) {
                        modelloCell.appendChild(eanCell);
                    }
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
    
    // Funzione per inviare tutti i prodotti a Smarty
    document.getElementById('send_to_smarty_btn').addEventListener('click', function() {
        if (!selectedFornitoreId) {
            alert('Seleziona prima un fornitore');
            return;
        }
        
        // Verifica che ci siano prodotti salvati
        const rows = document.querySelectorAll('#prodotti_tbody .product-row[data-saved="1"]');
        if (rows.length === 0) {
            alert('Non ci sono prodotti salvati da inviare a Smarty');
            return;
        }
        
        // Raccogli gli ID dei prodotti da inviare
        const productIds = Array.from(rows).map(row => row.getAttribute('data-product-id'));
        
        // Conferma l'invio
        if (!confirm(`Sei sicuro di voler inviare ${productIds.length} prodotti a Smarty?`)) {
            return;
        }
        
        // Prepara i dati per l'invio
        const formData = new FormData();
        formData.append('action', 'send_to_smarty');
        formData.append('fornitore_id', selectedFornitoreId);
        formData.append('product_ids', JSON.stringify(productIds));
        
        // Mostra indicatore di caricamento
        const sendBtn = this;
        sendBtn.disabled = true;
        const originalText = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Invio in corso...';
        
        // Invia i dati
        fetch('api_batch_send_to_smarty.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`${data.success_count} prodotti inviati con successo a Smarty!`);
                // Aggiorna lo stato dei prodotti inviati
                rows.forEach(row => {
                    const statusCell = row.querySelector('.product-status');
                    statusCell.innerHTML = '<span class="badge bg-primary">Inviato a Smarty</span>';
                });
            } else {
                alert('Errore nell\'invio a Smarty: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Si è verificato un errore durante l\'invio a Smarty');
        })
        .finally(() => {
            // Ripristina il pulsante
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalText;
        });
    });
});