<?php
/**
 * DIAGNOSTIC BASE DE DONNÉES
 * Affiche les dernières œuvres ajoutées avec TOUTES leurs données
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic BDD - Dernières œuvres</title>
    <style>
        body {
            font-family: monospace;
            background: #1a1a1a;
            color: #0f0;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 { color: #0ff; text-shadow: 0 0 10px #0ff; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #000;
        }
        th, td {
            border: 1px solid #0f0;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #003300;
            font-weight: bold;
        }
        .image-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
        }
        pre {
            background: #000;
            padding: 10px;
            border-left: 3px solid #0f0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 DIAGNOSTIC BASE DE DONNÉES - Dernières œuvres</h1>
    
    <?php
    try {
        // Connexion
        $db = new SQLite3('artgallery.db');
        $db->busyTimeout(5000);
        
        echo '<h2 class="success">✅ Connexion réussie</h2>';
        
        // Compter les œuvres
        $countResult = $db->query("SELECT COUNT(*) as total FROM artworks");
        $countRow = $countResult->fetchArray(SQLITE3_ASSOC);
        echo '<p><strong>Total d\'œuvres dans la base :</strong> ' . $countRow['total'] . '</p>';
        
        // Récupérer les 5 dernières œuvres
        $query = "SELECT 
            id, 
            title, 
            artist_id,
            category, 
            price, 
            description,
            technique,
            badge,
            status,
            created_at,
            SUBSTR(image_url, 1, 50) as image_preview
        FROM artworks 
        ORDER BY id DESC 
        LIMIT 5";
        
        $result = $db->query($query);
        
        echo '<h2>📋 Les 5 dernières œuvres ajoutées :</h2>';
        echo '<table>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Titre</th>';
        echo '<th>Artist ID</th>';
        echo '<th>Catégorie</th>';
        echo '<th>Prix</th>';
        echo '<th>Description</th>';
        echo '<th>Technique</th>';
        echo '<th>Badge</th>';
        echo '<th>Status</th>';
        echo '<th>Date</th>';
        echo '<th>Image (preview)</th>';
        echo '</tr>';
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . ($row['title'] ?? '<span class="error">NULL</span>') . '</td>';
            echo '<td>' . ($row['artist_id'] ?? '<span class="error">NULL</span>') . '</td>';
            echo '<td>' . ($row['category'] ?? '<span class="error">NULL</span>') . '</td>';
            echo '<td>' . ($row['price'] ?? '<span class="error">NULL</span>') . ' FCFA</td>';
            echo '<td>' . (substr($row['description'] ?? 'NULL', 0, 50)) . '...</td>';
            echo '<td>' . ($row['technique'] ?? '<span class="warning">NULL</span>') . '</td>';
            echo '<td>' . ($row['badge'] ?? '<span class="warning">NULL</span>') . '</td>';
            echo '<td>' . ($row['status'] ?? '<span class="error">NULL</span>') . '</td>';
            echo '<td>' . ($row['created_at'] ?? '<span class="error">NULL</span>') . '</td>';
            echo '<td>' . ($row['image_preview'] ?? '<span class="error">NULL</span>') . '...</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // Test de l'API
        echo '<h2>🌐 Test de l\'API Galerie Publique</h2>';
        echo '<p>Cliquez pour tester : <a href="api_galerie_publique.php" target="_blank" style="color:#0ff;">api_galerie_publique.php</a></p>';
        
        $db->close();
        
    } catch (Exception $e) {
        echo '<p class="error">❌ ERREUR : ' . $e->getMessage() . '</p>';
    }
    ?>
    
</body>
</html>
