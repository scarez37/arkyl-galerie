<?php
/**
 * SCRIPT DE MISE À JOUR DE LA BASE DE DONNÉES
 * Ajoute les colonnes manquantes si nécessaire
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mise à jour BDD ARKYL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        h1 { color: #d4a574; }
        .btn {
            background: #d4a574;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover { background: #c49565; }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔧 Mise à jour de la base de données ARKYL</h1>
    
    <?php
    $dbFile = 'artgallery.db';
    
    if (!file_exists($dbFile)) {
        echo '<div class="box">';
        echo '<p class="error">❌ Fichier de base de données introuvable : ' . $dbFile . '</p>';
        echo '<p>Chemin actuel : ' . getcwd() . '</p>';
        echo '</div>';
        exit;
    }
    
    if (isset($_POST['update'])) {
        echo '<div class="box">';
        echo '<h2>📝 Mise à jour en cours...</h2>';
        
        try {
            $db = new SQLite3($dbFile);
            $db->busyTimeout(5000);
            
            // Liste des colonnes à ajouter si elles n'existent pas
            $columnsToAdd = [
                ['name' => 'category', 'type' => 'TEXT', 'default' => "'Non spécifiée'"],
                ['name' => 'description', 'type' => 'TEXT', 'default' => "''"],
                ['name' => 'photos', 'type' => 'TEXT', 'default' => "NULL"], // JSON array
                ['name' => 'dimensions', 'type' => 'TEXT', 'default' => "NULL"], // JSON object
                ['name' => 'technique', 'type' => 'TEXT', 'default' => "NULL"],
                ['name' => 'technique_custom', 'type' => 'TEXT', 'default' => "NULL"],
                ['name' => 'badge', 'type' => 'TEXT', 'default' => "'Disponible'"],
                ['name' => 'created_at', 'type' => 'DATETIME', 'default' => "CURRENT_TIMESTAMP"]
            ];
            
            // Récupérer les colonnes existantes
            $columnsQuery = "PRAGMA table_info(artworks)";
            $columnsResult = $db->query($columnsQuery);
            
            $existingColumns = [];
            while ($col = $columnsResult->fetchArray(SQLITE3_ASSOC)) {
                $existingColumns[] = $col['name'];
            }
            
            $addedCount = 0;
            $skippedCount = 0;
            
            // Ajouter les colonnes manquantes
            foreach ($columnsToAdd as $column) {
                if (!in_array($column['name'], $existingColumns)) {
                    $sql = "ALTER TABLE artworks ADD COLUMN {$column['name']} {$column['type']} DEFAULT {$column['default']}";
                    
                    if ($db->exec($sql)) {
                        echo '<p class="success">✅ Colonne ajoutée : ' . $column['name'] . '</p>';
                        $addedCount++;
                    } else {
                        echo '<p class="error">❌ Erreur ajout ' . $column['name'] . ' : ' . $db->lastErrorMsg() . '</p>';
                    }
                } else {
                    echo '<p class="warning">⚠️ Colonne déjà existante : ' . $column['name'] . '</p>';
                    $skippedCount++;
                }
            }
            
            // Vérifier/créer la table artists si elle n'existe pas
            $tableQuery = "SELECT name FROM sqlite_master WHERE type='table' AND name='artists'";
            $tableResult = $db->query($tableQuery);
            
            if (!$tableResult->fetchArray()) {
                echo '<h3>🎨 Création de la table "artists"...</h3>';
                
                $createArtistsTable = "
                    CREATE TABLE IF NOT EXISTS artists (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        artist_name TEXT,
                        email TEXT UNIQUE,
                        password TEXT,
                        country TEXT,
                        profile_image TEXT,
                        bio TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ";
                
                if ($db->exec($createArtistsTable)) {
                    echo '<p class="success">✅ Table "artists" créée avec succès !</p>';
                } else {
                    echo '<p class="error">❌ Erreur création table : ' . $db->lastErrorMsg() . '</p>';
                }
            }
            
            $db->close();
            
            echo '<hr>';
            echo '<h3>📊 Résumé :</h3>';
            echo '<p>Colonnes ajoutées : <strong>' . $addedCount . '</strong></p>';
            echo '<p>Colonnes déjà existantes : <strong>' . $skippedCount . '</strong></p>';
            
            if ($addedCount > 0) {
                echo '<p class="success">✅ Base de données mise à jour avec succès !</p>';
            } else {
                echo '<p class="success">✅ Base de données déjà à jour !</p>';
            }
            
            echo '<p><a href="diagnostic_api.php"><button class="btn">🔍 Lancer le diagnostic</button></a></p>';
            
        } catch (Exception $e) {
            echo '<p class="error">❌ ERREUR : ' . $e->getMessage() . '</p>';
        }
        
        echo '</div>';
        
    } else {
        // Formulaire de confirmation
        echo '<div class="box">';
        echo '<h2>⚠️ Mise à jour de la structure de la base de données</h2>';
        echo '<p>Ce script va ajouter les colonnes manquantes dans la table <code>artworks</code> :</p>';
        echo '<ul>';
        echo '<li>category</li>';
        echo '<li>description</li>';
        echo '<li>photos (pour stocker plusieurs images)</li>';
        echo '<li>dimensions (largeur, hauteur, profondeur)</li>';
        echo '<li>technique</li>';
        echo '<li>technique_custom</li>';
        echo '<li>badge (statut)</li>';
        echo '<li>created_at</li>';
        echo '</ul>';
        
        echo '<p><strong>⚠️ Important :</strong> Cette opération ne supprimera aucune donnée existante.</p>';
        
        echo '<form method="POST">';
        echo '<button type="submit" name="update" class="btn">🚀 Mettre à jour la base de données</button>';
        echo '</form>';
        
        echo '<p><a href="diagnostic_api.php"><button class="btn">🔍 D\'abord lancer le diagnostic</button></a></p>';
        echo '</div>';
    }
    ?>
    
</body>
</html>
