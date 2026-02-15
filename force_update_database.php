<?php
/**
 * MISE À JOUR FORCÉE - Ajoute les colonnes manquantes
 * Méthode : Désactiver temporairement les foreign keys
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mise à jour forcée</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        h1 { color: #d4a574; }
        .btn {
            background: #d4a574;
            color: white;
            padding: 12px 24px;
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
    <h1>🔧 Mise à jour FORCÉE de la base de données</h1>
    
    <?php
    $dbFile = 'artgallery.db';
    
    if (!file_exists($dbFile)) {
        echo '<div class="box">';
        echo '<p class="error">❌ Base de données introuvable</p>';
        echo '</div>';
        exit;
    }
    
    if (isset($_POST['force_update'])) {
        echo '<div class="box">';
        echo '<h2>🚀 Exécution de la mise à jour forcée...</h2>';
        
        try {
            $db = new SQLite3($dbFile);
            $db->busyTimeout(10000);
            
            // ÉTAPE 1 : Désactiver les foreign keys temporairement
            echo '<p>🔓 Désactivation des contraintes de clés étrangères...</p>';
            $db->exec('PRAGMA foreign_keys = OFF');
            echo '<p class="success">✅ Contraintes désactivées</p>';
            
            // ÉTAPE 2 : Démarrer une transaction
            echo '<p>📦 Démarrage de la transaction...</p>';
            $db->exec('BEGIN TRANSACTION');
            
            // ÉTAPE 3 : Ajouter les colonnes une par une
            $columnsToAdd = [
                ['name' => 'photos', 'definition' => 'TEXT DEFAULT NULL'],
                ['name' => 'dimensions', 'definition' => 'TEXT DEFAULT NULL'],
                ['name' => 'technique', 'definition' => 'TEXT DEFAULT NULL'],
                ['name' => 'technique_custom', 'definition' => 'TEXT DEFAULT NULL'],
                ['name' => 'badge', 'definition' => "TEXT DEFAULT 'Disponible'"]
            ];
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($columnsToAdd as $column) {
                $sql = "ALTER TABLE artworks ADD COLUMN {$column['name']} {$column['definition']}";
                
                try {
                    if ($db->exec($sql)) {
                        echo '<p class="success">✅ Colonne ajoutée : ' . $column['name'] . '</p>';
                        $successCount++;
                    } else {
                        $error = $db->lastErrorMsg();
                        if (strpos($error, 'duplicate column') !== false) {
                            echo '<p class="warning">⚠️ Colonne déjà existante : ' . $column['name'] . '</p>';
                        } else {
                            echo '<p class="error">❌ Erreur pour ' . $column['name'] . ' : ' . $error . '</p>';
                            $errorCount++;
                        }
                    }
                } catch (Exception $e) {
                    echo '<p class="error">❌ Exception pour ' . $column['name'] . ' : ' . $e->getMessage() . '</p>';
                    $errorCount++;
                }
            }
            
            // ÉTAPE 4 : Valider la transaction
            echo '<p>💾 Validation de la transaction...</p>';
            $db->exec('COMMIT');
            echo '<p class="success">✅ Transaction validée</p>';
            
            // ÉTAPE 5 : Réactiver les foreign keys
            echo '<p>🔒 Réactivation des contraintes...</p>';
            $db->exec('PRAGMA foreign_keys = ON');
            echo '<p class="success">✅ Contraintes réactivées</p>';
            
            // ÉTAPE 6 : Vérification
            echo '<hr><h3>🔍 Vérification finale</h3>';
            
            $verifyQuery = "PRAGMA table_info(artworks)";
            $result = $db->query($verifyQuery);
            
            $foundColumns = [];
            while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
                $foundColumns[] = $col['name'];
            }
            
            echo '<p><strong>Colonnes dans la table :</strong></p>';
            echo '<pre>' . implode(', ', $foundColumns) . '</pre>';
            
            // Vérifier que toutes les nouvelles colonnes sont présentes
            $requiredNew = ['photos', 'dimensions', 'technique', 'technique_custom', 'badge'];
            $stillMissing = array_diff($requiredNew, $foundColumns);
            
            if (empty($stillMissing)) {
                echo '<h2 class="success">🎉 SUCCÈS ! Toutes les colonnes ont été ajoutées !</h2>';
                echo '<p>Nombre total de colonnes : ' . count($foundColumns) . '</p>';
                echo '<p><a href="verify_columns.php"><button class="btn">🔍 Vérifier à nouveau</button></a></p>';
                echo '<p><a href="diagnostic_api.php"><button class="btn">📊 Lancer le diagnostic</button></a></p>';
            } else {
                echo '<p class="error">❌ Colonnes encore manquantes : ' . implode(', ', $stillMissing) . '</p>';
                echo '<p class="warning">⚠️ La base de données pourrait être verrouillée ou corrompue.</p>';
            }
            
            $db->close();
            
        } catch (Exception $e) {
            echo '<p class="error">❌ ERREUR FATALE : ' . $e->getMessage() . '</p>';
            
            // Essayer de rollback
            try {
                $db->exec('ROLLBACK');
                $db->exec('PRAGMA foreign_keys = ON');
                echo '<p class="warning">⚠️ Transaction annulée, base restaurée</p>';
            } catch (Exception $e2) {
                echo '<p class="error">❌ Impossible d\'annuler : ' . $e2->getMessage() . '</p>';
            }
        }
        
        echo '</div>';
        
    } else {
        // Formulaire de confirmation
        echo '<div class="box">';
        echo '<h2>⚠️ Attention : Mise à jour forcée</h2>';
        
        echo '<p><strong>Cette opération va :</strong></p>';
        echo '<ul>';
        echo '<li>Désactiver temporairement les contraintes de clés étrangères</li>';
        echo '<li>Ajouter les 5 colonnes manquantes dans une transaction sécurisée</li>';
        echo '<li>Réactiver les contraintes</li>';
        echo '<li>Vérifier que tout est OK</li>';
        echo '</ul>';
        
        echo '<p class="warning"><strong>⚠️ IMPORTANT :</strong> Assurez-vous que personne d\'autre n\'accède à la base pendant cette opération.</p>';
        
        echo '<form method="POST">';
        echo '<button type="submit" name="force_update" class="btn">🚀 LANCER LA MISE À JOUR FORCÉE</button>';
        echo '</form>';
        
        echo '<p><a href="verify_columns.php"><button class="btn">🔍 Vérifier l\'état actuel</button></a></p>';
        echo '</div>';
    }
    ?>
    
</body>
</html>
