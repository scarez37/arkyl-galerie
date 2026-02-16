<?php
header('Content-Type: text/html; charset=utf-8');

// Utiliser la config centralisée
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>🔧 Migration Base de Données - Version 2</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        h1, h2 { color: #0ff; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        pre { background: #000; padding: 15px; border-radius: 8px; overflow: auto; }
        button { background: #4CAF50; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 20px 0; }
        button:hover { background: #45a049; }
        table { border-collapse: collapse; width: 100%; background: #000; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #222; color: #ff0; }
    </style>
</head>
<body>";

echo "<h1>🔧 Migration de la Base de Données ARKYL</h1>";

// Afficher les infos de la DB
$dbInfo = getDebugInfo();
echo "<h2>📍 Base de données détectée :</h2>";
echo "<table>
    <tr><th>Info</th><th>Valeur</th></tr>
    <tr><td>Chemin</td><td><strong>{$dbInfo['path']}</strong></td></tr>
    <tr><td>Existe ?</td><td class='" . ($dbInfo['exists'] == 'OUI' ? 'success' : 'error') . "'>{$dbInfo['exists']}</td></tr>
    <tr><td>Taille</td><td>" . number_format($dbInfo['size']) . " octets</td></tr>
    <tr><td>Lecture ?</td><td class='" . ($dbInfo['readable'] == 'OUI' ? 'success' : 'error') . "'>{$dbInfo['readable']}</td></tr>
    <tr><td>Écriture ?</td><td class='" . ($dbInfo['writable'] == 'OUI' ? 'success' : 'error') . "'>{$dbInfo['writable']}</td></tr>
</table>";

if ($dbInfo['exists'] !== 'OUI') {
    echo "<p class='error'>❌ La base de données n'existe pas ! Impossible de continuer.</p>";
    exit;
}

// Vérifier si on doit exécuter la migration
if (!isset($_GET['confirm'])) {
    try {
        $db = getDatabase();
        
        // Vérifier les colonnes actuelles
        echo "<h2>📋 Colonnes actuelles de la table 'artworks' :</h2>";
        $stmt = $db->query("PRAGMA table_info(artworks)");
        $existingColumns = [];
        echo "<table><tr><th>ID</th><th>Nom</th><th>Type</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['name'];
            echo "<tr><td>{$row['cid']}</td><td><strong>{$row['name']}</strong></td><td>{$row['type']}</td></tr>";
        }
        echo "</table>";
        
        // Vérifier quelles colonnes manquent
        $requiredColumns = ['width', 'height', 'depth', 'technique', 'techniqueCustom', 'dimensions'];
        $missingColumns = array_diff($requiredColumns, $existingColumns);
        
        if (empty($missingColumns)) {
            echo "<p class='success'>✅ Toutes les colonnes nécessaires existent déjà !</p>";
            echo "<p>Aucune migration nécessaire.</p>";
        } else {
            echo "<h2>⚠️ Colonnes manquantes qui seront ajoutées :</h2>";
            echo "<ul>";
            foreach ($missingColumns as $col) {
                echo "<li class='warning'><strong>$col</strong></li>";
            }
            echo "</ul>";
            
            echo "<h2>📋 Détails des colonnes à ajouter :</h2>";
            echo "<table>
                <tr><th>Colonne</th><th>Type</th><th>Description</th></tr>
                <tr><td>width</td><td>REAL</td><td>Largeur en cm</td></tr>
                <tr><td>height</td><td>REAL</td><td>Hauteur en cm</td></tr>
                <tr><td>depth</td><td>REAL</td><td>Profondeur en cm</td></tr>
                <tr><td>technique</td><td>TEXT</td><td>Technique artistique</td></tr>
                <tr><td>techniqueCustom</td><td>TEXT</td><td>Technique personnalisée</td></tr>
                <tr><td>dimensions</td><td>TEXT</td><td>JSON des dimensions</td></tr>
            </table>";
            
            echo "<form method='GET'>
                <input type='hidden' name='confirm' value='yes'>
                <button type='submit'>✅ Lancer la migration</button>
            </form>";
        }
        
    } catch (Exception $e) {
        echo "<pre class='error'>❌ ERREUR:\n" . $e->getMessage() . "</pre>";
    }
    
} else {
    // EXÉCUTER LA MIGRATION
    echo "<h2>🚀 Exécution de la migration...</h2>";
    
    try {
        $db = getDatabase();
        
        // Liste des colonnes à ajouter
        $columnsToAdd = [
            'width' => 'REAL',
            'height' => 'REAL',
            'depth' => 'REAL',
            'technique' => 'TEXT',
            'techniqueCustom' => 'TEXT',
            'dimensions' => 'TEXT'
        ];
        
        // Vérifier quelles colonnes existent déjà
        $stmt = $db->query("PRAGMA table_info(artworks)");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['name'];
        }
        
        $added = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($columnsToAdd as $columnName => $columnType) {
            if (in_array($columnName, $existingColumns)) {
                echo "<p class='warning'>⏭️ Colonne '$columnName' existe déjà - ignorée</p>";
                $skipped++;
            } else {
                try {
                    $sql = "ALTER TABLE artworks ADD COLUMN $columnName $columnType";
                    $db->exec($sql);
                    echo "<p class='success'>✅ Colonne '$columnName' ajoutée avec succès ($columnType)</p>";
                    $added++;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    echo "<p class='error'>❌ Erreur ajout '$columnName': $errorMsg</p>";
                    $errors[] = "$columnName: $errorMsg";
                }
            }
        }
        
        echo "<hr>";
        echo "<h2>📊 Résumé de la migration :</h2>";
        echo "<ul>";
        echo "<li class='success'>✅ Colonnes ajoutées : <strong>$added</strong></li>";
        echo "<li class='warning'>⏭️ Colonnes déjà existantes : <strong>$skipped</strong></li>";
        if (!empty($errors)) {
            echo "<li class='error'>❌ Erreurs : <strong>" . count($errors) . "</strong></li>";
        }
        echo "</ul>";
        
        // Vérification finale
        echo "<h2>🔍 Vérification finale :</h2>";
        $stmt = $db->query("PRAGMA table_info(artworks)");
        $allColumns = [];
        echo "<table><tr><th>ID</th><th>Nom</th><th>Type</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allColumns[] = $row['name'];
            $isNew = in_array($row['name'], array_keys($columnsToAdd));
            $class = $isNew ? 'success' : '';
            echo "<tr class='$class'><td>{$row['cid']}</td><td><strong>{$row['name']}</strong></td><td>{$row['type']}</td></tr>";
        }
        echo "</table>";
        
        echo "<p>Total de colonnes : <strong>" . count($allColumns) . "</strong></p>";
        
        if ($added > 0 || $skipped == count($columnsToAdd)) {
            echo "<p class='success'>✅ Migration terminée avec succès !</p>";
            
            echo "<hr>";
            echo "<h2>📝 Prochaines étapes :</h2>";
            echo "<ol>
                <li><strong>Teste l'API :</strong> 
                    <a href='/api_galerie_publique.php?artwork_id=1' target='_blank' style='color:#0ff;'>
                        /api_galerie_publique.php?artwork_id=1
                    </a>
                </li>
                <li>Les artistes peuvent maintenant éditer leurs œuvres pour ajouter :
                    <ul>
                        <li>Dimensions (largeur, hauteur, profondeur)</li>
                        <li>Technique</li>
                    </ul>
                </li>
                <li class='warning'>⚠️ <strong>IMPORTANT : Supprime ce fichier (migrate_db.php) de ton serveur !</strong></li>
            </ol>";
        }
        
    } catch (Exception $e) {
        echo "<pre class='error'>❌ ERREUR CRITIQUE:\n" . $e->getMessage() . "\n\nStack:\n" . $e->getTraceAsString() . "</pre>";
    }
}

echo "</body></html>";
?>
