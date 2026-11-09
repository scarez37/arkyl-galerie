<?php
// Page d'accueil servie par le serveur PHP (page de retour après paiement Stripe,
// retour d'inscription artiste…). Elle sert la version À JOUR du site : index.html.
// (Avant, elle assemblait 3 anciens fichiers partiels, désormais supprimés.)
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
