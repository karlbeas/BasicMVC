# CLAUDE.md — F2S Pro (Field, Form & Survey Pro)

> Ce fichier est lu automatiquement par Claude Code. Le manifeste complet est dans `docs/MANIFEST.md` : **lis-le avant toute tâche non triviale**, ainsi que les ADR de `docs/adr/`.

## Le projet en 5 lignes

F2S Pro est une plateforme d'enquêtes intelligentes (type KoboToolbox / Google Forms) pour l'Afrique francophone.
- Création de questionnaires par IA, ou à partir de fichiers XLSForm, XML, Excel, CSV, JSON, Word ou PDF.
- Interopérabilité avec Kobo, ODK et SurveyCTO (format pivot : XLSForm).
- Son vrai atout : **le tableau de bord d'analyse et le rapport IA vérifiable**.
- Prix : gratuit (1 campagne / 90 jours glissants), Pass Projet 5 $, Pro 10 $/mois.
- Slogan : *Créez. Collectez. Comprenez. Décidez.*

## Stack (décidée — ne pas changer sans ADR)

- **Production : hébergement mutualisé** (cPanel / Apache / LiteSpeed) — voir **ADR 0002**. Conséquences : pas de processus permanent, pas de Python, pas de `exec`, pas de SSH ni de Composer garantis sur le serveur, `max_execution_time` ~30–60 s.
- **Backend** : PHP 8.3+, MVC maison léger (`app/Core`), API REST JSON, Composer (dépendances livrées dans l'archive de release).
- **Base** : MySQL 8+ **ou MariaDB 10.6+** (InnoDB, utf8mb4, `utf8mb4_unicode_ci`, colonnes JSON pour le schéma de formulaire et les réponses brutes). Pas de triggers, procédures stockées ni `utf8mb4_0900_*`.
- **Front** : HTML5, Bootstrap 5.3 + jQuery 3.7 **servis localement** (`public/assets/vendor`, pas de CDN, CSP `'self'`, pas de script inline). Pas de framework SPA. PWA hors ligne en phase 6.
- **Tâches longues** : file SQL `jobs` traitée par `bin/worker-dispatch.php` (CRON chaque minute) ou `/cron/{CRON_HTTP_TOKEN}`. Handlers **reprenables** (`JobResult::yield`), jamais d'appel IA ou de calcul lourd en synchrone dans une requête web.
- **Statistiques et conversions** : en PHP (SQL + PHP). Lecture Excel/XLSForm par `app/Services/Bridge/XlsxReader.php` (ZipArchive + XMLReader, **pas de PhpSpreadsheet** — ADR 0004). **Python uniquement en dev/CI** comme oracle des jeux étalons (`worker/`, `tests/fixtures/imports/build_fixtures.py`).
- **IA** : API Mammouth (compatible OpenAI), `https://api.mammouth.ai/v1`, appelée uniquement côté serveur via `app/Services/Ai/AiGateway.php`, depuis une tâche de la file (`FormGenerationHandler`) ; prompts versionnés dans `app/Prompts/` ; chaque appel journalisé dans `token_usage` ; crédits dans `credit_ledger` (`CreditLedger`), débités seulement après succès.
- **Paiement** : agrégateur Mobile Money + carte (CinetPay / Notch Pay / Flutterwave — à choisir), validation par webhook côté serveur.

## Règles non négociables

1. **Le code calcule, l'IA écrit.** Aucun chiffre d'un tableau de bord ou d'un rapport ne vient de l'IA. Les statistiques sont calculées par SQL ou PHP, puis passées à l'IA sous forme agrégée pour rédaction.
2. **Traçabilité** : chaque phrase de rapport IA référence l'`analysis_id` qui la justifie (effectif, filtres, période, méthode) — bouton « Vérifier cette conclusion ».
3. **Versions immuables** : une `form_version` publiée n'est jamais modifiée (garde `status = 'draft'` dans le `WHERE` de chaque `UPDATE` + tests). Chaque soumission pointe vers sa version. Les réponses brutes sont stockées telles que reçues.
4. **Données personnelles** : jamais de données nominatives envoyées à l'IA ; uniquement des agrégats ou des extraits anonymisés. Petites cellules (n < 5) masquées dans les tableaux publics.
5. **Import honnête** : tout import produit un compte rendu (repris / transformé / perdu / non supporté) et demande confirmation en cas d'ambiguïté. Jamais de conversion silencieuse.
6. **Quotas et crédits IA** vérifiés côté serveur avant chaque opération coûteuse (`PlanPolicy`, limites dans la table `plans`).
7. **Sécurité** : requêtes préparées PDO uniquement (`App\Core\Database`), CSRF sur tous les formulaires et appels API (`X-CSRF-Token`), échappement de sortie (`e()`), isolation par `organization_id` sur chaque requête (premier argument de chaque méthode de repository), validation de type et de taille des fichiers, secrets dans `.env` seulement.
8. **Mutualisé** : tout traitement doit tenir dans le budget d'une tâche (≈ 50 s) ou être découpé ; `.env` et `storage/` hors racine web ; aucune dépendance à `exec`, `putenv`, démon ou extension exotique.

## Commandes

```bash
composer install                             # dépendances (dev)
composer test                                # PHPUnit (Unit + Integration ; base TEST_DB_* dédiée, vidée à chaque run)
composer test:unit                           # tests unitaires seuls (sans base)
composer lint                                # PHP-CS-Fixer (PER-CS 2.0) + PHPStan niveau 6
composer fix                                 # corrige le style
php bin/migrate.php [--status|--print]       # migrations SQL (--print : script pour phpMyAdmin)
php bin/worker-dispatch.php [secondes]       # traitement de la file de tâches (CRON chaque minute)
php -S 127.0.0.1:8080 -t public public/index.php   # serveur de dev
bash bin/build-release.sh [version]          # archive de déploiement (vendor/ inclus, sans dev)
```

## Où est quoi

- `app/Core/` : noyau (App, Router, Request/Response, View, Database, Session, Csrf, Lang, Env, Migrator, Logger).
- `app/Controllers/{Control,Studio,Collect,Api,System}/`, `app/Models/` (repositories), `app/Services/` (AuthService, PlanPolicy, FormValidator, FormSemanticChecker, FormService, AuditLogger…), `app/Services/Collect/` (ExpressionEvaluator — jumeau JS `public/assets/js/f2s-expr.js` testé sur `tests/fixtures/expressions.json` —, SubmissionValidator, FormRenderer, CampaignService, SubmissionService, ResponseExporter), `app/Jobs/` (JobQueue, Dispatcher, handlers), `app/Views/`.
- Bridge (imports) : `app/Services/Bridge/` (XlsxReader, CsvReader, XlsFormConverter, QuestionListConverter, ConversionReport, ImportService) ; jeux étalons `tests/fixtures/imports/`.
- Éditeur visuel : `public/assets/js/f2s-editor.js` ; formulaire public : `/f/{token}` (`PublicFormController`, `public/assets/js/f2s-collect.js`).
- Migrations : appliquées automatiquement par `bin/worker-dispatch.php` après une mise à jour (`SchemaUpgrader`) ; page 503 tant qu'elles sont en attente.
- Routes : `config/routes.php` (table explicite ; middleware `auth`, `guest`, `nocsrf`).
- Schéma de formulaire : `docs/schema/f2s-schema.v1.json` (lu à l'exécution, livré dans la release) + exemples dans `docs/schema/examples/`.
- Déploiement : `docs/deployment/shared-hosting.md`.

## Conventions

- Code, noms de tables, variables et commentaires techniques **en anglais** ; textes de l'interface **en français**, avec fichiers de langue `lang/fr.php` et `lang/en.php` (mêmes clés, vérifié par `tests/Unit/LangTest.php`).
- Tables au pluriel en `snake_case` ; clés primaires `BIGINT UNSIGNED` ; UUID public (`public_id`) pour toute ressource exposée dans une URL ; dates en UTC.
- Montants en centimes de dollar (`INT`) + devise ; affichage en USD et FCFA.
- Une migration SQL par changement de schéma (`database/migrations/AAAAMMJJ_NNNN_description.sql`), jamais d'édition d'une migration déjà appliquée (contrôle par somme de contrôle).
- Tests obligatoires pour : le schéma de formulaire, chaque convertisseur d'import/export, chaque calcul statistique (jeux de données étalons dans `tests/fixtures/` ; fixtures invalides nommées `<code_erreur>[--variante].json`).
- Toute décision d'architecture → un fichier `docs/adr/NNNN-titre.md`.

## Livraison

**À chaque version développée**, construire l'archive installable sur l'hébergement mutualisé et la remettre à l'utilisateur (fichier zip) :

```bash
bash bin/build-release.sh X.Y.Z   # → build/f2s-pro-X.Y.Z-public_html.zip (à remettre) + code d'installation
```

L'utilisateur installe **tout dans `public_html`** : remettre le zip `-public_html` et le code d'installation affiché, et versionner (X.Y.Z) dans l'état d'avancement.

## Où en est le projet

Voir la section « État d'avancement » à la fin de `docs/MANIFEST.md`. **Mets-la à jour à la fin de chaque session de travail.**
