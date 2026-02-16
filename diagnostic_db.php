<?php
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>🔍 Diagnostic Base de Données</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        h1, h2, h3 { color: #0ff; }
        table { border-collapse: collapse; width: 100%; background: #000; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #222; color: #ff0; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        pre { background: #000; padding: 15px; border-radius: 8px; overflow: auto; }
    </style>
</head>
<body>";

echo "<h1>🔍 Diagnostic de la Base de Données ARKYL</h1>";

try {
    // Chercher la base de données
    $possiblePaths = [
        '/opt/render/project/src/galerie.db',
        __DIR__ . '/galerie.db',
        '/opt/render/project/src/artgallery.db',
        __DIR__ . '/artgallery.db'
    ];
    
    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        echo "<p class='error'>❌ Base de données introuvable !</p>";
        echo "<p>Chemins testés :</p><ul>";
        foreach ($possiblePaths as $path) {
            echo "<li>$path</li>";
        }
        echo "</ul>";
        exit;
    }

    echo "<p class='success'>✅ Base de données trouvée : <strong>$dbPath</strong></p>";

    // Connexion
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===== 1. STRUCTURE DE LA TABLE =====
    echo "<h2>📋 Structure de la table 'artworks'</h2>";
    $stmt = $db->query("PRAGMA table_info(artworks)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Nom de la colonne</th>
            <th>Type</th>
            <th>Non NULL ?</th>
            <th>Valeur par défaut</th>
        </tr>";
    
    $columnNames = [];
    foreach ($columns as $col) {
        $columnNames[] = $col['name'];
        echo "<tr>
            <td>{$col['cid']}</td>
            <td><strong>{$col['name']}</strong></td>
            <td>{$col['type']}</td>
            <td>" . ($col['notnull'] ? 'Oui' : 'Non') . "</td>
            <td>" . ($col['dflt_value'] ?? 'NULL') . "</td>
        </tr>";
    }
    echo "</table>";

    // ===== 2. VÉRIFIER LES COLONNES CRITIQUES =====
    echo "<h2>🔎 Vérification des colonnes critiques</h2>";
    $requiredColumns = ['width', 'height', 'depth', 'technique', 'techniqueCustom', 'dimensions'];
    
    echo "<ul>";
    foreach ($requiredColumns as $reqCol) {
        if (in_array($reqCol, $columnNames)) {
            echo "<li class='success'>✅ Colonne '<strong>$reqCol</strong>' existe</li>";
        } else {
            echo "<li class='error'>❌ Colonne '<strong>$reqCol</strong>' MANQUANTE !</li>";
        }
    }
    echo "</ul>";

    // ===== 3. AFFICHER QUELQUES ŒUVRES =====
    echo "<h2>🎨 Échantillon de 5 œuvres (données brutes)</h2>";
    $stmt = $db->query("SELECT * FROM artworks LIMIT 5");
    $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($artworks)) {
        echo "<p class='warning'>⚠️ Aucune œuvre dans la base de données</p>";
    } else {
        foreach ($artworks as $artwork) {
            echo "<h3>Œuvre ID: {$artwork['id']} - {$artwork['title']}</h3>";
            echo "<pre>" . json_encode($artwork, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            
            // Analyse spéciale des champs critiques
            echo "<table>
                <tr><th>Champ</th><th>Valeur</th><th>Type</th><th>Vide ?</th></tr>";
            
            $criticalFields = ['width', 'height', 'depth', 'technique', 'techniqueCustom', 'dimensions', 'description', 'category', 'price'];
            foreach ($criticalFields as $field) {
                $value = $artwork[$field] ?? 'N/A';
                $type = gettype($value);
                $isEmpty = empty($value) ? '❌ OUI' : '✅ NON';
                $displayValue = is_string($value) ? htmlspecialchars(substr($value, 0, 100)) : json_encode($value);
                
                echo "<tr>
                    <td><strong>$field</strong></td>
                    <td>$displayValue</td>
                    <td>$type</td>
                    <td>$isEmpty</td>
                </tr>";
            }
            echo "</table>";
            echo "<hr>";
        }
    }

    // ===== 4. STATISTIQUES =====
    echo "<h2>📊 Statistiques</h2>";
    $stats = [
        'Total œuvres' => $db->query("SELECT COUNT(*) FROM artworks")->fetchColumn(),
        'Œuvres avec width' => $db->query("SELECT COUNT(*) FROM artworks WHERE width IS NOT NULL AND width != ''")->fetchColumn(),
        'Œuvres avec height' => $db->query("SELECT COUNT(*) FROM artworks WHERE height IS NOT NULL AND height != ''")->fetchColumn(),
        'Œuvres avec depth' => $db->query("SELECT COUNT(*) FROM artworks WHERE depth IS NOT NULL AND depth != ''")->fetchColumn(),
        'Œuvres avec technique' => $db->query("SELECT COUNT(*) FROM artworks WHERE technique IS NOT NULL AND technique != ''")->fetchColumn(),
    ];
    
    echo "<table>
        <tr><th>Métrique</th><th>Valeur</th></tr>";
    foreach ($stats as $label => $value) {
        echo "<tr><td>$label</td><td><strong>$value</strong></td></tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<pre class='error'>❌ ERREUR:\n" . $e->getMessage() . "\n\nStack:\n" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>
