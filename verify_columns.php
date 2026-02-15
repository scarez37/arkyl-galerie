<?php
/**
 * VÉRIFICATION DIRECTE - Force une nouvelle connexion
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérification Colonnes</title>
    <style>
        body {
            font-family: monospace;
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #0f0;
        }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        pre { background: #000; padding: 10px; border: 1px solid #0f0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #0f0; padding: 8px; text-align: left; }
        th { background: #003300; }
    </style>
</head>
<body>
    <h1>🔍 VÉRIFICATION DIRECTE DES COLONNES</h1>
    
    <?php
    $dbFile = 'artgallery.db';
    
    try {
        // Forcer une nouvelle connexion
        if (file_exists($dbFile)) {
            $db = new SQLite3($dbFile, SQLITE3_OPEN_READWRITE);
            $db->busyTimeout(5000);
            
            echo '<h2>📊 TOUTES LES COLONNES DE LA TABLE "artworks"</h2>';
            
            // Méthode 1 : PRAGMA table_info
            echo '<h3>Méthode 1 : PRAGMA table_info</h3>';
            $result = $db->query("PRAGMA table_info(artworks)");
            
            echo '<table>';
            echo '<tr><th>#</th><th>Nom</th><th>Type</th><th>NotNull</th><th>Défaut</th><th>PK</th></tr>';
            
            $columns = [];
            while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $col['name'];
                echo '<tr>';
                echo '<td>' . $col['cid'] . '</td>';
                echo '<td><strong>' . $col['name'] . '</strong></td>';
                echo '<td>' . $col['type'] . '</td>';
                echo '<td>' . ($col['notnull'] ? 'Oui' : 'Non') . '</td>';
                echo '<td>' . ($col['dflt_value'] ?? 'NULL') . '</td>';
                echo '<td>' . ($col['pk'] ? 'Oui' : '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            echo '<h3>✅ Total : ' . count($columns) . ' colonnes</h3>';
            echo '<pre>' . implode(', ', $columns) . '</pre>';
            
            // Méthode 2 : SELECT avec LIMIT 0
            echo '<h3>Méthode 2 : Structure via SELECT</h3>';
            $result = $db->query("SELECT * FROM artworks LIMIT 0");
            $numCols = $result->numColumns();
            
            echo '<p>Nombre de colonnes détectées : <strong>' . $numCols . '</strong></p>';
            echo '<ul>';
            for ($i = 0; $i < $numCols; $i++) {
                echo '<li>' . $result->columnName($i) . '</li>';
            }
            echo '</ul>';
            
            // Méthode 3 : Schéma complet de la table
            echo '<h3>Méthode 3 : Schéma SQL complet</h3>';
            $schema = $db->querySingle("SELECT sql FROM sqlite_master WHERE type='table' AND name='artworks'");
            echo '<pre>' . htmlspecialchars($schema) . '</pre>';
            
            // Test : Essayer de sélectionner les nouvelles colonnes
            echo '<h3>Test 4 : SELECT direct des nouvelles colonnes</h3>';
            
            $testCols = ['photos', 'dimensions', 'technique', 'technique_custom', 'badge'];
            
            foreach ($testCols as $col) {
                try {
                    $testQuery = "SELECT $col FROM artworks LIMIT 1";
                    $testResult = $db->query($testQuery);
                    
                    if ($testResult) {
                        echo '<p class="success">✅ Colonne "' . $col . '" accessible</p>';
                        $row = $testResult->fetchArray(SQLITE3_ASSOC);
                        echo '<pre>Valeur : ' . var_export($row[$col], true) . '</pre>';
                    } else {
                        echo '<p class="error">❌ Colonne "' . $col . '" non accessible</p>';
                    }
                } catch (Exception $e) {
                    echo '<p class="error">❌ Erreur sur "' . $col . '" : ' . $e->getMessage() . '</p>';
                }
            }
            
            // Test 5 : Essayer une requête complète comme l'API
            echo '<h3>Test 5 : Requête API complète</h3>';
            
            try {
                $apiQuery = "SELECT 
                    a.id, 
                    a.title, 
                    a.price, 
                    a.image_url, 
                    a.category,
                    a.description,
                    a.photos,
                    a.dimensions,
                    a.technique,
                    a.technique_custom,
                    a.badge
                FROM artworks a 
                LIMIT 1";
                
                $apiResult = $db->query($apiQuery);
                
                if ($apiResult) {
                    $artwork = $apiResult->fetchArray(SQLITE3_ASSOC);
                    echo '<p class="success">✅ REQUÊTE API COMPLÈTE RÉUSSIE !</p>';
                    echo '<pre>' . json_encode($artwork, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                } else {
                    echo '<p class="error">❌ Erreur : ' . $db->lastErrorMsg() . '</p>';
                }
                
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception : ' . $e->getMessage() . '</p>';
            }
            
            $db->close();
            
            echo '<hr>';
            echo '<h2>🎯 CONCLUSION</h2>';
            
            $required = ['photos', 'dimensions', 'technique', 'technique_custom', 'badge'];
            $missing = array_diff($required, $columns);
            
            if (empty($missing)) {
                echo '<p class="success">✅✅✅ TOUTES LES COLONNES SONT PRÉSENTES !</p>';
                echo '<p>Vous pouvez maintenant utiliser api_galerie_publique.php (version complète)</p>';
            } else {
                echo '<p class="error">❌ Colonnes encore manquantes :</p>';
                echo '<ul>';
                foreach ($missing as $col) {
                    echo '<li class="error">' . $col . '</li>';
                }
                echo '</ul>';
                echo '<p class="warning">⚠️ Il faut peut-être redémarrer le serveur ou vider le cache SQLite</p>';
            }
            
        } else {
            echo '<p class="error">Base de données non trouvée</p>';
        }
        
    } catch (Exception $e) {
        echo '<p class="error">ERREUR : ' . $e->getMessage() . '</p>';
    }
    ?>
    
</body>
</html>
