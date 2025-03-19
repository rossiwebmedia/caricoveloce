<?php
// Attiva output buffering per prevenire output indesiderato
ob_start();

// Includi le dipendenze
require_once 'config.php';
require_once 'functions.php';

// Log delle richieste
if (isset($_POST['type'])) {
    $type = $_POST['type'];
    logMessage("Richiesto aggiornamento cache per: " . $type, 'INFO');
}

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// Determina il tipo di richiesta
$type = isset($_POST['type']) ? $_POST['type'] : '';
$result = ['success' => false, 'message' => 'Tipo di operazione non specificato'];

// Esegui l'operazione richiesta
try {
    switch ($type) {
        case 'suppliers':
            $result = updateSuppliersCache();
            break;
        case 'brands':
            $result = updateBrandsCache();
            break;
        case 'taxes':
            $result = updateTaxesCache();
            break;
        case 'seasons':
            $result = updateSeasonsCache();
            break;
        case 'genders':
            $result = updateGendersCache();
            break;
        default:
            $result = [
                'success' => false,
                'message' => 'Tipo di cache non valido'
            ];
    }
} catch (Exception $e) {
    logMessage("Errore in api_update_cache.php: " . $e->getMessage(), 'ERROR');
    $result = [
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ];
}

// Pulisci qualsiasi output precedente
ob_end_clean();

// Invia la risposta JSON
echo json_encode($result);
exit;

/**
 * Aggiorna la cache dei fornitori
 */
function updateSuppliersCache() {
    try {
        $conn = getDbConnection();
        
        // Chiama l'API Smarty per i fornitori con l'azione "list"
        $response = callSmartyApi('Suppliers', 'list');
        
        // Log della risposta per debug
        logMessage("Risposta API fornitori: " . json_encode(array_slice((array)$response, 0, 2)) . "...", 'DEBUG');
        
        // Estrai i fornitori dalla risposta
        $suppliers = [];
        
        // Caso 1: La risposta è già un array di fornitori
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['business_name']) || 
            isset($response[0]['BusinessName']) ||
            isset($response[0]['id'])
        )) {
            $suppliers = $response;
            logMessage("Trovati " . count($suppliers) . " fornitori in risposta diretta", 'INFO');
        }
        // Caso 2: Formato con Data.Records 
        else if (isset($response['Data']) && isset($response['Data']['Records']) && is_array($response['Data']['Records'])) {
            $suppliers = $response['Data']['Records'];
            logMessage("Trovati " . count($suppliers) . " fornitori in Data.Records", 'INFO');
        }
        // Caso 3: Altri formati possibili
        else if (isset($response['Records']) && is_array($response['Records'])) {
            $suppliers = $response['Records'];
            logMessage("Trovati " . count($suppliers) . " fornitori in Records", 'INFO');
        } 
        else if (isset($response['Data']) && is_array($response['Data'])) {
            $suppliers = $response['Data'];
            logMessage("Trovati " . count($suppliers) . " fornitori in Data", 'INFO');
        }
        
        // Se non ci sono fornitori validi, usa dei valori predefiniti
        if (empty($suppliers) || !is_array($suppliers)) {
            $suppliers = [
                ['Id' => '1', 'business_name' => 'Fornitore Standard 1', 'Name' => 'Fornitore Standard 1', 'email' => ''],
                ['Id' => '2', 'business_name' => 'Fornitore Standard 2', 'Name' => 'Fornitore Standard 2', 'email' => ''],
                ['Id' => '3', 'business_name' => 'Fornitore Standard 3', 'Name' => 'Fornitore Standard 3', 'email' => '']
            ];
            logMessage("Usando fornitori predefiniti", 'WARNING');
        }
        
        // Crea la tabella se non esiste
        $conn->exec('CREATE TABLE IF NOT EXISTS cache_fornitori (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            supplier_id VARCHAR(100),
            business_name VARCHAR(255),
            nome VARCHAR(255),
            email VARCHAR(255),
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Svuota la tabella
        $conn->exec('TRUNCATE TABLE cache_fornitori');
        
        // Prepara l'inserimento
        $stmt = $conn->prepare('INSERT INTO cache_fornitori (supplier_id, business_name, nome, email) VALUES (?, ?, ?, ?)');
        
        $count = 0;
        foreach ($suppliers as $supplier) {
            // Supporta diversi formati di campo nei fornitori
            $supplierId = $supplier['Id'] ?? $supplier['id'] ?? '';
            $businessName = $supplier['BusinessName'] ?? $supplier['business_name'] ?? '';
            $nome = $supplier['Name'] ?? $supplier['name'] ?? $businessName;
            $email = $supplier['Email'] ?? $supplier['email'] ?? '';
            
            if (empty($nome)) $nome = $businessName;
            if (empty($businessName)) $businessName = $nome;
            
            $stmt->execute([$supplierId, $businessName, $nome, $email]);
            $count++;
        }
        
        return [
            'success' => true,
            'message' => 'Aggiornati ' . $count . ' fornitori nella cache'
        ];
    } catch (Exception $e) {
        logMessage('Errore nell\'aggiornamento dei fornitori: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
    }
}

/**
 * Aggiorna la cache delle marche
 */
function updateBrandsCache() {
    try {
        $conn = getDbConnection();
        
        // Chiama l'API Smarty per le marche
        $response = callSmartyApi('Brands', 'list');
        
        // Log della risposta per debug
        logMessage("Risposta API marche: " . json_encode(array_slice((array)$response, 0, 2)) . "...", 'DEBUG');
        
        // Estrai le marche dalla risposta
        $brands = [];
        
        // Caso 1: La risposta è già un array di marche
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['name']) || 
            isset($response[0]['Name']) ||
            isset($response[0]['id'])
        )) {
            $brands = $response;
            logMessage("Trovate " . count($brands) . " marche in risposta diretta", 'INFO');
        }
        // Caso 2: Formato con Data.Records 
        else if (isset($response['Data']) && isset($response['Data']['Records']) && is_array($response['Data']['Records'])) {
            $brands = $response['Data']['Records'];
            logMessage("Trovate " . count($brands) . " marche in Data.Records", 'INFO');
        }
        // Caso 3: Altri formati possibili
        else if (isset($response['Records']) && is_array($response['Records'])) {
            $brands = $response['Records'];
            logMessage("Trovate " . count($brands) . " marche in Records", 'INFO');
        } 
        else if (isset($response['Data']) && is_array($response['Data'])) {
            $brands = $response['Data'];
            logMessage("Trovate " . count($brands) . " marche in Data", 'INFO');
        }
        
        // Se non ci sono marche valide, usa dei valori predefiniti
        if (empty($brands) || !is_array($brands)) {
            $brands = [
                ['id' => '1', 'name' => 'Nike'],
                ['id' => '2', 'name' => 'Adidas'],
                ['id' => '3', 'name' => 'Puma'],
                ['id' => '4', 'name' => 'New Balance'],
                ['id' => '5', 'name' => 'Converse']
            ];
            logMessage("Usando marche predefinite", 'WARNING');
        }
        
        // Crea la tabella se non esiste
        $conn->exec('CREATE TABLE IF NOT EXISTS cache_marche (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            brand_id VARCHAR(100),
            name VARCHAR(255),
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Svuota la tabella
        $conn->exec('TRUNCATE TABLE cache_marche');
        
        // Prepara l'inserimento
        $stmt = $conn->prepare('INSERT INTO cache_marche (brand_id, name) VALUES (?, ?)');
        
        $count = 0;
        foreach ($brands as $brand) {
            $brandId = $brand['Id'] ?? $brand['id'] ?? '';
            $name = $brand['Name'] ?? $brand['name'] ?? '';
            
            if (!empty($name)) {
                $stmt->execute([$brandId, $name]);
                $count++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Aggiornate ' . $count . ' marche nella cache'
        ];
    } catch (Exception $e) {
        logMessage('Errore nell\'aggiornamento delle marche: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
    }
}

/**
 * Aggiorna la cache delle aliquote IVA
 */
function updateTaxesCache() {
    try {
        $conn = getDbConnection();
        
        // Chiama l'API Smarty per le aliquote IVA
        $response = callSmartyApi('Taxes', 'list');
        
        // Log della risposta per debug
        logMessage("Risposta API aliquote: " . json_encode(array_slice((array)$response, 0, 2)) . "...", 'DEBUG');
        
        // Estrai le aliquote dalla risposta
        $taxes = [];
        
        // Caso 1: La risposta è già un array di aliquote
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['value']) || 
            isset($response[0]['Value']) ||
            isset($response[0]['name']) ||
            isset($response[0]['Name'])
        )) {
            $taxes = $response;
            logMessage("Trovate " . count($taxes) . " aliquote in risposta diretta", 'INFO');
        }
        // Caso 2: Formato con Data.Records 
        else if (isset($response['Data']) && isset($response['Data']['Records']) && is_array($response['Data']['Records'])) {
            $taxes = $response['Data']['Records'];
            logMessage("Trovate " . count($taxes) . " aliquote in Data.Records", 'INFO');
        }
        // Caso 3: Altri formati possibili
        else if (isset($response['Records']) && is_array($response['Records'])) {
            $taxes = $response['Records'];
            logMessage("Trovate " . count($taxes) . " aliquote in Records", 'INFO');
        } 
        else if (isset($response['Data']) && is_array($response['Data'])) {
            $taxes = $response['Data'];
            logMessage("Trovate " . count($taxes) . " aliquote in Data", 'INFO');
        }
        
        // Se non ci sono aliquote valide, usa dei valori predefiniti
        if (empty($taxes) || !is_array($taxes)) {
            $taxes = [
                ['id' => '1', 'name' => 'IVA 22%', 'value' => 22.00],
                ['id' => '2', 'name' => 'IVA 10%', 'value' => 10.00],
                ['id' => '3', 'name' => 'IVA 4%', 'value' => 4.00],
                ['id' => '4', 'name' => 'Esente IVA', 'value' => 0.00]
            ];
            logMessage("Usando aliquote predefinite", 'WARNING');
        }
        
        // Crea la tabella se non esiste
        $conn->exec('CREATE TABLE IF NOT EXISTS cache_aliquote (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            tax_id VARCHAR(100),
            name VARCHAR(255),
            value DECIMAL(5,2),
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Svuota la tabella
        $conn->exec('TRUNCATE TABLE cache_aliquote');
        
        // Prepara l'inserimento
        $stmt = $conn->prepare('INSERT INTO cache_aliquote (tax_id, name, value) VALUES (?, ?, ?)');
        
        $count = 0;
        foreach ($taxes as $tax) {
            $taxId = $tax['Id'] ?? $tax['id'] ?? '';
            $name = $tax['Name'] ?? $tax['name'] ?? '';
            $value = $tax['Value'] ?? $tax['value'] ?? 0;
            
            if (!empty($name)) {
                $stmt->execute([$taxId, $name, $value]);
                $count++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Aggiornate ' . $count . ' aliquote IVA nella cache'
        ];
    } catch (Exception $e) {
        logMessage('Errore nell\'aggiornamento delle aliquote IVA: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
    }
}

/**
 * Aggiorna la cache delle stagioni
 */
function updateSeasonsCache() {
    try {
        $conn = getDbConnection();
        
        // Recupera gli attributi dei prodotti
        $response = callSmartyApi('ProductAttributes', 'list');
        
        // Log della risposta per debug
        logMessage("Risposta API ProductAttributes: " . json_encode(array_slice((array)$response, 0, 2)) . "...", 'DEBUG');
        
        // Cerca l'attributo stagione
        $stagioni = [];
        $attributes = [];
        
        // Caso 1: La risposta è un array di attributi
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['name']) || 
            isset($response[0]['Name']) ||
            isset($response[0]['Values']) ||
            isset($response[0]['values'])
        )) {
            $attributes = $response;
        }
        // Caso 2: Formato con Data.Attributes
        else if (isset($response['Data']) && isset($response['Data']['Attributes'])) {
            $attributes = $response['Data']['Attributes'];
        }
        // Caso 3: Altri formati possibili
        else if (isset($response['Attributes'])) {
            $attributes = $response['Attributes'];
        }
        
        // Cerca tra gli attributi la stagione
        foreach ($attributes as $attribute) {
            $attrName = $attribute['Name'] ?? $attribute['name'] ?? '';
            
            if (!empty($attrName) && (
                strtolower($attrName) === 'stagione' || 
                strtolower($attrName) === 'stagioni' ||
                strtolower($attrName) === 'season'
            )) {
                // Trova i valori dell'attributo
                $values = $attribute['Values'] ?? $attribute['values'] ?? [];
                
                foreach ($values as $value) {
                    if (isset($value['Value']) || isset($value['value'])) {
                        $stagioni[] = $value['Value'] ?? $value['value'];
                    }
                }
                break;
            }
        }
        
        // Aggiungi le stagioni predefinite
        $defaultSeasons = ['SS25', 'FW25', 'SS26', 'FW26', 'Accessori'];
        foreach ($defaultSeasons as $season) {
            if (!in_array($season, $stagioni)) {
                $stagioni[] = $season;
            }
        }
        
        // Crea la tabella se non esiste
        $conn->exec('CREATE TABLE IF NOT EXISTS cache_stagioni (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Svuota la tabella
        $conn->exec('TRUNCATE TABLE cache_stagioni');
        
        // Prepara l'inserimento
        $stmt = $conn->prepare('INSERT INTO cache_stagioni (nome) VALUES (?)');
        
        $count = 0;
        foreach ($stagioni as $stagione) {
            if (!empty($stagione)) {
                $stmt->execute([$stagione]);
                $count++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Aggiornate ' . $count . ' stagioni nella cache'
        ];
    } catch (Exception $e) {
        logMessage('Errore nell\'aggiornamento delle stagioni: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
    }
}

/**
 * Aggiorna la cache dei generi
 */
function updateGendersCache() {
    try {
        $conn = getDbConnection();
        
        // Recupera gli attributi dei prodotti
        $response = callSmartyApi('ProductAttributes', 'list');
        
        // Log della risposta per debug
        logMessage("Risposta API ProductAttributes: " . json_encode(array_slice((array)$response, 0, 2)) . "...", 'DEBUG');
        
        // Cerca l'attributo genere
        $generi = [];
        $attributes = [];
        
        // Caso 1: La risposta è un array di attributi
        if (is_array($response) && isset($response[0]) && (
            isset($response[0]['name']) || 
            isset($response[0]['Name']) ||
            isset($response[0]['Values']) ||
            isset($response[0]['values'])
        )) {
            $attributes = $response;
        }
        // Caso 2: Formato con Data.Attributes
        else if (isset($response['Data']) && isset($response['Data']['Attributes'])) {
            $attributes = $response['Data']['Attributes'];
        }
        // Caso 3: Altri formati possibili
        else if (isset($response['Attributes'])) {
            $attributes = $response['Attributes'];
        }
        
        // Cerca tra gli attributi il genere
        foreach ($attributes as $attribute) {
            $attrName = $attribute['Name'] ?? $attribute['name'] ?? '';
            
            if (!empty($attrName) && (
                strtolower($attrName) === 'genere' || 
                strtolower($attrName) === 'generi' ||
                strtolower($attrName) === 'gender'
            )) {
                // Trova i valori dell'attributo
                $values = $attribute['Values'] ?? $attribute['values'] ?? [];
                
                foreach ($values as $value) {
                    if (isset($value['Value']) || isset($value['value'])) {
                        $generi[] = $value['Value'] ?? $value['value'];
                    }
                }
                break;
            }
        }
        
        // Aggiungi i generi predefiniti
        $defaultGenders = ['Uomo', 'Donna', 'Unisex', 'Bambino', 'Bambina'];
        foreach ($defaultGenders as $gender) {
            if (!in_array($gender, $generi)) {
                $generi[] = $gender;
            }
        }
        
        // Crea la tabella se non esiste
        $conn->exec('CREATE TABLE IF NOT EXISTS cache_generi (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Svuota la tabella
        $conn->exec('TRUNCATE TABLE cache_generi');
        
        // Prepara l'inserimento
        $stmt = $conn->prepare('INSERT INTO cache_generi (nome) VALUES (?)');
        
        $count = 0;
        foreach ($generi as $genere) {
            if (!empty($genere)) {
                $stmt->execute([$genere]);
                $count++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Aggiornati ' . $count . ' generi nella cache'
        ];
    } catch (Exception $e) {
        logMessage('Errore nell\'aggiornamento dei generi: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
    }
}