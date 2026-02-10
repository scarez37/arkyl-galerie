<?php
try {
    $db = new SQLite3('artgallery.db');
    echo "<html><body><h2>Installation des Stats</h2>";
    
    $db->exec("CREATE TABLE IF NOT EXISTS artwork_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, 
        viewer_ip VARCHAR(45), viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    
    $db->exec("CREATE TABLE IF NOT EXISTS artwork_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, 
        user_id INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(artwork_id, user_id))");
    
    $db->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT, artwork_id INTEGER, artist_id INTEGER, 
        buyer_name VARCHAR(255), buyer_email VARCHAR(255), sale_price DECIMAL(10,2), 
        commission_amount DECIMAL(10,2), artist_revenue DECIMAL(10,2), 
        status VARCHAR(20) DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        
    echo "<h3 style='color:green'>✅ Tables créées avec succès !</h3></body></html>";
} catch (Exception $e) { echo "Erreur : " . $e->getMessage(); }
?>
