# F2S Pro — Field, Form & Survey Pro

**Créez. Collectez. Comprenez. Décidez.**

Plateforme d'enquêtes intelligentes pour l'Afrique francophone : création de questionnaires (IA, XLSForm, Excel, Word/PDF), collecte web, tableau de bord automatique et rapport IA vérifiable. Compatible Kobo, ODK et SurveyCTO.

- Vision, offres, modules, feuille de route : [`docs/MANIFEST.md`](docs/MANIFEST.md)
- Décisions d'architecture : [`docs/adr/`](docs/adr/) — notamment [ADR 0002 : production sur hébergement mutualisé](docs/adr/0002-production-shared-hosting.md)
- Format des formulaires : [`docs/schema/f2s-schema.v1.json`](docs/schema/f2s-schema.v1.json)
- Déploiement : [`docs/deployment/shared-hosting.md`](docs/deployment/shared-hosting.md)
- Consignes pour Claude Code : [`CLAUDE.md`](CLAUDE.md)

## Démarrage rapide (développement)

Prérequis : PHP 8.3 (`pdo_mysql`, `mbstring`, `fileinfo`), Composer, MySQL 8+ ou MariaDB 10.6+.

```bash
composer install
cp .env.example .env     # APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false, DB_*
php bin/migrate.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Puis ouvrir http://127.0.0.1:8080, créer un compte, un projet, et importer `docs/schema/examples/satisfaction-pharmacie.json`.

## Qualité

```bash
composer test     # PHPUnit — les tests d'intégration utilisent la base TEST_DB_* (f2s_test), vidée à chaque run
composer lint     # PHP-CS-Fixer + PHPStan niveau 6
```

## Production (hébergement mutualisé)

```bash
bash bin/build-release.sh   # archive autonome (vendor/ inclus) à téléverser
```

Puis suivre [`docs/deployment/shared-hosting.md`](docs/deployment/shared-hosting.md) : racine web sur `public/`, `.env` et `storage/` hors racine web, migrations (CLI ou script phpMyAdmin via `php bin/migrate.php --print`), CRON `bin/worker-dispatch.php` chaque minute.

## Licence

GPL-3.0-or-later (voir [`LICENSE`](LICENSE)).
