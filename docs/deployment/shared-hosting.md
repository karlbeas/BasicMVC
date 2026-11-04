# Déployer F2S Pro sur un hébergement mutualisé

Guide pour cPanel / Plesk / DirectAdmin (Apache ou LiteSpeed). Décision : [ADR 0002](../adr/0002-production-shared-hosting.md).

## 1. Prérequis côté hébergeur

- PHP **8.3** (cPanel → *MultiPHP Manager*) avec les extensions `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl`, `curl`, `intl` (recommandée).
- MySQL **8+** ou MariaDB **10.6+**, une base et un utilisateur dédiés (cPanel → *Bases de données MySQL*).
- HTTPS actif (AutoSSL / Let's Encrypt).
- Une tâche CRON (toutes les minutes si possible).

Réglages PHP conseillés (*MultiPHP INI Editor*) : `memory_limit = 256M`, `max_execution_time = 60`, `upload_max_filesize = 10M`, `post_max_size = 12M`, `display_errors = Off`.

## 2. Installation rapide dans `public_html` (méthode standard)

Archive : `f2s-pro-X.Y.Z-public_html.zip` (contenu à la racine du zip) et son **code d'installation** (fourni avec l'archive, fichier `storage/install.key`).

1. cPanel → *Bases de données MySQL* : créer une base (ex. `moncompte_f2s`), un utilisateur, et lui donner **tous les privilèges** sur la base.
2. cPanel → *Gestionnaire de fichiers* → `public_html/` : supprimer l'éventuel `index.html` par défaut, téléverser le zip, clic droit → *Extraire*. (Un sous-dossier, ex. `public_html/enquetes/`, fonctionne aussi.)
3. Ouvrir `https://votre-domaine/` : l'**assistant d'installation** s'affiche. Il vérifie PHP et les droits, demande le code d'installation et les accès à la base, crée les tables et écrit `.env`. Il se désactive ensuite définitivement (le code est supprimé).
4. Ajouter la tâche CRON affichée en fin d'installation (section 6).
5. Créer le premier compte.

Protection : le `.htaccess` racine envoie toutes les requêtes vers `public/`, refuse les fichiers cachés (`.env`) et sensibles ; chaque dossier interne (`app/`, `config/`, `storage/`, `vendor/`…) porte en plus son propre `Require all denied`. Après installation, vérifiez que `https://votre-domaine/.env`, `/storage/` et `/vendor/` renvoient 403 ou 404.

## 3. Disposition alternative : application hors de la racine web

Plus robuste si l'hébergeur le permet (archive `f2s-pro-X.Y.Z.zip`, dossier `f2s-pro/`) :

- **A.** Décompresser dans `/home/USER/f2s/` et pointer la racine du (sous-)domaine sur `/home/USER/f2s/public` (cPanel → *Domaines*).
- **B.** Décompresser dans `/home/USER/f2s/`, copier le contenu de `public/` dans `public_html/` et créer `public_html/f2s-path.php` contenant `<?php return '/home/USER/f2s';`.

L'assistant `/install` fonctionne de la même façon dans ces deux cas.

## 4. Configuration manuelle (sans l'assistant)

Copier `.env.example` en `.env` et renseigner `APP_URL`, `DB_*`, `APP_ENV=production`, `APP_DEBUG=false`, `CRON_HTTP_TOKEN`. Droits : `.env` en `600` ; `storage/` en écriture pour PHP.

## 5. Base de données (mises à jour)

L'assistant applique les migrations à l'installation. Pour une **mise à jour** :

- avec Terminal / SSH : `php bin/migrate.php` ;
- sans : créer une tâche CRON ponctuelle `php /chemin/vers/bin/migrate.php`, attendre une minute, la supprimer ; ou importer dans phpMyAdmin le script de `php bin/migrate.php --print` (base vide uniquement).

Une migration déjà appliquée n'est jamais modifiée : `migrate.php` refuse de tourner si un fichier appliqué a changé.

### Construire les archives (développeur)

```bash
bash bin/build-release.sh X.Y.Z   # → build/f2s-pro-X.Y.Z-public_html.zip, build/f2s-pro-X.Y.Z.zip, code d'installation
```

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

1. Récupérer la nouvelle archive.
2. Sauvegarder la base (phpMyAdmin → Exporter) et `.env`.
3. Extraire l'archive par-dessus l'installation existante (le zip ne contient pas de `.env` : la configuration est conservée). Supprimer `storage/install.key` s'il réapparaît (sans effet une fois installé, mais inutile).
4. **Rien d'autre à faire** : à son passage suivant (≤ 1 minute), la tâche CRON applique automatiquement les nouvelles migrations. Entre-temps, le site affiche « Mise à jour en cours ». Sans CRON, appeler une fois l'URL `/cron/VOTRE_JETON`.
5. Contrôler `/health` et la page d'accueil.

## 8. Sécurité — liste de contrôle

- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] `https://domaine/.env`, `/storage/`, `/vendor/`, `/app/` → 403 ou 404.
- [ ] HTTPS forcé (redirection 301 dans `public/.htaccess`).
- [ ] `storage/` non accessible depuis le web (403/404) ; `storage/install.key` absent après installation.
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
