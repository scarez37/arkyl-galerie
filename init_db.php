<?php
// Initialisation de la base de données
// Encodage: UTF-8

$db = new SQLite3('artgallery.db');

// Table des artistes
$db->exec('
    CREATE TABLE IF NOT EXISTS artists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        artist_name TEXT NOT NULL,
        portfolio_url TEXT,
        message TEXT,
        status TEXT DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
');

// Table des œuvres
$db->exec('
    CREATE TABLE IF NOT EXISTS artworks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        artist_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        image_url TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT "active",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (artist_id) REFERENCES artists(id)
    )
');

// Insertion de données de test
$db->exec("
    INSERT OR IGNORE INTO artists (id, name, email, artist_name, status) 
    VALUES 
    (1, 'Marie Dubois', 'marie@example.com', 'Marie D.', 'approved'),
    (2, 'Pierre Martin', 'pierre@example.com', 'P. Martin', 'approved')
");

$db->exec("
    INSERT OR IGNORE INTO artworks (artist_id, title, category, price, image_url, description) 
    VALUES 
    (1, 'Aurore Mystique', 'Peinture', 1200.00, 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5', 'Une peinture abstraite aux couleurs vibrantes'),
    (1, 'Echo du Temps', 'Sculpture', 2500.00, 'https://images.unsplash.com/photo-1578301978162-7aae4d755744', 'Sculpture contemporaine en bronze'),
    (2, 'Lumiere Urbaine', 'Photographie', 800.00, 'https://images.unsplash.com/photo-1536924940846-227afb31e2a5', 'Photographie urbaine nocturne'),
    (2, 'Reve Digital', 'Art Numerique', 1500.00, 'https://images.unsplash.com/photo-1541961017774-22349e4a1262', 'Creation numerique surrealiste'),
    (1, 'Horizon Infini', 'Peinture', 1800.00, 'https://images.unsplash.com/photo-1549887534-1541e9326642', 'Paysage abstrait a l huile'),
    (2, 'Forme Abstraite', 'Sculpture', 3000.00, 'https://images.unsplash.com/photo-1547826039-bfc35e0f1ea8', 'Sculpture moderne en acier')
");

echo "✅ Base de données initialisée avec succès!\n";
echo "📊 Tables créées: artists, artworks\n";
echo "🎨 Données de test ajoutées: 2 artistes, 6 œuvres\n";
$db->close();
?>
