<!-- Modal per Aggiungere Nuova Tipologia -->
<div class="modal fade" id="addNewTypeModal" tabindex="-1" aria-labelledby="addNewTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNewTypeModalLabel">Nuova Tipologia Prodotto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addNewTypeForm">
                    <div class="mb-3">
                        <label for="new_type_name" class="form-label">Nome Tipologia*:</label>
                        <input type="text" class="form-control" id="new_type_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_type_multiplier" class="form-label">Moltiplicatore Prezzo*:</label>
                                <input type="number" class="form-control" id="new_type_multiplier" step="0.01" min="1" value="3.00" required>
                                <div class="form-text">Es: 3.00 = prezzo x 3</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_type_round_to" class="form-label">Arrotonda a*:</label>
                                <input type="number" class="form-control" id="new_type_round_to" step="0.01" min="0" value="9.90" required>
                                <div class="form-text">Es: 9.90</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_type_description" class="form-label">Descrizione:</label>
                        <textarea class="form-control" id="new_type_description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="saveNewTypeBtn">Salva Tipologia</button>
            </div>
        </div>
    </div>
</div>