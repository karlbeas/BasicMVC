# ADR 0004 — AI Studio et imports (Bridge) en hébergement mutualisé

- **Date** : 2026-11-04
- **Statut** : accepté
- **Complète** : ADR 0002 (point 5 : « XLSForm via PhpSpreadsheet » est remplacé)

## Contexte

La v0.4.0 ajoute la création de questionnaires par IA (API Mammouth) et l'import de questionnaires (XLSForm de Kobo/ODK/SurveyCTO, listes Excel/CSV, JSON). Contraintes : hébergement mutualisé (pas de démon, `max_execution_time` court, extensions PHP non maîtrisées), règles 1, 4, 5 et 6 du manifeste.

## Décisions

### Lecture Excel sans PhpSpreadsheet
- PhpSpreadsheet déclare une dizaine d'extensions obligatoires (dont `gd`). Composer vérifie ces extensions au démarrage : **une seule manquante chez l'hébergeur empêcherait tout le site de démarrer**, pas seulement l'import.
- F2S lit donc les `.xlsx` avec un lecteur maison (`XlsxReader`) fondé sur `ZipArchive` + `XMLReader` (extensions quasi universelles), en flux, avec plafonds (50 Mo décompressés, 5 000 lignes, 200 colonnes) et XML sans accès réseau ni entités externes.
- Les jeux étalons `.xlsx` sont produits par `openpyxl` (outil indépendant) : le lecteur est testé sur des fichiers qu'il n'a pas écrits.
- L'ancien format `.xls` (binaire) n'est pas lu : l'utilisateur est invité à enregistrer en `.xlsx`.

### Imports honnêtes
- Pipeline : détection (extension + présence d'une feuille `survey`) → conversion → validation F2S Schema → **compte rendu** (repris / transformés / non repris / non pris en charge) → **confirmation** → formulaire en brouillon. Rien n'est créé avant la confirmation ; le fichier source n'est pas conservé (empreinte SHA-256 seulement).
- XLSForm : métadonnées mobiles (`start`, `deviceid`…) écartées et signalées ; identifiants invalides renommés avec réécriture des `${…}` (un doublon ne détourne jamais les références) ; colonnes inconnues conservées dans `x_source` ; `or_other`, `range`, `multiline`, `acknowledge` transformés et signalés ; `repeat`, `geopoint`, `image`, `file` repris mais signalés non collectables ; `rank`, listes externes non repris.

### IA
- `AiGateway` unique (modèles en configuration, modèle de secours, journal `token_usage` avec coût, plafond quotidien par organisation, transport HTTP injectable pour les tests). Aucune donnée de répondant n'est envoyée : seulement la description saisie par l'utilisateur (ou le questionnaire lui-même pour une retouche).
- Génération en tâche `jobs` reprenable : **plan de sections → questionnaire → validation stricte (+ une réparation automatique au plus)**. Chaque appel est une étape ; si le temps restant ne permet pas un appel, la tâche se met en pause et reprend au passage suivant.
- La page de suivi interroge `/api/jobs/{id}`, qui exécute la tâche en attente dans la limite de `max_execution_time` (ADR 0002 §4) après avoir libéré le verrou de session.
- **Crédits** (`credit_ledger`) : vérifiés avant, **débités une seule fois et seulement si un formulaire valide est enregistré** (référence = identifiant de tâche, contrainte d'unicité). Allocation selon l'offre : `ai_credits` (unique) ou `ai_credits_per_month` (Pro).
- Un formulaire invalide n'est jamais enregistré ; au-delà du quota de questions de l'offre, rien n'est créé ni débité.

## Conséquences

- (+) Aucune dépendance lourde ajoutée ; archive de livraison toujours < 1 Mo.
- (+) Imports et IA testables hors ligne (fixtures, faux transport).
- (−) Le lecteur maison ne couvre pas les cas exotiques d'Excel (formules non calculées : seule la dernière valeur enregistrée est lue ; dates en numéro de série). Acceptable pour des questionnaires.
- (−) Le premier appel réel à Mammouth ne peut être vérifié que sur le serveur de production (API inaccessible depuis l'environnement de développement).
