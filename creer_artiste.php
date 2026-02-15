<?php
/**
 * CRÉER UN ARTISTE DE TEST
 * Pour pouvoir publier des œuvres
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $db = new SQLite3('artgallery.db');
    $db->busyTimeout(5000);
    
    // Vérifier combien d'artistes existent
    $countQuery = "SELECT COUNT(*) as total FROM artists";
    $result = $db->query($countQuery);
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $existingArtists = $row['total'];
    
    echo "<h1>🎨 Gestion des artistes</h1>";
    echo "<p>Artistes existants : <strong>$existingArtists</strong></p>";
    
    // Lister les artistes
    echo "<h2>📋 Liste des artistes :</h2>";
    $listQuery = "SELECT id, name, artist_name, email FROM artists";
    $listResult = $db->query($listQuery);
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Nom d'artiste</th><th>Email</th></tr>";
    
    while ($artist = $listResult->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr>";
        echo "<td>{$artist['id']}</td>";
        echo "<td>{$artist['name']}</td>";
        echo "<td>" . ($artist['artist_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($artist['email'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Formulaire pour créer un artiste
    if (isset($_POST['create_artist'])) {
        $name = $_POST['name'];
        $artist_name = $_POST['artist_name'];
        $email = $_POST['email'];
        $country = $_POST['country'] ?? 'Côte d\'Ivoire';
        
        $insertQuery = "INSERT INTO artists (name, artist_name, email, country) VALUES (:name, :artist_name, :email, :country)";
        $stmt = $db->prepare($insertQuery);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':artist_name', $artist_name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':country', $country, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            $newId = $db->lastInsertRowID();
            echo "<div style='background: #d4ffd4; padding: 20px; margin: 20px 0; border: 2px solid green;'>";
            echo "<h3>✅ Artiste créé avec succès !</h3>";
            echo "<p><strong>ID : $newId</strong></p>";
            echo "<p><strong>Utilisez cet ID dans votre localStorage :</strong></p>";
            echo "<pre>localStorage.setItem('user_id', '$newId');</pre>";
            echo "<p>ou</p>";
            echo "<pre>currentUser = { id: $newId, name: '$name' };</pre>";
            echo "</div>";
            
            // Recharger pour afficher le nouvel artiste
            echo "<meta http-equiv='refresh' content='2'>";
        } else {
            echo "<p style='color: red;'>❌ Erreur : " . $db->lastErrorMsg() . "</p>";
        }
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un artiste</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #d4a574;
            color: white;
        }
        .form-box {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #d4a574;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #c49565;
        }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>➕ Créer un nouvel artiste</h2>
        <form method="POST">
            <label>Nom complet :</label>
            <input type="text" name="name" required placeholder="Ex: Cédric Kouassi">
            
            <label>Nom d'artiste :</label>
            <input type="text" name="artist_name" required placeholder="Ex: Cedric">
            
            <label>Email :</label>
            <input type="email" name="email" required placeholder="Ex: cedric@example.com">
            
            <label>Pays :</label>
            <input type="text" name="country" value="Côte d'Ivoire">
            
            <button type="submit" name="create_artist">Créer l'artiste</button>
        </form>
    </div>
</body>
</html>
