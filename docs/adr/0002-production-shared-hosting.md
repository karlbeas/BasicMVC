# ADR 0002 — Production sur hébergement mutualisé

- **Date** : 2026-11-04
- **Statut** : accepté
- **Remplace partiellement** : ADR 0001 (conséquence « Hébergement sur VPS »)

## Contexte

L'environnement de production retenu pour F2S Pro est un **hébergement mutualisé** (type cPanel / LiteSpeed / Apache). Ses contraintes habituelles :

| Contrainte | Valeur typique |
| --- | --- |
| PHP | 8.3 sélectionnable (MultiPHP), `exec`/`proc_open`/`putenv` souvent désactivés |
| Base | MySQL 8 **ou MariaDB 10.6+** (souvent MariaDB) |
| Processus longs | Interdits : pas de démon, pas de worker permanent |
| Durée d'une requête | `max_execution_time` 30 à 120 s, mémoire 128 à 512 Mo |
| Tâches planifiées | CRON du panneau, souvent toutes les minutes (parfois 5 ou 15 min) |
| Python | Absent ou inutilisable en production |
| SSH / Composer | Pas garantis |
| Racine web | `public_html/`, pas toujours modifiable pour le domaine principal |
| Antivirus (ClamAV) | Indisponible |

L'ADR 0001 confiait la conversion XLSForm (`pyxform`) et les statistiques (SciPy, pandas, DuckDB) à un worker Python, ce qui imposait un VPS.

## Décision

1. **Tout le code de production est en PHP 8.3.** Aucun appel à Python, `exec` ou `shell_exec` dans l'application.
2. **File de tâches SQL unique** (`jobs`) traitée par `bin/worker-dispatch.php` via le CRON du panneau :
   - budget de temps par passage (`JOBS_TIME_BUDGET`, 50 s par défaut) ;
   - un seul dispatcher à la fois (verrou MySQL `GET_LOCK`) : les passages CRON qui se chevauchent sont sans effet ;
   - **tâches reprenables** : un handler découpe son travail et rend `JobResult::yield($progression)` avant l'échéance ; la tâche reprend au passage suivant sans consommer de tentative ;
   - les tâches restées « running » après un arrêt brutal par l'hébergeur sont remises en file (`JOBS_STALE_AFTER`) ;
   - réessais avec délai exponentiel puis statut `failed` ;
   - colonne `runner` (`php` | `python`) : un worker Python sur VPS pourra plus tard consommer la même file sans changer le contrat ;
   - cette table unique remplace `ai_jobs` / `analysis_jobs` du manifeste ; `type` distingue les tâches.
3. **Déclenchement sans CLI** : point d'accès `/cron/{CRON_HTTP_TOKEN}` (désactivé si le jeton fait moins de 32 caractères) pour les hébergeurs sans CRON en ligne de commande ou un service externe (cron-job.org…).
4. **Latence perçue** (génération IA, phase 2) : la requête web crée la tâche, puis le navigateur interroge son état ; l'endpoint d'état peut exécuter lui-même la tâche en attente via `Dispatcher::runOne()` dans la limite de `max_execution_time`. Le CRON reste le filet de sécurité. L'appel IA a toujours lieu dans une tâche, jamais dans le contrôleur.
5. **Remplacements des briques Python** :

   | Besoin | Solution PHP | Phase |
   | --- | --- | --- |
   | XLSForm ↔ F2S Schema | Convertisseur maison sur `phpoffice/phpspreadsheet` (feuilles survey/choices/settings) | 1–4 |
   | XForm XML (ODK) | Génération/lecture XML en PHP (`DOMDocument`) | 5 |
   | Statistiques descriptives | SQL (agrégats) + PHP | 3 |
   | Khi-deux, t de Student, Mann-Whitney, IC | Implémentation PHP (fonctions gamma/bêta incomplètes) | 3–6 |
   | PDF / DOCX | Dompdf ou mPDF + PhpWord (répond à la question ouverte n° 3) | 3 |

6. **Python reste un outil de développement et de CI** : il sert d'**oracle** pour produire les jeux étalons (`tests/fixtures/`) — `pyxform` valide nos XLSForm exportés, SciPy fournit les valeurs de référence des tests statistiques PHP. Il n'est jamais déployé.
7. **Base portable MySQL 8 / MariaDB 10.6+** : interclassement `utf8mb4_unicode_ci` (pas de `utf8mb4_0900_*`), pas de triggers ni de procédures stockées (privilèges souvent refusés), `FOR UPDATE SKIP LOCKED` uniquement dans la file, dates en UTC. Les migrations sont des fichiers SQL simples ; `php bin/migrate.php --print` produit un script importable dans phpMyAdmin quand il n'y a pas de SSH.
8. **Déploiement par archive** : `vendor/` est construit en CI ou en local (`composer install --no-dev -o`) et livré dans l'archive ; aucune commande Composer n'est requise sur le serveur. Voir `docs/deployment/shared-hosting.md`.
9. **Sécurité adaptée** : sessions dans `storage/sessions` (pas le `/tmp` partagé entre comptes), `.env` et `storage/` hors de la racine web, contrôle strict type/taille des fichiers sans ClamAV (fichiers jamais exécutés ni servis directement), en-têtes de sécurité et CSP `'self'` (Bootstrap et jQuery servis localement, aucun CDN).

## Conséquences

- (+) Coût d'hébergement minimal pour le pilote, déploiement simple par FTP / gestionnaire de fichiers.
- (+) Une seule pile à maintenir en production.
- (−) Les statistiques avancées (niveaux 4–5) demandent une implémentation PHP soignée et des tests étalons rigoureux.
- (−) Débit de la file limité (un dispatcher, ~50 s par minute) : suffisant pour le pilote, à surveiller (tâches en attente, durée moyenne).
- **Critères de passage à un VPS** (nouvelle ADR) : file en attente > 5 min de façon récurrente, besoin de `pyxform`/SciPy en production, plafonds CPU/mémoire de l'hébergeur atteints, ou exigences de localisation des données.
