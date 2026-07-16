<?php
// Générer les icônes PNG dynamiquement si elles manquent

function createIconPNG($size) {
    $image = imagecreatetruecolor($size, $size);
    $bgColor = imagecolorallocate($image, 10, 10, 10);       // Noir
    $goldColor = imagecolorallocate($image, 212, 175, 55);   // Or
    
    imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);
    
    // Écrire le "A" au centre
    $fontSize = $size / 2;
    $textX = $size / 2;
    $textY = $size / 2 + ($fontSize / 3);
    
    // Utiliser une police système
    $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    if (file_exists($fontPath)) {
        imagettftext($image, $fontSize * 0.6, 0, $textX - ($fontSize / 3), $textY, $goldColor, $fontPath, 'A');
    } else {
        // Fallback si pas de police TrueType
        $textColor = $goldColor;
        imagestring($image, 5, $textX - 10, $textY - 10, 'A', $textColor);
    }
    
    return $image;
}

// Générer favicon-192.png
$icon192 = createIconPNG(192);
imagepng($icon192, __DIR__ . '/favicon-192.png');
imagedestroy($icon192);
echo "✅ favicon-192.png créé\n";

// Générer favicon-32.png
$icon32 = createIconPNG(32);
imagepng($icon32, __DIR__ . '/favicon-32.png');
imagedestroy($icon32);
echo "✅ favicon-32.png créé\n";

// Générer logo-512.png
$icon512 = createIconPNG(512);
imagepng($icon512, __DIR__ . '/logo-512.png');
imagedestroy($icon512);
echo "✅ logo-512.png créé\n";

echo "\n🎉 Tous les fichiers PNG ont été générés!\n";
?>
