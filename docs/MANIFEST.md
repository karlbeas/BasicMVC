# Manifeste du projet F2S Pro — Field, Form & Survey Pro

**Version 1.1 — 4 novembre 2026** (v1.0 rédigée sous le nom de travail « MiniForm Pro » ; v1.1 : nom F2S Pro, production sur hébergement mutualisé — [ADR 0002](adr/0002-production-shared-hosting.md))
Sources de décision : étude produit Claude (« F2S Pro — étude produit », 3 nov. 2026) et rapport Studio Redcarré v1.0 (3 nov. 2026). Les arbitrages entre les deux sont tranchés ici ; ce document fait foi.

---

## 1. Vision

**Créez. Collectez. Comprenez. Décidez.**

F2S Pro transforme une intention (« je veux mesurer la satisfaction des clients de ma pharmacie ») ou un jeu de données existant (export Kobo, fichier Excel) en questionnaire publiable, tableau de bord automatique et rapport professionnel vérifiable, pour 10 $/mois.

Le produit ne se vend pas sur la création de formulaires (Google Forms et Tally sont gratuits) mais sur **la réduction du travail méthodologique et analytique**.

### Deux portes d'entrée

| Porte | Déclencheur | Résultat |
| --- | --- | --- |
| **Créer une enquête** | Description libre du besoin ou questionnaire Word/PDF | Formulaire structuré, éditable, publiable |
| **Analyser des données existantes** | Fichier XLSX/CSV/JSON ou export Kobo/ODK | Données validées, tableau de bord et rapport — sans changer d'outil de collecte |

### Cibles

Étudiants et chercheurs (mémoires, thèses), consultants et bureaux d'études, petites ONG non éligibles au plan gratuit de Kobo, collectivités, services RH, écoles, PME (satisfaction client).
Cible d'abonnement la plus durable : consultants et organisations qui mènent plusieurs enquêtes par an.

### Concurrence (prix relevés en novembre 2026)

| Outil | Entrée payante | Ce qui nous différencie |
| --- | --- | --- |
| KoboToolbox | Starter 25 $/mois (1 000 soumissions) ; gratuit seulement pour ONG, administrations, écoles | Assistant de création IA, tableau de bord et rapport IA |
| SurveyCTO | Basic 250 $/mois | Prix |
| ODK Central | Auto-hébergé ou cloud payant | Simplicité, aucun profil technique requis |
| Google Forms | Inclus Workspace ; Gemini crée des formulaires simples (pas de sections multiples) | XLSForm, statistiques d'enquête, rapport livrable |
| Tally | Pro 29 $/mois | Analyse plutôt que volume de formulaires |

---

## 2. Offres et quotas (à coder dans `plans` + `PlanPolicy`)

| | Gratuit — 0 $ | Pass Projet — 5 $ / 30 jours | Pro — 10 $/mois (96 $/an) |
| --- | --- | --- | --- |
| Campagnes | 1 publiée par **90 jours glissants** (à partir de la date de publication) | 1 | 10 actives |
| Questions par formulaire | 30 | 150 | 300 |
| Réponses | 100 par campagne | 1 000 | 5 000 / mois (usage équitable) |
| Crédits IA | 1 génération guidée + retouches limitées (10 crédits) | 60 crédits | 100 crédits / mois |
| Tableau de bord | Essentiel (niveaux 1–2) | Complet | Complet |
| Rapport IA PDF/DOCX | Aperçu flouté | Oui | Oui |
| Imports | XLSX/CSV simples | Tous formats | Tous formats + connecteurs |
| Exports | CSV, JSON (toujours garantis) | + XLSX, PDF | + XLSForm, SPSS/Stata (phase 6) |
| Collaborateurs | 1 | 1 | 2 |
| Filigrane « Créé avec F2S Pro » | Oui | Non | Non |

- Offre **Équipe 29 $/mois** : hors MVP, à décider après le pilote.
- Les données d'un compte gratuit restent consultables et exportables après la campagne (politique de rétention à publier).
- Plafonds séparés : stockage médias, appels IA, fréquence d'API.
- Prix affichés en USD et FCFA (10 $ ≈ 6 000 FCFA ; 5 $ ≈ 3 000 FCFA).
- Les plafonds « questions par formulaire » du Pass et du Pro (150, 300) et le barème de crédits ci-dessous sont des valeurs de départ proposées, à ajuster après mesure.

### Barème des crédits IA (valeurs de départ)

| Opération | Crédits |
| --- | --- |
| Génération d'un questionnaire | 5 |
| Retouche IA d'un questionnaire | 1 |
| Conversion Word/PDF → formulaire | 5 |
| Traduction FR ↔ EN d'un formulaire | 2 |
| Synthèse IA d'un tableau ou graphique | 1 |
| Question en langage naturel (« Ask My Survey ») | 2 |
| Codage thématique de 1 000 réponses ouvertes | 10 |
| Rapport analytique complet (modèle standard) | 15 |
| Rapport premium (modèle haut de gamme) | 40 |

---

## 3. Les six modules

| Module | Code | Rôle |
| --- | --- | --- |
| **AI Studio** | `Studio` | Création conversationnelle, éditeur visuel, validation méthodologique, langues, règles, aperçu analytique |
| **Bridge** | `Bridge` | Import/export XLSForm, XForm XML, Excel, CSV, JSON, Word/PDF ; mapping ; comptes rendus de conversion ; connecteurs Kobo/ODK/Google |
| **Collect** | `Collect` | Publication par lien et QR, enquêtes publiques/privées/terrain, multipages, quotas, brouillons, hors ligne (phase 6) |
| **Analytics** | `Analytics` | Statistiques déterministes, croisements, segmentation, tableaux de bord, alertes qualité, exports |
| **Intelligence** | `Intelligence` | Analyste conversationnel, thèmes des réponses ouvertes, rapports, vérification des conclusions |
| **Control** | `Control` | Organisations, workspaces, utilisateurs, rôles, abonnements, paiements, quotas, crédits, journal d'audit, administration |

### 3.1 AI Studio — exigences

- L'utilisateur décrit : objectif, public, langue, durée cible, analyses envisagées.
- L'IA produit d'abord **un plan de sections**, puis le questionnaire au format **F2S Schema** (`docs/schema/f2s-schema.v1.json`).
- Chaque question a : identifiant stable (`name`), type, libellés FR/EN, choix codés, règles de validation, et **une utilité analytique** (`analysis.role`).
- Types MVP : texte, texte long, entier, décimal, date, heure, choix unique, choix multiple, échelle (Likert/NPS), matrice, téléphone, e-mail, note/information, calcul. Phase 5–6 : fichier, photo, GPS, groupe répété.
- Logique : `relevant` (affichage conditionnel), `required`, `constraint` + message d'erreur, `calculation`. Syntaxe des expressions **compatible XLSForm** (`${age} >= 18`).
- Contrôle qualité IA : questions suggestives, ambiguës, doubles, redondantes ; estimation de la durée de passation.
- **Innovation clé — « Dashboard Before Data »** : avant publication, l'écran montre les indicateurs, graphiques et croisements prévus ; les variables manquantes pour les analyses voulues (âge, sexe, zone…) sont signalées et proposées à l'ajout.
- Validation stricte : toute sortie IA est validée contre le JSON Schema ; si invalide → une tentative de réparation automatique, puis erreur claire. Jamais de formulaire invalide enregistré.

### 3.2 Bridge — exigences

Pipeline obligatoire :

```
Source → Détection du format → Validation → Transformation vers F2S Schema
       → Mapping & aperçu → Compte rendu de conversion → Confirmation utilisateur
       → Éditeur / Analytics / Export
```

| Format | Import | Export | Phase |
| --- | --- | --- | --- |
| XLSForm (.xlsx : feuilles survey/choices/settings) | ✔ | ✔ | MVP (convertisseur PHP sur PhpSpreadsheet ; `pyxform` en CI pour valider les exports — ADR 0002) |
| Excel / CSV de **questions** | ✔ (inférence assistée) | — | MVP |
| Excel / CSV / JSON de **données** (réponses) | ✔ (mapping colonnes → questions/types) | ✔ | MVP |
| JSON F2S Schema | ✔ | ✔ | MVP |
| Word / PDF (questionnaire papier) | ✔ via IA | Rapport | MVP |
| XForm XML (ODK) | ✔ | ✔ | Phase 5 |
| Connecteur KoboToolbox (clé API de l'utilisateur) | ✔ formulaire + données | — | Phase 5 |
| Google Forms (API + OAuth) | ✔ | — | Phase 5 |
| SPSS (.sav), Stata (.dta), R | — | ✔ | Phase 6 |
| API REST + webhooks | — | ✔ | Après J90 |
| Points d'accès OpenRosa (ODK Collect / KoboCollect) | — | — | Après J90 (à évaluer) |

- Un fichier Excel n'est pas forcément un XLSForm ; un XML n'est pas forcément un XForm : **la détection vient en premier**.
- Le compte rendu liste : éléments pris en charge, transformations, avertissements, pertes potentielles, fonctions non supportées.

### 3.3 Collect — exigences

- MVP : collecte web responsive, lien + QR code, partage WhatsApp, brouillons, logique conditionnelle, dates d'ouverture/fermeture, quotas de réponses, protection anti-doublon (jeton de soumission unique).
- Enquêtes privées : code d'accès ou invitation.
- Phase 6 : PWA hors ligne (cache contrôlé du formulaire, brouillons chiffrés, synchronisation idempotente par `submission_uuid`), suivi des enquêteurs, supervision, GPS, médias avec consentement.
- **Ne pas présenter la PWA comme équivalente à ODK Collect** avant des tests sur appareils Android bas de gamme et connexions instables.

### 3.4 Analytics — le cœur du produit

Le tableau de bord se construit automatiquement depuis le type de chaque question.

| Niveau | Contenu | Offre |
| --- | --- | --- |
| 1 — Descriptif | Effectifs, %, distributions, moyenne, médiane, dispersion, valeurs manquantes, réponses par jour | Toutes |
| 2 — Qualité | Doublons, incohérences, valeurs aberrantes, durées trop courtes, questionnaires incomplets | Toutes |
| 3 — Comparatif | Tableaux croisés glisser-déposer, segmentations multiples, comparaisons par sexe/âge/site/période | Pass, Pro |
| 4 — Statistique | Khi-deux, t de Student / Mann-Whitney, intervalles de confiance, corrélations, pondération si justifiée | Pass, Pro |
| 5 — Textuel | Thèmes des réponses ouvertes avec citations anonymisées | Pass, Pro |
| 6 — Décisionnel | Tableaux de bord partageables (lien public/protégé), KPI personnalisés, alertes, rapports exportables | Pass, Pro |

- Choix automatique du graphique : barres (choix), histogramme (nombre), courbe (série temporelle), thèmes (texte), carte (GPS).
- Les filtres croisent les variables **sans perdre la cohérence des dénominateurs** ; chaque indicateur affiche son effectif et ses filtres actifs.
- Significativité exprimée en clair (« différence réelle » / « peut-être due au hasard »), avec le test et l'effectif.

### 3.5 Intelligence — l'analyste conversationnel

Chaîne obligatoire :

```
Question utilisateur → interprétation IA → plan d'analyse structuré (JSON)
→ validation du plan (liste blanche d'opérations) → moteur statistique (SQL / worker)
→ résultats traçables (analysis_id) → rédaction IA → rapport / visualisation
```

- L'IA **ne produit jamais un chiffre** directement à partir d'un fichier complet.
- L'IA ne génère pas de SQL libre exécuté tel quel : elle produit un plan d'analyse JSON (variables, filtres, opération parmi une liste blanche) que le code traduit en requête.
- Garde-fous rédactionnels (dans le prompt système et vérifiés en sortie) :
  - pas de causalité déduite d'une association ;
  - pas d'échantillon non probabiliste qualifié de « représentatif » ;
  - toujours afficher effectifs, dénominateurs, exclusions, valeurs manquantes et méthodes ;
  - tests statistiques uniquement si hypothèses et plan d'échantillonnage compatibles ;
  - sous-groupes trop petits protégés ; pas d'inférence sur des attributs sensibles ;
  - séparer faits mesurés, interprétations et recommandations.
- Rapport type : contexte, méthode, échantillon, résultats clés, graphiques, limites, recommandations. Export PDF et DOCX.

### 3.6 Control

Organisations → workspaces → projets ; rôles `owner`, `admin`, `designer`, `collector`, `analyst`, `viewer`. Abonnements, paiements Mobile Money/carte, quotas, crédits IA, journal d'audit, back-office d'administration.

---

## 4. Architecture

```
[Navigateur : HTML5 + Bootstrap 5 + jQuery]
        │  HTTPS / JSON
[PHP 8.3 — MVC + API REST]  ──►  [MySQL 8 / MariaDB 10.6+]
        │        │                    ▲
        │        └─► file de tâches (jobs) ──► [CRON → bin/worker-dispatch.php (PHP, tâches reprenables)]
        │
        ├─► AiGateway ──► API Mammouth (OpenAI-compatible)
        └─► PaymentGateway ◄── webhooks agrégateur Mobile Money
```

> Hébergement de production : **mutualisé** (ADR 0002). Aucun processus permanent ni Python en production ; Python sert d'oracle de tests en CI.

| Couche | Implémentation |
| --- | --- |
| UI | HTML5, Bootstrap 5, JavaScript/jQuery servis localement (pas de CDN) ; PWA en phase 6 |
| Backend | PHP 8.3+, MVC, API REST sécurisée, Composer, PHPUnit |
| Données | MySQL 8+ ou MariaDB 10.6+, schéma interne JSON versionné, index analytiques |
| Fichiers | Stockage protégé hors webroot, quotas, rétention, contrôle de type/taille (ClamAV indisponible en mutualisé : fichiers jamais exécutés ni servis directement) |
| Tâches longues | File SQL `jobs` + CRON du panneau (ou déclencheur HTTP protégé), budget ≈ 50 s par passage, tâches reprenables ; worker dédié sur VPS seulement après une nouvelle ADR |
| IA | Passerelle Mammouth : routage des modèles, budget, cache, journal d'usage (`token_usage`) |
| Documents | Génération PDF/XLSX/CSV/DOCX traçable |
| Connecteurs | Adaptateurs Kobo, ODK, Google selon API et permissions de l'utilisateur |
| Paiement | Agrégateur Mobile Money + cartes ; activation uniquement après vérification serveur du webhook |

### Arborescence cible

```
f2s-pro/
├── CLAUDE.md
├── .env.example
├── docs/ (MANIFEST.md, adr/, schema/)
├── public/                 # index.php, assets compilés
├── app/
│   ├── Controllers/        # par module : Studio, Bridge, Collect, Analytics, Intelligence, Control
│   ├── Models/
│   ├── Services/           # AiGateway, PlanPolicy, CreditLedger, FormValidator, PaymentGateway…
│   ├── Prompts/            # prompts système versionnés
│   ├── Jobs/               # ImportJob, AnalysisJob, ReportJob…
│   └── Views/
├── config/
├── database/migrations/
├── lang/ (fr.php, en.php)
├── worker/                 # Python, dev/CI uniquement : oracles des jeux étalons (ADR 0002)
├── storage/                # hors webroot
├── tests/ (Unit, Feature, fixtures/)
└── bin/ (migrate.php, worker-dispatch.php)
```

### Entités de données principales

`organizations`, `workspaces`, `users`, `memberships` (rôles), `projects`, `forms`, `form_versions` (schéma JSON immuable), `questions` (projection indexée de la version), `campaigns`, `submissions` (`submission_uuid`, brut JSON + statut), `answers` (projection structurée pour agrégations), `imports`, `import_reports`, `exports`, `analysis_definitions`, `analysis_results`, `dashboards`, `dashboard_widgets`, `jobs` (file unique, remplace `ai_jobs` / `analysis_jobs`), `token_usage`, `credit_ledger`, `plans`, `subscriptions`, `payments`, `audit_logs`.

Règles :
- Les réponses brutes (`submissions.raw_json`) sont conservées dans leur forme d'origine ; `answers` est une projection reconstructible.
- `form_versions` n'est jamais mise à jour après publication (contrôle applicatif + test).
- Toute table métier porte `organization_id` ; toute requête filtre dessus.

---

## 5. IA — modèles et coûts

Base : API Mammouth `https://api.mammouth.ai/v1`, clé dans `MAMMOUTH_API_KEY`. Tarifs maximum publiés (nov. 2026), en $ par million de tokens — **à revérifier avant déploiement** (https://info.mammouth.ai/docs/api-quick-start/).

| Usage | Modèle par défaut | Secours | Entrée / Sortie ($/M) du modèle par défaut |
| --- | --- | --- | --- |
| Génération et retouche de questionnaire | `gpt-6-luna` | `deepseek-v4.1-flash` | 0,10 / 0,50 |
| Structuration Word/PDF → formulaire | `gpt-6-luna` | `qwen3.8-flash` | 0,10 / 0,50 |
| Plan d'analyse (« Ask My Survey ») | `deepseek-v4.1-flash` | `glm-5.3-flash` | 0,22 / 0,66 |
| Synthèses et rapport standard | `gpt-6-luna` | `claude-haiku-5-5` | 0,10 / 0,50 |
| Rapport premium (option en crédits) | `claude-sonnet-5-5` | `gpt-6.1-sol` | 2 / 10 |
| Thèmes des réponses ouvertes | `text-embedding-3-small` + `gpt-6-luna` | — | 0,02 (embeddings) |

Coûts simulés (à 0,10 / 0,50 $) : questionnaire ≈ 0,0023 $ ; retouche ≈ 0,0012 $ ; synthèse ≈ 0,0014 $ ; rapport ≈ 0,005 $.

Règles d'économie : noms de modèles en configuration (jamais en dur), prompts système courts et versionnés dans `app/Prompts/`, `max_tokens` serré, sortie JSON validée, cache des résultats, plafond quotidien par organisation, journalisation de chaque appel dans `token_usage` (modèle, tokens, coût, opération).

---

## 6. Sécurité et conformité

- Privacy-by-design ; information claire des répondants ; politique de confidentialité et de rétention publiées.
- Cameroun : loi n° 2024/017 du 23 décembre 2024 relative à la protection des données à caractère personnel — revue juridique avant lancement ; lois des autres pays visés à vérifier.
- Isolation des organisations, permissions par rôle, authentification robuste, contrôle des liens de partage.
- Chiffrement en transit (HTTPS), protection des données sensibles au repos et des sauvegardes, rotation des secrets.
- Contrôle de taille et de type des fichiers, antivirus, protection contre SSRF (connecteurs) et injections.
- Minimisation des données envoyées à l'IA : agrégats et extraits anonymisés.
- Masquage des petites cellules et des résultats ré-identifiants dans les tableaux de bord publics.
- Journaux d'accès, gestion des incidents, export et suppression des données à la demande.
- Tests de sauvegarde/restauration, tests de charge, validation de l'intégrité des imports.

---

## 7. Feuille de route

| Phase | Période | Livrables | Critère de sortie |
| --- | --- | --- | --- |
| **1 — Socle** | J1–J7 | Squelette MVC, auth, organisations/workspaces/rôles, projets, migrations, **F2S Schema v1 + validateur**, imports XLSX/CSV simples | Tests verts ; un formulaire JSON valide peut être créé, validé, stocké |
| **2 — AI Studio + Collect** | J8–J15 | Génération IA, éditeur visuel, Dashboard Before Data, publication web (lien/QR), règles courantes, soumissions | Un utilisateur crée un formulaire par prompt et reçoit des réponses |
| **3 — Analytics** | J16–J24 | Niveaux 1–3, filtres, tableaux croisés, alertes qualité, AI Insights, rapport PDF | Tableau de bord automatique juste sur les jeux étalons |
| **4 — Monétisation + pilote** | J25–J35 | Paiement Mobile Money, plans, quotas, crédits, administration, import/export XLSForm, tests, **pilote Cameroun** | MVP en ligne, premiers paiements |
| **5 — Bridge étendu** | J36–J60 | Connecteurs Kobo/Google, XForm XML, équipes, synchronisation, Ask My Survey | Import Kobo → rapport en moins de 5 min |
| **6 — Terrain + stats** | J61–J90 | PWA hors ligne robuste, supervision terrain, statistiques avancées (niveaux 4–5), SPSS/Stata, tests de charge | Hors ligne validé sur appareils réels |

Priorité commerciale : le **pont Kobo/Excel → tableau de bord → rapport IA** est la fonction la plus rapide à vendre.

### Budget

| Poste | MVP 35 jours | Produit étendu 90 jours |
| --- | --- | --- |
| Outils de développement IA | 50–150 $ | 150–400 $ |
| Crédits IA de test | 10–40 $ | 40–120 $ |
| Hébergement / tests | 20–70 $ | 100–300 $ |
| Services techniques | 20–80 $ | 80–250 $ |
| Tests et imprévus | 50–150 $ | 150–450 $ |
| **Total hors publicité** | **150–490 $** | **520–1 520 $** |
| Publicité de lancement (Facebook/LinkedIn) | 150–300 $ | à réinvestir |

Hors rémunération du développeur (35 jours à 150 $/jour ≈ 5 250 $ de travail valorisé).

### Modèle économique (contribution mensuelle, scénario Redcarré)

| | 50 abonnés | 250 abonnés | 1 000 abonnés |
| --- | --- | --- | --- |
| Revenu brut à 10 $ | 500 $ | 2 500 $ | 10 000 $ |
| Frais de paiement 3 % | 15 $ | 75 $ | 300 $ |
| Variable 0,60 $/abonné | 30 $ | 150 $ | 600 $ |
| Infrastructure fixe | 60 $ | 150 $ | 450 $ |
| **Contribution** | **395 $** | **2 125 $** | **8 650 $** |

Avant acquisition, rémunération, fiscalité et support. Indicateurs à suivre : durée de vie des abonnements, résiliation, coût d'acquisition, marge après support.

---

## 8. Lancement

- **Pilote : Cameroun (FR/EN)**, puis Côte d'Ivoire et Sénégal ; extensions RDC, Bénin, France, Belgique ; anglais (Nigeria, Ghana, Kenya) vers le 4e mois.
- Canaux : vidéos « Décrivez votre enquête, F2S crée le formulaire et le tableau de bord » et « Importez votre fichier Kobo/Excel, obtenez votre rapport » ; partenariats universités, formateurs S&E, incubateurs, ONG ; bibliothèque publique de modèles sectoriels ; filigrane et QR codes des formulaires gratuits ; SEO « alternative Kobo », « analyser une enquête Kobo », « questionnaire mémoire ».
- La viralité ne repose jamais sur des mécanismes trompeurs ni sur la divulgation de réponses privées.

### Objectifs du pilote

- Au moins 70 % des utilisateurs qui commencent une création obtiennent un questionnaire utilisable.
- Au moins 90 % des fichiers aux formats annoncés passent l'import sur le corpus de test.
- 3 à 5 % de conversion gratuit → payant (premier signal, non une garantie).
- Aucun écart chiffré inexpliqué sur les jeux de données étalons des rapports.
- Marge contributive et coût d'acquisition compatibles avec la durée de vie observée.

---

## 9. Risques

| Risque | Impact | Réponse |
| --- | --- | --- |
| Kobo gratuit pour les ONG éligibles | Élevé | Se vendre comme couche d'analyse et de rapport compatible Kobo |
| Interopérabilité complexe | Élevé | Périmètre normé, corpus de fichiers de test, compte rendu de conversion |
| Hallucination analytique | Élevé | Statistiques déterministes, plans d'analyse en liste blanche, traçabilité |
| Confidentialité et conformité | Élevé | Privacy-by-design, revue juridique |
| Perte de données hors ligne | Élevé | PWA seulement en phase 6, tests sur appareils réels |
| Abus de l'offre gratuite | Moyen | Quotas par compte, projet, fichier et IA ; vérification téléphone |
| Rétention insuffisante | Élevé | Cibler les professionnels récurrents ; Pass Projet pour les ponctuels |
| Serveur mutualisé saturé | Moyen | File de tâches, pré-agrégations, trajectoire VPS/cloud |

---

## 10. Questions ouvertes (à trancher et consigner en ADR)

1. Agrégateur de paiement : CinetPay, Notch Pay ou Flutterwave (frais, délais de reversement, pays couverts) ?
2. ~~Hébergement~~ — **tranché** : hébergement mutualisé, tout en PHP (ADR 0002). Reste à choisir l'hébergeur et la région des données.
3. Générateur PDF/DOCX : **côté PHP** (Dompdf ou mPDF + PhpWord) par l'ADR 0002 — le choix Dompdf / mPDF reste à faire (test sur rapport type).
4. Nom de domaine et marque (vérifier la disponibilité de « F2S Pro »).
5. Durée de rétention des données des comptes gratuits.

---

## 11. Prochains livrables du dossier de réalisation

1. Cahier des charges détaillé et user stories par rôle.
2. **F2S Schema v1 finalisé** + matrice de compatibilité XLSForm/XForm (`docs/schema/`) — brouillon fourni.
3. Wireframes UI mobile + desktop (AI Studio, tableau de bord, rapport).
4. Contrats d'API, modèle de données SQL complet, diagrammes d'architecture.
5. Spécifications du moteur statistique + jeux de tests étalons.
6. Barèmes de quotas, crédits IA, paiements et administration.
7. Plan de recette, sécurité, performance et déploiement.

---

## 12. État d'avancement

> À mettre à jour à la fin de chaque session Claude Code.

| Date | Phase | Fait | Prochaine étape |
| --- | --- | --- | --- |
| 2026-11-04 | Cadrage | Études produit, arbitrages, manifeste, brouillon F2S Schema v1 + exemple | Phase 1 : initialiser le dépôt PHP, migrations de base, validateur de schéma + tests |
| 2026-11-04 | 1 — Socle | Projet renommé **F2S Pro** ; ADR 0002 (mutualisé) ; noyau MVC (routeur explicite, PDO, CSRF, sessions dans `storage/`, FR/EN, en-têtes de sécurité/CSP) ; migrations (plans + quotas, organisations, workspaces, utilisateurs, rôles, projets, formulaires, versions immuables, questions, file `jobs`, audit) ; inscription/connexion (limitation des tentatives) ; projets ; création d'un formulaire depuis un JSON validé (`FormValidator` = opis/json-schema + contrôles sémantiques), publication figée, nouvelle version, export JSON ; quota « questions par formulaire » ; file de tâches reprenable + CRON + déclencheur HTTP ; guide de déploiement mutualisé, archive de release, CI (MySQL 8 + MariaDB 10.6). 65 tests verts, PHPStan niveau 6. | Fin de phase 1 : import XLSX/CSV simples (PhpSpreadsheet) avec compte rendu de conversion ; changement d'organisation active et invitations ; réinitialisation de mot de passe et vérification d'e-mail |

### Prochaine tâche à donner à Claude Code

> « Lis CLAUDE.md, docs/MANIFEST.md et les ADR. Termine la phase 1 : ajoute `phpoffice/phpspreadsheet` et implémente dans `app/Services/Bridge/` la détection de format puis l'import d'un XLSX/CSV simple de **questions** vers F2S Schema v1, avec compte rendu de conversion (repris / transformé / perdu / non supporté) affiché avant confirmation, en tâche `jobs` reprenable si le fichier est gros. Ajoute les jeux étalons dans `tests/fixtures/imports/`. Mets à jour l'état d'avancement. »
