# Corrections de sécurité et de fiabilité

Ce document récapitule ce qui a été corrigé lors de l'audit, ce qui a été testé, et ce qu'il **reste à faire de votre côté**.

---

## 1. Authentification et droits d'accès

**Avant** : la connexion renvoyait juste un `user_id`, et chaque API croyait l'identifiant, le rôle ou le `?admin=1` envoyés par le navigateur. N'importe qui pouvait supprimer des œuvres, valider des virements aux artistes, lire toutes les commandes (noms, e-mails, adresses) ou créer des commandes sans payer.

**Maintenant** : à la connexion, le **serveur** délivre un jeton signé (`auth_helper.php`, HMAC-SHA256, 30 jours). Le navigateur le renvoie automatiquement (`api-client.js`) et le serveur en déduit qui est l'utilisateur.

| Action | Qui peut la faire |
|---|---|
| Trésorerie (`api_admin_tresorerie.php`), libérer les fonds, interrompre une livraison, historique des virements | **Administrateur** uniquement |
| Publier / modifier / supprimer une œuvre, modifier un profil, poster | **Le propriétaire** (ou l'administrateur) |
| Marquer une commande expédiée / refusée | **L'artiste** de cette commande (ou l'admin) |
| Confirmer la réception | **L'acheteur** de cette commande (ou l'admin) |
| Voir ses commandes, notifications, panier, favoris | **Soi-même** uniquement |
| Créer une commande | **Impossible sans paiement** : le serveur vérifie auprès de Stripe que la session est payée |
| Actualités (ajout / modification / suppression) | **Administrateur** uniquement |
| `?admin=1` | Ignoré sauf pour un vrai administrateur |

- **Administrateur** = e-mail Google **vérifié par Google** présent dans `ADMIN_EMAILS` (ou rôle `admin` en base pour un compte à mot de passe). Il n'est plus décidé par le navigateur.
- **Connexion Google** : le jeton Google est maintenant vérifié **côté serveur** (`auth.php?action=google_session`) : signature, application, expiration, e-mail vérifié. Avant, le navigateur décodait le jeton sans rien vérifier.
- **`auth.php?action=login_google`** (qui connectait n'importe quel compte artiste connaissant son e-mail) exige désormais le jeton de session Google.
- **Inscription artiste** : liée à l'e-mail Google **vérifié** (plus celui saisi), mot de passe ≥ 8 caractères.
- **Anti force brute** : au-delà de 10 échecs par e-mail (40 par IP) en 15 min → erreur 429. Message identique que le compte existe ou non.
- **Visiteurs non connectés** : leur panier / favoris (identifiants `user_…` / `guest_…`) continuent de fonctionner.

## 2. Scripts exposés — supprimés

`setup_db.php`, `fix_orders_table.php`, `create_tables.php`, `creer_table_adresses.php`, `creer_artiste.php` (protégés par `?secret=arkyl2026`, un mot de passe lisible dans le dépôt, ou pas protégés). Remplacés par **`supabase/schema.sql`** (à exécuter une fois dans l'éditeur SQL de Supabase, non accessible depuis le site).

## 3. Bugs corrigés

| Problème | Correction |
|---|---|
| Connexion / inscription incompatibles (`password` vs `password_hash`) | Les deux colonnes sont lues ; le schéma unifie sur `password_hash` |
| 5 fichiers sur l'ancienne base SQLite (`api.php`, `api_get_cart_count.php`, `api_publier_oeuvre.php`, `creer_artiste.php`, `get_stats.php` — ce dernier avec une injection SQL) | Supprimés ; `api_get_cart_count.php` réécrit en PostgreSQL |
| `api_interactions.php` et `api_save_address.php` incluaient des fichiers **inexistants** (`config_mysql.php`, `db.php`) → plantage | Réécrits en PostgreSQL |
| Likes de posts : `MAX(0, …)` (SQLite) → erreur PostgreSQL ; `delta` arbitraire | `GREATEST`, et +1/−1 uniquement |
| Création de table favoris avec `DATETIME` (invalide en PostgreSQL) | `TIMESTAMPTZ` |
| **XSS** (code injecté via titre d'œuvre, nom d'artiste, description, image, bio, commentaire…) | plus de 300 insertions échappées dans `app.js` (`escapeHtml`, `escapeJsAttr`, `safeUrl`) + page galerie publique. Vérifié en navigateur : l'ancien code exécute le script piégé, le nouveau non |
| `router.php` : `include` construit depuis l'URL (remontée de dossiers possible) | `realpath` + liste de fichiers interdits ; `.htaccess` équivalent pour Apache |
| Erreurs techniques (fichier, ligne, SQL, hôte de base) renvoyées au navigateur | Journalisées côté serveur ; message générique renvoyé |
| CORS ouvert à tous (`*`, avec `credentials`) | Origines autorisées (`ALLOWED_ORIGINS`) |
| Œuvre unique achetable deux fois | Le paiement refuse une œuvre déjà vendue |
| Quantités du panier, `delta` des likes, frais de port : valeurs du navigateur non bornées | Bornées |
| Profil public : e-mail et téléphone des artistes visibles de tous | Visibles seulement par l'artiste lui-même et l'admin |
| Mise à jour de profil « par e-mail » pouvait écraser le profil d'un autre artiste | Réécrite : seul le propriétaire modifie sa ligne |
| Condition toujours vraie `emailMatch \|\| existingArtistAccount` (`app.js`) | Simplifiée (la comparaison ne servait à rien) |
| Deux gestionnaires de webhook Stripe dupliqués (copier-coller) | Une seule logique partagée : `stripe_order_helper.php` (idempotente) |

## 4. Fichiers obsolètes supprimés

`1-header-nav.html`, `2-pages-content.html`, `3-admin-modales.html` (anciennes versions du site, ≈ 1,9 Mo), `sections.html` (maquette vide), `login.html` / `register.html` (doublons de `connexion.html` / `inscription.html`), `version.txt`, `generate-icons.php`.
`index.php` (page de retour après paiement Stripe et après inscription) sert maintenant **`index.html`**, la version à jour.

## 5. Déploiement

- `Dockerfile` : composer épinglé, `.htaccess` actif, port dynamique sans effet de bord, PHP sans affichage d'erreurs.
- `render.yaml` : passe en `runtime: docker` et déclare les variables d'environnement (à ma connaissance, `env: php` n'est pas un environnement proposé par Render ; Docker est la voie habituelle pour PHP — à vérifier à la première synchronisation).
- PWA : vraies icônes 192/512 px (le site devient installable), service worker `arkyl-v3`.
- Ajout de `.gitignore`, `.dockerignore`, `.env.example`, `README.md`.

## 6. Tests réalisés

- 108 tests automatisés de l'API (serveur PHP réel + PostgreSQL 16 + simulateur Google) : tout ce qui est sensible est refusé sans connexion ; un utilisateur ne peut ni voir ni modifier les données d'un autre ; jetons falsifiés / expirés / `alg=none` refusés ; CORS ; fichiers internes inaccessibles ; anti force brute.
- Paiement et création de commande avec une bibliothèque Stripe **simulée** : œuvre déjà vendue refusée ; commande refusée si la session n'est pas payée ou si l'acheteur est un autre compte ; création, idempotence (2 appels = 1 commande), œuvre marquée vendue, panier vidé.
- Navigateur (Chromium) : les 6 pages se chargent sans erreur JavaScript ; le jeton n'est envoyé qu'à l'API du site (jamais à un site tiers) ; test XSS avant/après.
- Migration : script testé entre deux bases PostgreSQL (copie, compteurs d'identifiants, comparaison des lignes, refus d'écraser).

**Non testé** (impossible dans l'environnement de travail) : construction de l'image Docker, déploiement réel Render / Vercel / Supabase, vrais paiements Stripe, vraie connexion Google (simulée).

---

## 7. À faire de VOTRE côté

1. **Clés Stripe — non traité, à votre demande.** Une clé secrète de test (`sk_test_…`) et le secret de webhook (`whsec_…`) sont **toujours écrits dans le code** (`api_stripe_checkout.php`, `api_commandes.php`, `webhook_stripe.php`) et visibles sur GitHub (historique inclus). À faire : *régénérer* les deux dans le tableau de bord Stripe, les placer dans `STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` chez l'hébergeur, puis supprimer les valeurs de secours dans ces 3 fichiers.
2. **Définir** `AUTH_SECRET`, `ADMIN_EMAILS` et `SUPABASE_DB_URL` chez l'hébergeur (voir `docs/MIGRATION_SUPABASE.md`).
3. **Les personnes déjà connectées** devront se reconnecter une fois.
4. **E-mails de commande aux artistes** : ils utilisent la fonction `mail()` de PHP, qui nécessite un serveur de messagerie absent des conteneurs Docker standard. Pour être sûr qu'ils partent, branchez un service SMTP (Brevo, Resend, Mailjet…).

## 8. Limites connues / pistes

- **Frais de livraison** : calculés dans le navigateur ; le serveur les borne mais ne les recalcule pas.
- **Images** : photos, avatars et preuves d'expédition sont stockées **dans la base** (texte base64). Sur Supabase gratuit (500 Mo), la base grossira vite : à terme, utiliser **Supabase Storage**.
- **Likes de posts** : un utilisateur connecté peut aimer plusieurs fois (pas de suivi par personne).
- **Création de tables à chaque requête** : de nombreux endpoints exécutent encore `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE …`. Inoffensif depuis `schema.sql`, mais inutile : à retirer progressivement.
- Le mot de passe `arkyl2026` reste visible dans l'**historique** git des scripts supprimés ; sans danger puisque les scripts n'existent plus.
