<div class="tipo-prodotto-container mb-4">
    <label class="form-label">Tipo di Prodotto:</label>
    <div class="row g-3">
        <div class="col-md-3">
            <div class="card product-type-card" data-type="semplice">
                <div class="card-body text-center">
                    <i class="bi bi-box fs-1"></i>
                    <h5 class="mt-2">Prodotto Semplice</h5>
                    <p class="text-muted small">Senza varianti</p>
                </div>
                <div class="card-footer p-0">
                    <div class="product-type-selector"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card product-type-card" data-type="taglia">
                <div class="card-body text-center">
                    <i class="bi bi-rulers fs-1"></i>
                    <h5 class="mt-2">Varianti di Taglia</h5>
                    <p class="text-muted small">Stesso colore</p>
                </div>
                <div class="card-footer p-0">
                    <div class="product-type-selector"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card product-type-card" data-type="colore">
                <div class="card-body text-center">
                    <i class="bi bi-palette fs-1"></i>
                    <h5 class="mt-2">Varianti di Colore</h5>
                    <p class="text-muted small">Stessa taglia</p>
                </div>
                <div class="card-footer p-0">
                    <div class="product-type-selector"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card product-type-card" data-type="taglia_colore">
                <div class="card-body text-center">
                    <i class="bi bi-grid-3x3-gap fs-1"></i>
                    <h5 class="mt-2">Taglia e Colore</h5>
                    <p class="text-muted small">Matrice completa</p>
                </div>
                <div class="card-footer p-0">
                    <div class="product-type-selector"></div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="tipo_prodotto" name="tipo_prodotto" value="<?php echo $isEditMode ? htmlspecialchars($tipoProdotto) : 'semplice'; ?>">
</div>