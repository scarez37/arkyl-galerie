<?php
// 👉 REMPLACE LE TEXTE CI-DESSOUS PAR TON LIEN COPIÉ SUR RENDER :
$url = "postgresql://arkyl_db_user:196PXpwGBH4Qr31JPyyeog0S1hn04XVs@dpg-d69ptr7gi27c73clct3g-a/arkyl_db";

try {
    // 1. Décodage du lien Render
    $dbopts = parse_url($url);
    $dsn = "pgsql:host=" . $dbopts["host"] . ";port=5432;dbname=" . ltrim($dbopts["path"], '/');
    
    // 2. Connexion à PostgreSQL
    $db = new PDO($dsn, $dbopts["user"], $dbopts["pass"]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Création du tiroir des ARTISTES
    $db->exec("CREATE TABLE IF NOT EXISTS artists (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255),
        artist_name VARCHAR(255),
        email VARCHAR(255) UNIQUE,
        password TEXT,
        country VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Création du tiroir des ŒUVRES
    $db->exec("CREATE TABLE IF NOT EXISTS artworks (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        price DECIMAL(10,2),
        image_url TEXT,
        artist_id INTEGER,
        artist_name VARCHAR(255),
        description TEXT,
        category VARCHAR(100),
        technique VARCHAR(255),
        dimensions TEXT,
        photos TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "<h1>🎉 SUCCÈS TOTAL !</h1>";
    echo "<p>Ta base de données PostgreSQL est prête ! Tous les tiroirs sont installés.</p>";

} catch (Exception $e) {
    echo "<h1>❌ ERREUR</h1>";
    echo "<p>Le serveur dit : " . $e->getMessage() . "</p>";
}
?>
