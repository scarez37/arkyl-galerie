<?php
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>🗑️ Nettoyage Bases de Données</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        h1, h2 { color: #0ff; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        table { border-collapse: collapse; width: 100%; background: #000; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #222; color: #ff0; }
        button { background: #4CAF50; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .danger { background: #f44336; }
        .danger:hover { background: #da190b; }
        input[type='radio'] { transform: scale(1.5); margin-right: 10px; }
        label { cursor: pointer; display: block; padding: 10px; margin: 5px 0; background: #222; border-radius: 5px; }
        label:hover { background: #333; }
    </style>
</head>
<body>";

echo "<h1>🗑️ Nettoyage des Bases de Données</h1>";
echo "<p class='warning'>⚠️ Nous allons trouver toutes les bases, en garder UNE, et supprimer les autres.</p>";

// Chercher TOUTES les bases possibles
$searchPaths = [
    '/var/www/html',
    '/opt/render/project/src',
    __DIR__,
];

$foundDatabases = [];

foreach ($searchPaths as $dir) {
    if (!is_dir($dir)) continue;
    
    // Chercher galerie.db et artgallery.db
    foreach (['galerie.db', 'artgallery.db'] as $dbFile) {
        $fullPath = $dir . '/' . $dbFile;
        if (file_exists($fullPath)) {
            try {
                $db = new PDO('sqlite:' . $fullPath);
                $stmt = $db->query("SELECT COUNT(*) FROM artworks");
                $count = $stmt->fetchColumn();
                
                // Vérifier les colonnes
                $stmt2 = $db->query("PRAGMA table_info(artworks)");
                $columns = [];
                while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = $row['name'];
                }
                
                $hasNewColumns = in_array('width', $columns) && in_array('technique', $columns);
                
                $foundDatabases[] = [
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'artworks_count' => $count,
                    'columns_count' => count($columns),
                    'has_new_columns' => $hasNewColumns,
                    'writable' => is_writable($fullPath)
                ];
            } catch (Exception $e) {
                $foundDatabases[] = [
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'artworks_count' => 'ERREUR',
                    'columns_count' => 0,
                    'has_new_columns' => false,
                    'writable' => is_writable($fullPath)
                ];
            }
        }
    }
}

if (count($foundDatabases) == 0) {
    echo "<p class='error'>❌ Aucune base de données trouvée !</p>";
    exit;
}

if (count($foundDatabases) == 1) {
    echo "<p class='success'>✅ Une seule base de données trouvée - pas besoin de nettoyage !</p>";
    echo "<p><strong>{$foundDatabases[0]['path']}</strong></p>";
    echo "<p>Contient : {$foundDatabases[0]['artworks_count']} œuvres</p>";
    exit;
}

echo "<h2>📋 Bases de données trouvées : " . count($foundDatabases) . "</h2>";

if (!isset($_POST['keep_db'])) {
    // Afficher le formulaire de choix
    echo "<form method='POST'>";
    echo "<table>
        <tr>
            <th>Garder ?</th>
            <th>Chemin</th>
            <th>Taille</th>
            <th>Nb œuvres</th>
            <th>Nb colonnes</th>
            <th>Nouvelles colonnes ?</th>
            <th>Écriture ?</th>
        </tr>";
    
    $recommended = null;
    $maxScore = -1;
    
    foreach ($foundDatabases as $i => $dbInfo) {
        // Score : nombre d'œuvres + bonus si nouvelles colonnes
        $score = is_numeric($dbInfo['artworks_count']) ? $dbInfo['artworks_count'] : 0;
        if ($dbInfo['has_new_columns']) $score += 1000;
        
        if ($score > $maxScore && $dbInfo['writable']) {
            $maxScore = $score;
            $recommended = $i;
        }
        
        $isRecommended = ($i === $recommended);
        $rowClass = $isRecommended ? "style='background:#004400;'" : "";
        
        echo "<tr $rowClass>
            <td>
                <label>
                    <input type='radio' name='keep_db' value='{$dbInfo['path']}' " . ($isRecommended ? 'checked' : '') . " required>
                </label>
            </td>
            <td><strong>{$dbInfo['path']}</strong>" . ($isRecommended ? " <span class='success'>← RECOMMANDÉE</span>" : "") . "</td>
            <td>" . number_format($dbInfo['size']) . " octets</td>
            <td><strong>{$dbInfo['artworks_count']}</strong></td>
            <td>{$dbInfo['columns_count']}</td>
            <td class='" . ($dbInfo['has_new_columns'] ? 'success' : 'error') . "'>" . ($dbInfo['has_new_columns'] ? '✅ OUI' : '❌ NON') . "</td>
            <td class='" . ($dbInfo['writable'] ? 'success' : 'error') . "'>" . ($dbInfo['writable'] ? '✅ OUI' : '❌ NON') . "</td>
        </tr>";
    }
    echo "</table>";
    
    echo "<h2>⚠️ ATTENTION :</h2>";
    echo "<ul class='warning'>
        <li>La base cochée sera <strong>CONSERVÉE</strong></li>
        <li>Toutes les autres seront <strong>SUPPRIMÉES DÉFINITIVEMENT</strong></li>
        <li>Cette action est <strong>IRRÉVERSIBLE</strong></li>
    </ul>";
    
    echo "<p class='success'>💡 La base recommandée a le plus d'œuvres et/ou les nouvelles colonnes.</p>";
    
    echo "<button type='submit' class='danger'>🗑️ Supprimer les bases non sélectionnées</button>";
    echo "</form>";
    
} else {
    // Exécuter la suppression
    $keepPath = $_POST['keep_db'];
    
    echo "<h2>🚀 Exécution du nettoyage...</h2>";
    echo "<p class='success'>✅ Base à conserver : <strong>$keepPath</strong></p>";
    
    $deleted = 0;
    $errors = [];
    
    foreach ($foundDatabases as $dbInfo) {
        if ($dbInfo['path'] === $keepPath) {
            echo "<p class='success'>✅ Conservée : {$dbInfo['path']}</p>";
        } else {
            if (unlink($dbInfo['path'])) {
                echo "<p class='warning'>🗑️ Supprimée : {$dbInfo['path']}</p>";
                $deleted++;
            } else {
                echo "<p class='error'>❌ Impossible de supprimer : {$dbInfo['path']}</p>";
                $errors[] = $dbInfo['path'];
            }
        }
    }
    
    echo "<hr><h2>📊 Résumé :</h2>";
    echo "<ul>
        <li class='success'>✅ Base conservée : <strong>1</strong></li>
        <li class='warning'>🗑️ Bases supprimées : <strong>$deleted</strong></li>";
    if (!empty($errors)) {
        echo "<li class='error'>❌ Erreurs : <strong>" . count($errors) . "</strong></li>";
    }
    echo "</ul>";
    
    if ($deleted > 0) {
        echo "<p class='success'>✅ Nettoyage terminé ! Il ne reste qu'une seule base de données.</p>";
        
        // Vérifier si la base conservée a les bonnes colonnes
        try {
            $db = new PDO('sqlite:' . $keepPath);
            $stmt = $db->query("PRAGMA table_info(artworks)");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['name'];
            }
            
            $hasWidth = in_array('width', $columns);
            $hasTechnique = in_array('technique', $columns);
            
            echo "<h2>🔍 Vérification de la base conservée :</h2>";
            echo "<ul>";
            echo "<li>Nombre de colonnes : " . count($columns) . "</li>";
            echo "<li class='" . ($hasWidth ? 'success' : 'error') . "'>width : " . ($hasWidth ? '✅ Présente' : '❌ Absente') . "</li>";
            echo "<li class='" . ($hasTechnique ? 'success' : 'error') . "'>technique : " . ($hasTechnique ? '✅ Présente' : '❌ Absente') . "</li>";
            echo "</ul>";
            
            if (!$hasWidth || !$hasTechnique) {
                echo "<p class='warning'>⚠️ Les nouvelles colonnes sont absentes ! Lance maintenant :</p>";
                echo "<p><a href='/migrate_db_v2.php' style='color:#0ff;'>migrate_db_v2.php</a> pour les ajouter.</p>";
            } else {
                echo "<p class='success'>✅ Toutes les colonnes nécessaires sont présentes !</p>";
                
                // Créer db_config.php
                $configContent = "<?php
/**
 * 🔧 CONFIGURATION UNIQUE DE LA BASE DE DONNÉES
 * Généré automatiquement le " . date('Y-m-d H:i:s') . "
 */

define('DB_PATH', '$keepPath');

function getDatabase() {
    \$db = new PDO('sqlite:' . DB_PATH);
    \$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return \$db;
}

function getDatabasePath() {
    return DB_PATH;
}

function getDebugInfo() {
    return [
        'path' => DB_PATH,
        'exists' => file_exists(DB_PATH) ? 'OUI' : 'NON',
        'size' => file_exists(DB_PATH) ? filesize(DB_PATH) : 0,
        'readable' => is_readable(DB_PATH) ? 'OUI' : 'NON',
        'writable' => is_writable(DB_PATH) ? 'OUI' : 'NON'
    ];
}
?>";
                
                file_put_contents(__DIR__ . '/db_config.php', $configContent);
                echo "<p class='success'>✅ Fichier db_config.php créé/mis à jour !</p>";
                
                echo "<h2>📝 Prochaines étapes :</h2>";
                echo "<ol>
                    <li><strong>Teste l'API :</strong> <a href='/api_galerie_publique.php?artwork_id=1' style='color:#0ff;'>Cliquer ici</a></li>
                    <li><strong>Teste le remplissage :</strong> <a href='/test_quick.php' style='color:#0ff;'>Cliquer ici</a></li>
                    <li class='warning'>⚠️ <strong>Supprime ce fichier :</strong> cleanup_db.php</li>
                </ol>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erreur vérification : " . $e->getMessage() . "</p>";
        }
    }
}

echo "</body></html>";
?>
