<?php

/**
 * Ottiene i fornitori dalla cache
 * 
 * @param string $search Termine di ricerca opzionale
 * @return array Array di fornitori
 */
function getFornitori($search = '') {
    $cacheFile = UPLOADS_DIR . '/fornitori_cache.json';
    
    if (file_exists($cacheFile)) {
        $fornitori = json_decode(file_get_contents($cacheFile), true);
        
        // Filtra per il termine di ricerca se specificato
        if (!empty($search)) {
            $search = strtolower($search);
            $fornitori = array_filter($fornitori, function($f) use ($search) {
                return stripos(strtolower($f['name']), $search) !== false;
            });
        }
        
        return $fornitori;
    }
    
    return [];
}

/**
 * Ottiene le marche dalla cache
 * 
 * @param string $search Termine di ricerca opzionale
 * @return array Array di marche
 */
function getMarche($search = '') {
    $cacheFile = UPLOADS_DIR . '/marche_cache.json';
    
    if (file_exists($cacheFile)) {
        $marche = json_decode(file_get_contents($cacheFile), true);
        
        // Filtra per il termine di ricerca se specificato
        if (!empty($search)) {
            $search = strtolower($search);
            $marche = array_filter($marche, function($m) use ($search) {
                return stripos(strtolower($m['name']), $search) !== false;
            });
        }
        
        return $marche;
    }
    
    return [];
}

/**
 * Ottiene le aliquote IVA dalla cache
 * 
 * @return array Array di aliquote IVA
 */
function getAliquoteIVA() {
    $cacheFile = UPLOADS_DIR . '/iva_cache.json';
    
    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    return [];
}

/**
 * Verifica se uno SKU esiste già nel database
 * 
 * @param string $sku SKU da verificare
 * @return bool True se lo SKU esiste già, false altrimenti
 */
function skuExists($sku) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM prodotti WHERE sku = ?");
    $stmt->execute([$sku]);
    return $stmt->fetch() !== false;
}

/**
 * Recupera tutti i generi disponibili (o crea una lista predefinita)
 * 
 * @return array Lista di generi
 */
/**
 * Verifica che la funzione non esista già prima di dichiararla
 */
if (!function_exists('getGeneri')) {
    function getGeneri() {
        try {
            $conn = getDbConnection();
            $generi = ['' => 'Seleziona...'];
            
            // Prova a caricare da cache_generi
            if ($conn->query("SHOW TABLES LIKE 'cache_generi'")->rowCount() > 0) {
                $stmt = $conn->query("SELECT nome FROM cache_generi ORDER BY nome");
                while ($row = $stmt->fetch()) {
                    $key = strtolower(str_replace(' ', '_', $row['nome']));
                    $generi[$key] = $row['nome'];
                }
            }
            
            // Se non ci sono risultati, usa i valori predefiniti
            if (count($generi) <= 1) {
                $generi = [
                    '' => 'Seleziona...',
                    'uomo' => 'Uomo',
                    'donna' => 'Donna',
                    'unisex' => 'Unisex',
                    'bambino' => 'Bambino',
                    'bambina' => 'Bambina'
                ];
            }
            
            return $generi;
        } catch (Exception $e) {
            logMessage('Errore nel recupero dei generi: ' . $e->getMessage(), 'ERROR');
            return [
                '' => 'Seleziona...',
                'uomo' => 'Uomo',
                'donna' => 'Donna',
                'unisex' => 'Unisex'
            ];
        }
    }
}

/**
 * Recupera tutte le stagioni disponibili (o crea una lista predefinita)
 * 
 * @return array Lista di stagioni
 */
if (!function_exists('getStagioni')) {
    function getStagioni() {
        try {
            $conn = getDbConnection();
            $stagioni = ['' => 'Seleziona...'];
            
            // Prova a caricare da cache_stagioni
            if ($conn->query("SHOW TABLES LIKE 'cache_stagioni'")->rowCount() > 0) {
                $stmt = $conn->query("SELECT nome FROM cache_stagioni ORDER BY nome");
                while ($row = $stmt->fetch()) {
                    // Usa il valore originale come chiave, non trasformarlo
                    $stagioni[$row['nome']] = $row['nome'];
                }
            }
            
            // Se non ci sono risultati, usa i valori predefiniti
            if (count($stagioni) <= 1) {
                $stagioni = [
                    '' => 'Seleziona...',
                    'SS25' => 'SS25',
                    'FW25' => 'FW25',
                    'SS26' => 'SS26',
                    'FW26' => 'FW26',
                    'Accessori' => 'Accessori'
                ];
            }
            
            return $stagioni;
        } catch (Exception $e) {
            logMessage('Errore nel recupero delle stagioni: ' . $e->getMessage(), 'ERROR');
            return [
                '' => 'Seleziona...',
                'SS25' => 'SS25',
                'FW25' => 'FW25',
                'SS26' => 'SS26',
                'FW26' => 'FW26',
                'Accessori' => 'Accessori'
            ];
        }
    }
}

/**
 * Recupera tutti i preset di taglie dal database
 * 
 * @return array Lista di preset di taglie
 */
function getAllTagliePreset() {
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT id, nome, taglie FROM preset_taglie ORDER BY nome");
    $presets = [];
    while ($row = $stmt->fetch()) {
        $presets[$row['id']] = [
            'nome' => $row['nome'],
            'taglie' => explode(',', $row['taglie'])
        ];
    }
    return $presets;
}

/**
 * Recupera tutti i preset di colori dal database
 * 
 * @return array Lista di preset di colori
 */
function getAllColoriPreset() {
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT id, nome, colori FROM preset_colori ORDER BY nome");
    $presets = [];
    while ($row = $stmt->fetch()) {
        $presets[$row['id']] = [
            'nome' => $row['nome'],
            'colori' => explode(',', $row['colori'])
        ];
    }
    return $presets;
}

/**
 * Salva un nuovo preset di taglie
 * 
 * @param string $nome Nome del preset
 * @param array $taglie Array di taglie
 * @return int|bool ID del nuovo preset o false in caso di errore
 */
function saveTagliePreset($nome, $taglie) {
    $conn = getDbConnection();
    $taglieStr = implode(',', $taglie);
    
    try {
        // Verifica se esiste già
        $stmt = $conn->prepare("SELECT id FROM preset_taglie WHERE nome = ?");
        $stmt->execute([$nome]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Aggiorna
            $stmt = $conn->prepare("UPDATE preset_taglie SET taglie = ? WHERE id = ?");
            $stmt->execute([$taglieStr, $existing['id']]);
            return $existing['id'];
        } else {
            // Inserisci nuovo
            $stmt = $conn->prepare("INSERT INTO preset_taglie (nome, taglie) VALUES (?, ?)");
            $stmt->execute([$nome, $taglieStr]);
            return $conn->lastInsertId();
        }
    } catch (Exception $e) {
        logMessage("Errore nel salvataggio del preset taglie: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Salva un nuovo preset di colori
 * 
 * @param string $nome Nome del preset
 * @param array $colori Array di colori
 * @return int|bool ID del nuovo preset o false in caso di errore
 */
function saveColoriPreset($nome, $colori) {
    $conn = getDbConnection();
    $coloriStr = implode(',', $colori);
    
    try {
        // Verifica se esiste già
        $stmt = $conn->prepare("SELECT id FROM preset_colori WHERE nome = ?");
        $stmt->execute([$nome]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Aggiorna
            $stmt = $conn->prepare("UPDATE preset_colori SET colori = ? WHERE id = ?");
            $stmt->execute([$coloriStr, $existing['id']]);
            return $existing['id'];
        } else {
            // Inserisci nuovo
            $stmt = $conn->prepare("INSERT INTO preset_colori (nome, colori) VALUES (?, ?)");
            $stmt->execute([$nome, $coloriStr]);
            return $conn->lastInsertId();
        }
    } catch (Exception $e) {
        logMessage("Errore nel salvataggio del preset colori: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Assegna EAN automaticamente a tutte le varianti
 * 
 * @param array $varianti Array di varianti
 * @return array Varianti aggiornate con EAN
 */
function assegnaEANAutomatico($varianti) {
    foreach ($varianti as &$variante) {
        if (empty($variante['ean'])) {
            $variante['ean'] = getNextEAN();
        }
    }
    return $varianti;
}