
ARKYL — galerie d'art africain
Galerie en ligne : les artistes publient leurs œuvres, les acheteurs commandent (paiement par carte via Stripe, fonds conservés en séquestre jusqu'à la réception).

Architecture
Élément	Technologie
Site (front)	HTML / CSS / JavaScript (index.html, app.js, style.css), PWA installable
API	PHP 8 (api_*.php, auth.php, webhook_stripe.php)
Base de données	PostgreSQL hébergé chez Supabase (supabase/schema.sql)
Paiement	Stripe Checkout + webhook
Connexion	Google Sign-In vérifié par le serveur + mot de passe artiste, jeton signé (auth_helper.php)
Hébergement	API : Render / Railway (Docker) · Front : Vercel (réécrit les .php vers l'API)
Variables d'environnement
Voir .env.example. Les principales : SUPABASE_DB_URL, AUTH_SECRET, ADMIN_EMAILS, STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, APP_URL, ALLOWED_ORIGINS.

Ne mettez jamais de mot de passe, de clé ou de jeton dans le code ni sur GitHub.

Mettre en route
Migrer / créer la base : docs/MIGRATION_SUPABASE.md
Local (avec PHP installé) :
composer install
export SUPABASE_DB_URL='postgresql://…'  AUTH_SECRET="$(openssl rand -hex 32)"  ARKYL_DEV=1
php -S 127.0.0.1:8000 router.php
Docker : docker build -t arkyl . && docker run -p 8080:80 -e SUPABASE_DB_URL=… -e AUTH_SECRET=… arkyl
Sécurité (résumé)
Les droits (administrateur, artiste, propriétaire d'une commande/œuvre) sont décidés par le serveur à partir d'un jeton signé — jamais par ce qu'envoie le navigateur.
Les administrateurs sont les e-mails Google vérifiés listés dans ADMIN_EMAILS.
Tous les textes saisis par les utilisateurs sont échappés avant affichage (escapeHtml dans app.js).
CORS limité aux origines autorisées ; aucune erreur technique n'est renvoyée au navigateur.
Les tables Supabase sont verrouillées (RLS) : seul le serveur PHP y accède.
Détail des corrections : docs/CORRECTIONS_SECURITE.md.

