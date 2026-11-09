# Migrer ARKYL vers Supabase — guide pas à pas

Ce guide transfère la base de données du site vers **Supabase** (PostgreSQL hébergé).
Le site (PHP) reste où il est (Render / Railway) : seule la **base de données** change de place.

> Durée : 20 à 30 minutes. Prévoir un moment calme : pendant le transfert, les nouvelles commandes ne doivent pas arriver (quelques minutes).

---

## 0. Ce qui change, ce qui ne change pas

| | |
|---|---|
| ✅ Ne change pas | l'adresse du site, Google Sign-In, Stripe, Vercel, les comptes et mots de passe des artistes |
| 🔄 Change | l'endroit où sont stockées les données (Supabase) et **une variable** chez l'hébergeur : `SUPABASE_DB_URL` |
| 🆕 À ajouter | `AUTH_SECRET` (secret de signature des connexions) et `ADMIN_EMAILS` |

---

## 1. Créer le projet Supabase

1. Sur <https://supabase.com> → **New project**.
2. Choisir une région proche de vos visiteurs (ex. *West EU – Paris* ou *Frankfurt*).
3. Choisir un **mot de passe de base de données** solide et le **noter** (il n'est affiché qu'une fois).
   Évitez les caractères `@ : / ? # %` pour simplifier, sinon il faudra les « encoder » (voir plus bas).

## 2. Récupérer l'adresse de connexion

1. Dans le projet : bouton **Connect** (en haut) → onglet **Connection string**.
2. Choisir **Session pooler** (port **5432**). Vous obtenez une adresse de ce type :

   ```
   postgresql://postgres.abcdefghij:[YOUR-PASSWORD]@aws-0-eu-west-3.pooler.supabase.com:5432/postgres
   ```
3. Remplacez `[YOUR-PASSWORD]` par votre mot de passe.

**Pourquoi « Session pooler » ?** L'option « Direct connection » (`db.xxxx.supabase.co`) ne fonctionne qu'en IPv6, ce que Render/Railway ne gèrent pas toujours. Le « Session pooler » marche partout. Le « Transaction pooler » (port 6543) fonctionne aussi : le site désactive alors automatiquement les requêtes préparées.

**Mot de passe avec caractères spéciaux** : remplacez chaque caractère par son code : `@` → `%40`, `:` → `%3A`, `/` → `%2F`, `?` → `%3F`, `#` → `%23`, `%` → `%25`.

## 3. Transférer les données

### Cas A — vous avez encore l'ancienne base (recommandé)

Il vous faut l'adresse **externe** de l'ancienne base (Render : *Dashboard → votre base → Connections → External Database URL*).

Sur votre ordinateur (Mac, Linux, ou Windows via **WSL**), avec les outils PostgreSQL installés (`psql`, `pg_dump`, `pg_restore`) :

```bash
cd arkyl-galerie
OLD_DATABASE_URL='postgresql://ancien_utilisateur:ancien_mdp@ancien-hote:5432/ancienne_base' \
SUPABASE_DB_URL='postgresql://postgres.abcdefghij:MON_MDP@aws-0-eu-west-3.pooler.supabase.com:5432/postgres' \
./scripts/migrate_to_supabase.sh
```

Le script :
1. vérifie les deux connexions ;
2. **sauvegarde** l'ancienne base dans un fichier `backup-ancienne-base-….dump` (gardez-le !) ;
3. copie toutes les tables et données vers Supabase ;
4. applique `supabase/schema.sql` (colonnes manquantes + sécurité) ;
5. **compare** le nombre de lignes de chaque table : tout doit afficher ✅.

L'ancienne base n'est **jamais modifiée**. Si une ligne affiche ❌, ne basculez pas : relancez ou demandez de l'aide avec le message affiché.

> *Message « server version mismatch » :* installez des outils PostgreSQL de version **égale ou supérieure** à celle de l'ancienne base.

### Cas B — l'ancienne base est perdue ou vide (nouvelle installation)

1. Supabase → **SQL Editor** → **New query**.
2. Ouvrez le fichier `supabase/schema.sql`, copiez tout son contenu, collez-le, cliquez **Run**.
3. Les 18 tables sont créées, avec la sécurité activée. Les artistes devront se réinscrire.

> ⚠️ Les bases gratuites de Render **expirent** au bout d'un certain temps. Si la vôtre est encore active, faites le Cas A **sans attendre**.

## 4. Configurer l'hébergeur (Render / Railway)

Dans les **variables d'environnement** du service web :

| Variable | Valeur | Obligatoire |
|---|---|---|
| `SUPABASE_DB_URL` | l'adresse « Session pooler » de l'étape 2 | ✅ |
| `AUTH_SECRET` | une chaîne aléatoire de 32+ caractères. Pour en générer une : `openssl rand -hex 32` (ou un gestionnaire de mots de passe) | ✅ |
| `ADMIN_EMAILS` | votre/vos e-mail(s) Google administrateur(s), séparés par des virgules | ✅ |
| `APP_URL` | l'adresse publique du site, ex. `https://arkyl.site` | recommandé |
| `ALLOWED_ORIGINS` | ex. `https://arkyl.site,https://www.arkyl.site` | recommandé |
| `STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` | vos clés Stripe | ✅ (voir `docs/CORRECTIONS_SECURITE.md`, section 7) |
| `GOOGLE_CLIENT_ID` | seulement si vous changez de client Google | non |

L'ancienne variable `DATABASE_URL` peut rester : `SUPABASE_DB_URL` est lue **en premier**. Supprimez-la quand tout fonctionne.

Redéployez le service.

## 5. Vérifier que tout marche

Faites ce test complet (≈ 5 min) :

- [ ] La page d'accueil s'affiche avec les œuvres.
- [ ] Connexion avec Google → vous voyez votre nom.
- [ ] Avec votre e-mail administrateur : le menu **Admin** apparaît.
- [ ] Un artiste se connecte (mot de passe) et voit ses œuvres.
- [ ] Ajout d'une œuvre au panier, puis paiement test Stripe.
- [ ] La commande apparaît côté artiste **et** côté admin.

> Les personnes déjà connectées avant la mise à jour verront « Votre session a expiré » : il suffit de se **reconnecter** une fois.

## 6. Après la bascule

- Gardez l'ancienne base **7 jours** en lecture seule, puis supprimez-la.
- Conservez le fichier `backup-ancienne-base-….dump` en lieu sûr (ne le mettez **pas** sur GitHub : il contient les e-mails et adresses des clients).
- **Sauvegardes** : la formule gratuite de Supabase ne garde pas de sauvegarde quotidienne téléchargeable. Faites une copie régulière :
  ```bash
  pg_dump "$SUPABASE_DB_URL" --schema=public --format=custom --no-owner -f sauvegarde-$(date +%F).dump
  ```

---

## Sécurité Supabase — ce qu'il faut savoir

- Supabase publie automatiquement chaque table via une **API publique** (clé « anon »). ARKYL ne l'utilise pas : le script `supabase/schema.sql` **verrouille** toutes les tables (RLS activée, droits retirés à `anon` et `authenticated`). Ne retirez pas ce bloc.
- Ne copiez **jamais** la clé `service_role` dans le site ni sur GitHub.
- Le mot de passe de la base ne doit se trouver **que** dans les variables d'environnement de l'hébergeur.

## Dépannage

| Message / symptôme | Cause probable et solution |
|---|---|
| « Connexion à la base de données impossible » | Mauvaise adresse ou mot de passe. Vérifiez l'encodage des caractères spéciaux (étape 2) et utilisez le **Session pooler**. |
| `password authentication failed` | Mot de passe incorrect (réinitialisable dans Supabase → Settings → Database). |
| `could not translate host name` / `Network is unreachable` | Vous utilisez la « Direct connection » (IPv6). Prenez le **Session pooler**. |
| `no pg_hba.conf entry … no encryption` | SSL : ajoutez `?sslmode=require` à la fin de l'adresse (activé par défaut pour les hôtes distants). |
| `remaining connection slots are reserved` | Trop de connexions : utilisez le Session ou Transaction pooler. |
| « Votre session a expiré » en boucle | `AUTH_SECRET` change à chaque démarrage (variable absente et base différente). Définissez-la une fois pour toutes. |
| Le menu Admin n'apparaît pas | Votre e-mail Google n'est pas dans `ADMIN_EMAILS` (la comparaison ignore les majuscules). |
