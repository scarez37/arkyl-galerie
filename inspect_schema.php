<?php
// inspect_schema.php
// Affiche la liste exacte des colonnes de la table 'artworks'
header('Content-Type: text/html; charset=utf-8');

try {
    $db = new SQLite3('artgallery.db');
    
    echo "<h1>📋 Structure de la table 'artworks'</h1>";
    
    $result = $db->query("PRAGMA table_info(artworks)");
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Nom de la colonne</th><th>Type</th></tr>";
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr>";
        echo "<td><b>" . $row['name'] . "</b></td>"; // C'est ici qu'on verra le vrai nom
        echo "<td>" . $row['type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // On regarde aussi la table artists pour être sûr
    echo "<h1>📋 Structure de la table 'artists'</h1>";
    $result2 = $db->query("PRAGMA table_info(artists)");
    while ($row = $result2->fetchArray(SQLITE3_ASSOC)) {
        echo "<li>" . $row['name'] . "</li>";
    }

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
