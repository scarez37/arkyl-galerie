<?php
// ==========================================
// 1. L'EN-TÊTE ET L'ACCUEIL (Tes nouveaux fichiers)
// ==========================================
include('intro.html');   // Ton animation de départ avec le logo
include('header.html');  // Ta barre de navigation
include('hero.html');    // Ton introduction "L'Art Africain Réinventé"

// ==========================================
// 2. LE CŒUR DE L'APPLICATION (Tes fichiers d'avant)
// ==========================================
// C'est ici qu'on charge ton vrai contenu ARKYL (News Ticker, Filtres, Galerie...)
include('2-pages-content.html'); 

// ==========================================
// 3. L'ADMINISTRATION ET LES SCRIPTS (Tes fichiers d'avant)
// ==========================================
// C'est ici qu'on charge les modales cachées de l'artiste et le script app.js
include('3-admin-modales.html');

// ==========================================
// 4. LE PIED DE PAGE (Ton nouveau fichier)
// ==========================================
include('footer.html');
?>
