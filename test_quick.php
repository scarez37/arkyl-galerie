<?php
header('Content-Type: text/html; charset=utf-8');

// Utiliser la config centralisée
require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>🧪 Test Rapide - Remplir les données</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        h1, h2 { color: #0ff; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        button { background: #4CAF50; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 20px 0; }
        input, select { padding: 10px; margin: 10px 0; width: 300px; font-size: 16px; }
        label { display: block; margin-top: 15px; color: #ff0; }
    </style>
</head>
<body>";

echo "<h1>🧪 Test Rapide - Ajouter dimensions et technique</h1>";
echo "<p class='warning'>⚠️ Ceci est un script de TEST pour vérifier que tout fonctionne.</p>";

if (!isset($_POST['submit'])) {
    // Afficher le formulaire
    try {
        $db = getDatabase();
        
        // Lister les œuvres
        echo "<h2>📋 Œuvres disponibles :</h2>";
        $stmt = $db->query("SELECT id, title, category FROM artworks LIMIT 10");
        $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<form method='POST'>
            <label>Choisir une œuvre à modifier :</label>
            <select name='artwork_id' required>";
        
        foreach ($artworks as $art) {
            echo "<option value='{$art['id']}'>[ID {$art['id']}] {$art['title']} ({$art['category']})</option>";
        }
        
        echo "</select>
        
            <label>Largeur (cm) :</label>
            <input type='number' name='width' value='80' step='0.1' required>
            
            <label>Hauteur (cm) :</label>
            <input type='number' name='height' value='60' step='0.1' required>
            
            <label>Profondeur (cm) :</label>
            <input type='number' name='depth' value='3' step='0.1' required>
            
            <label>Technique :</label>
            <input type='text' name='technique' value='Peinture acrylique' required>
            
            <br><br>
            <button type='submit' name='submit'>✅ Mettre à jour cette œuvre</button>
        </form>";
        
    } catch (Exception $e) {
        echo "<pre class='error'>❌ ERREUR:\n" . $e->getMessage() . "</pre>";
    }
    
} else {
    // Traiter le formulaire
    try {
        $db = getDatabase();
        
        $artworkId = intval($_POST['artwork_id']);
        $width = floatval($_POST['width']);
        $height = floatval($_POST['height']);
        $depth = floatval($_POST['depth']);
        $technique = trim($_POST['technique']);
        
        // Créer le JSON des dimensions
        $dimensionsJson = json_encode([
            'width' => $width,
            'height' => $height,
            'depth' => $depth
        ], JSON_UNESCAPED_UNICODE);
        
        // Mettre à jour la base de données
        $stmt = $db->prepare("
            UPDATE artworks 
            SET width = :width,
                height = :height,
                depth = :depth,
                technique = :technique,
                dimensions = :dimensions
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':width' => $width,
            ':height' => $height,
            ':depth' => $depth,
            ':technique' => $technique,
            ':dimensions' => $dimensionsJson,
            ':id' => $artworkId
        ]);
        
        echo "<p class='success'>✅ Œuvre #$artworkId mise à jour avec succès !</p>";
        
        echo "<h2>📊 Données enregistrées :</h2>";
        echo "<ul>
            <li>Largeur : <strong>$width cm</strong></li>
            <li>Hauteur : <strong>$height cm</strong></li>
            <li>Profondeur : <strong>$depth cm</strong></li>
            <li>Technique : <strong>$technique</strong></li>
        </ul>";
        
        echo "<hr>";
        echo "<h2>🧪 Tests :</h2>";
        echo "<ol>
            <li><strong>Teste l'API :</strong> 
                <a href='/api_galerie_publique.php?artwork_id=$artworkId' target='_blank' style='color:#0ff;'>
                    /api_galerie_publique.php?artwork_id=$artworkId
                </a>
                <br><small class='warning'>Tu devrais voir les dimensions et la technique dans la réponse JSON</small>
            </li>
            <li><strong>Teste sur le site :</strong> 
                <a href='/' target='_blank' style='color:#0ff;'>
                    Ouvrir le site public
                </a>
                <br><small class='warning'>Clique sur l'œuvre - les dimensions et la technique devraient s'afficher !</small>
            </li>
        </ol>";
        
        echo "<br><a href='test_quick.php'><button>← Modifier une autre œuvre</button></a>";
        
        echo "<hr>";
        echo "<p class='warning'>⚠️ Une fois que tu as vérifié que tout marche, <strong>supprime ce fichier (test_quick.php) de ton serveur</strong> !</p>";
        
    } catch (Exception $e) {
        echo "<pre class='error'>❌ ERREUR:\n" . $e->getMessage() . "</pre>";
    }
}

echo "</body></html>";
?>
