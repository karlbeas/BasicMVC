# Déployer F2S Pro sur un hébergement mutualisé

Guide pour cPanel / Plesk / DirectAdmin (Apache ou LiteSpeed). Décision : [ADR 0002](../adr/0002-production-shared-hosting.md).

## 1. Prérequis côté hébergeur

- PHP **8.3** (cPanel → *MultiPHP Manager*) avec les extensions `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl`, `curl`, `intl` (recommandée).
- MySQL **8+** ou MariaDB **10.6+**, une base et un utilisateur dédiés (cPanel → *Bases de données MySQL*).
- HTTPS actif (AutoSSL / Let's Encrypt).
- Une tâche CRON (toutes les minutes si possible).

Réglages PHP conseillés (*MultiPHP INI Editor*) : `memory_limit = 256M`, `max_execution_time = 60`, `upload_max_filesize = 10M`, `post_max_size = 12M`, `display_errors = Off`.

## 2. Construire l'archive de release (sur votre poste ou en CI)

```bash
bash bin/build-release.sh          # produit build/f2s-pro-<version>.zip
```

L'archive contient `vendor/` (dépendances de production uniquement, autoloader optimisé) : **aucune commande Composer n'est nécessaire sur le serveur**. La CI GitHub (`.github/workflows/ci.yml`) produit la même archive en artefact à chaque push.

## 3. Disposition des fichiers

Principe : **seul le contenu de `public/` est accessible depuis le web.** `.env`, `storage/`, `vendor/` et le code restent hors de la racine web.

### Option A — recommandée : racine du domaine pointée sur `public/`

```
/home/USER/f2s/              ← archive décompressée ici
    app/ bin/ config/ database/ lang/ storage/ vendor/ .env
    public/                  ← racine web du (sous-)domaine
```

cPanel → *Domaines* → modifier la « racine du document » du domaine ou sous-domaine : `/home/USER/f2s/public`. (Possible pour les sous-domaines et domaines additionnels ; pour le domaine principal, voir B.)

### Option B — `public_html` imposé

1. Décompresser l'archive dans `/home/USER/f2s/`.
2. Copier **le contenu** de `f2s/public/` dans `public_html/` (ou un sous-dossier `public_html/enquetes/`).
3. Créer `public_html/f2s-path.php` :
   ```php
   <?php return '/home/USER/f2s';
   ```
   (Ce fichier est protégé par le `.htaccess` copié depuis `public/`.)
4. À chaque mise à jour, recopier `public/` (assets) en plus du reste.

Avec SSH, un lien symbolique remplace la copie : `ln -s ~/f2s/public ~/public_html/enquetes`.

### Option C — dernier recours : tout dans `public_html`

Décompresser l'archive directement dans `public_html/`. Le `.htaccess` racine redirige tout vers `public/` et refuse les fichiers cachés (`.env`…). **Vérifiez** que `https://votre-domaine/.env`, `/storage/` et `/vendor/composer/installed.json` renvoient 403/404. Préférez A ou B.

### Sous-dossier

L'application fonctionne aussi sous `https://exemple.com/enquetes/` : le chemin de base est détecté automatiquement. En cas de doute, fixez `APP_URL=https://exemple.com/enquetes`.

## 4. Configuration

Copier `.env.example` en `.env` (gestionnaire de fichiers) et renseigner au minimum `APP_URL`, `DB_*`, `APP_ENV=production`, `APP_DEBUG=false`, `STORAGE_PATH` (chemin absolu) et, si besoin, `CRON_HTTP_TOKEN`. Droits : `.env` en `600` ; `storage/` et ses sous-dossiers en écriture pour PHP (`755` ou `775` selon l'hébergeur).

## 5. Base de données

- **Avec SSH ou Terminal cPanel** : `php bin/migrate.php` (puis `php bin/migrate.php --status`).
- **Sans SSH** :
  - soit générer le script sur votre poste : `php bin/migrate.php --print > f2s-schema.sql` puis l'importer dans **phpMyAdmin** (base vide uniquement) ;
  - soit créer une tâche CRON ponctuelle `php /home/USER/f2s/bin/migrate.php`, attendre une minute, puis la supprimer.

Une migration déjà appliquée n'est jamais modifiée : `migrate.php` refuse de tourner si un fichier appliqué a changé.

## 6. Tâches planifiées (file de tâches)

cPanel → *Tâches Cron*, toutes les minutes :

```
* * * * * /usr/local/bin/php /home/USER/f2s/bin/worker-dispatch.php >/dev/null 2>&1
```

Le chemin de PHP 8.3 varie selon l'hébergeur (`/opt/cpanel/ea-php83/root/usr/bin/php`, `/usr/local/bin/ea-php83`…).

Sans CRON en ligne de commande : définir `CRON_HTTP_TOKEN` (≥ 32 caractères aléatoires, `php -r "echo bin2hex(random_bytes(24));"`) et faire appeler chaque minute, par le CRON « URL » du panneau ou un service externe :

```
https://votre-domaine/cron/VOTRE_JETON
```

Vérification : `/health` doit répondre `{"status":"ok","db":"ok"}`.

## 7. Mises à jour

1. Construire la nouvelle archive.
2. Sauvegarder la base (phpMyAdmin → Exporter) et `.env`.
3. Remplacer `app/ bin/ config/ database/ lang/ public/ vendor/` (ne pas toucher `.env` ni `storage/`).
4. Lancer les migrations (section 5).
5. Contrôler `/health` et la page d'accueil.

## 8. Sécurité — liste de contrôle

- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] `https://domaine/.env`, `/storage/`, `/vendor/`, `/app/` → 403 ou 404.
- [ ] HTTPS forcé (redirection 301 dans `public/.htaccess`).
- [ ] `storage/` hors de la racine web (options A/B) et non listable.
- [ ] Sauvegardes automatiques de la base activées chez l'hébergeur et restauration testée.
- [ ] Journaux applicatifs : `storage/logs/app-AAAA-MM-JJ.log` (purge périodique).

## 9. Développement local

```bash
composer install
cp .env.example .env          # APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false, DB_*
php bin/migrate.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Tests d'intégration : base dédiée dont le nom contient `test` (par défaut `f2s_test`, utilisateur `f2s`/`f2s`, voir `phpunit.xml.dist`) — elle est **vidée** à chaque exécution.
