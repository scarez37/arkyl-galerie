<?php
echo "<h1>Les outils de ton serveur :</h1>";
$drivers = PDO::getAvailableDrivers();
if (empty($drivers)) {
    echo "<p>Aucun driver PDO installé.</p>";
} else {
    foreach ($drivers as $driver) {
        echo "<p>✅ " . $driver . "</p>";
    }
}
?>
