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

- **Limites temporaires de l'offre gratuite (2026-11-04, migration 0007)** à la demande du porteur de projet, en attendant la console de contrôle des utilisateurs et des forfaits : **70 questions par formulaire, 5 campagnes publiées** (ouvertes simultanément et par 90 jours glissants) ; 100 réponses par campagne inchangé. ⚠ Le Pass Projet (150 questions, 1 campagne) devient moins généreux que le gratuit sur le nombre de campagnes : à revoir avec la console.
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

Base : API Mammouth `https://api.mammouth.ai/v1`, clé dans `MAMMOUTH_API_KEY`. Tarifs en $ par million de tokens, vérifiés le 2026-11-04 (https://model-explorer.mammouth.ai/).

| Usage | Modèle par défaut | Secours (autre fournisseur) | Entrée / Sortie ($/M) du modèle par défaut |
| --- | --- | --- | --- |
| Génération et retouche de questionnaire | `claude-haiku-5-5` | `gpt-6-luna` | 0,10 / 0,50 |
| Structuration Word/PDF → formulaire | `gpt-6-luna` | `glm-5.3-flash` | 0,10 / 0,50 |
| Plan d'analyse (« Ask My Survey ») | `claude-haiku-5-5` | `gpt-6-luna` | 0,10 / 0,50 |
| Synthèses et rapport standard | `claude-haiku-5-5` | `gpt-6-luna` | 0,10 / 0,50 |
| Rapport premium (option en crédits) | `claude-sonnet-5-5` | `gpt-6.1-sol` | 2 / 10 |
| Thèmes des réponses ouvertes | `text-embedding-3-small` + `gpt-6-luna` | — | 0,022 (embeddings) |

> Identifiants et prix **vérifiés le 2026-11-04** sur https://model-explorer.mammouth.ai/ (liste fournie par le porteur de projet) ; `claude-haiku-5-5` retenu pour les tâches simples à la demande du porteur de projet : table complète dans `config/ai_prices.php` (107 modèles). `qwen3.8-flash`, prévu initialement, n'existe pas chez Mammouth : remplacé par `glm-5.3-flash`. Un modèle absent de la table est compté au tarif prudent de 2 / 10 $ pour que le plafond quotidien s'applique toujours.

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
| **0 — Socle** ✅ | fait (v0.1–0.2) | Squelette MVC, auth, organisations/workspaces/rôles, projets, migrations, **F2S Schema v1 + validateur**, versions immuables, quotas, file de tâches, installateur `public_html` | Tests verts ; un formulaire JSON valide peut être créé, validé, stocké |
| **1 — AI Studio + Collect + Bridge** | J1–J15 | Génération IA, éditeur visuel, Dashboard Before Data, publication web (lien/QR), règles courantes, soumissions ; **imports Excel/CSV/XLSForm, Kobo, ODK** avec compte rendu de conversion | Un utilisateur crée un formulaire par prompt ou par import et reçoit des réponses |
| **2 — Analytics + équipes** | J16–J28 | Niveaux 1–3, filtres, tableaux croisés, alertes qualité, AI Insights, rapport PDF/Word ; **invitations de collaborateurs, mot de passe oublié, changement d'organisation** | Tableau de bord automatique juste sur les jeux étalons |
| **3 — Crédits IA + pilote** | J29–J40 | Crédits IA (barème §2), administration, export XLSForm, tests, **pilote Cameroun** (accès gratuit ou offres attribuées manuellement) | Pilote en ligne, objectifs §8 mesurés |
| **4 — Connecteurs + terrain** | J41–J75 | Connecteur Google Forms, XForm XML avancé, synchronisation, Ask My Survey ; PWA hors ligne, supervision terrain, statistiques avancées (niveaux 4–5), SPSS/Stata, tests de charge | Hors ligne validé sur appareils réels ; import Kobo → rapport en moins de 5 min |
| **5 — Identité visuelle** | J76–J85 | Mise à jour graphique : logo, charte (couleurs, styles), polices (servies localement), direction artistique de l'interface, des formulaires publics, des rapports et des e-mails | Charte appliquée partout ; contrastes conformes WCAG AA |
| **6 — Multilingue** | J86–J95 | Interface et formulaires en **FR, EN, DE, ES, PT** : fichiers `lang/{de,es,pt}.php`, sélecteur de langue, langues des questionnaires (le schéma est déjà multilingue), formats de dates et de nombres par langue, traduction IA des formulaires (barème §2) | Toutes les clés traduites (test de parité des fichiers de langue) ; un formulaire publié dans les 5 langues |
| **7 — Paiement** | J96–J110 | Paiement Mobile Money + carte ([ADR 0003](adr/0003-payment-gateway-abstraction.md), CinetPay d'abord), souscription Pass Projet / Pro, activation après vérification serveur du webhook, expiration et retour en gratuit | Premiers paiements réels vérifiés |

Priorité commerciale : le **pont Kobo/Excel → tableau de bord → rapport IA** est la fonction la plus rapide à vendre.

> Feuille de route réordonnée le 2026-11-04 à la demande du porteur de projet : création/collecte/imports d'abord, puis analyse et équipes, crédits IA et pilote, connecteurs et hors ligne, identité visuelle, multilingue (FR/EN/DE/ES/PT), et **le paiement en dernier**. Tant que le paiement n'existe pas, les offres payantes s'attribuent manuellement (table `organizations.plan_id`).

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

1. ~~Agrégateur de paiement~~ — **tranché** : abstraction multi-agrégateurs, premier connecteur CinetPay ([ADR 0003](adr/0003-payment-gateway-abstraction.md)). Reste à ouvrir le compte marchand.
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
| 2026-11-04 | 1 — Socle (**v0.1.0**, `f2s-pro-0.1.0.zip`) | Projet renommé **F2S Pro** ; ADR 0002 (mutualisé) ; noyau MVC (routeur explicite, PDO, CSRF, sessions dans `storage/`, FR/EN, en-têtes de sécurité/CSP) ; migrations (plans + quotas, organisations, workspaces, utilisateurs, rôles, projets, formulaires, versions immuables, questions, file `jobs`, audit) ; inscription/connexion (limitation des tentatives) ; projets ; création d'un formulaire depuis un JSON validé (`FormValidator` = opis/json-schema + contrôles sémantiques), publication figée, nouvelle version, export JSON ; quota « questions par formulaire » ; file de tâches reprenable + CRON + déclencheur HTTP ; guide de déploiement mutualisé, archive de release, CI (MySQL 8 + MariaDB 10.6). 65 tests verts, PHPStan niveau 6. | Fin de phase 1 : import XLSX/CSV simples (PhpSpreadsheet) avec compte rendu de conversion ; changement d'organisation active et invitations ; réinitialisation de mot de passe et vérification d'e-mail |
| 2026-11-04 | 1 — Socle (**v0.2.0**, `f2s-pro-0.2.0-public_html.zip`) | Installation « prête à l'emploi » dans `public_html` : zip à extraire tel quel, assistant web `/install` (vérifications, code d'installation à usage unique, test de la base, migrations, écriture de `.env`, jeton CRON, auto-désactivation), `.htaccess` de refus dans chaque dossier interne. Testé sous Apache. 71 tests. | Phase 1 (v0.3.0) : éditeur visuel, lien/QR, collecte des réponses ; puis v0.4.0 : IA et imports |
| 2026-11-04 | 1 — Collecte (**v0.3.0**, `f2s-pro-0.3.0-public_html.zip`) | **Éditeur visuel** (sections, 13 types, choix, échelles, matrices, traductions, logique `relevant`/`constraint`, rôle analytique) et création d'un formulaire vierge ; **campagnes** sur une version publiée (lien court `/f/xxxx`, QR code SVG local, partage WhatsApp, date de fin, plafond de réponses, code d'accès, clôture/réouverture) avec quotas de l'offre (1 campagne / 90 jours et 100 réponses en gratuit) ; **formulaire public mobile** (FR/EN, logique conditionnelle en direct, « Autre (préciser) », filigrane gratuit, anti-doublon `submission_uuid`, pot de miel, aucune IP stockée) ; **validation serveur** complète (types, choix, obligatoire si pertinent, contraintes, calculs) via un évaluateur d'expressions XLSForm sûr, jumeau PHP/JS testé sur le même jeu de cas ; stockage brut + `data_json` + projection `answers` ; liste des réponses ; **exports CSV (Excel FR, anti-injection de formules) et JSON** ; migrations appliquées automatiquement par le CRON après une mise à jour (page « mise à jour en cours » entre-temps). 128 tests, test navigateur de bout en bout. | v0.4.0 : création par IA et imports Excel/CSV/XLSForm/Kobo/ODK |
| 2026-11-04 | 1 — IA + imports (**v0.4.0**, `f2s-pro-0.4.0-public_html.zip`) | **Création par IA** (description → plan de sections → questionnaire F2S validé, une réparation automatique max, tâche reprenable suivie en direct) et **retouche par IA** d'un brouillon ; `AiGateway` (Mammouth, modèle de secours, journal `token_usage` avec coût, budget quotidien) ; **crédits IA** (`credit_ledger`, barème §2, débit unique après succès) ; **imports** XLSForm (Kobo/ODK/SurveyCTO), listes de questions Excel/CSV (UTF-8/Windows-1252, ; ou ,), JSON, avec **compte rendu** repris/transformés/non repris/non pris en charge et **confirmation** ; lecteur XLSX maison (ADR 0004) ; jeux étalons générés par openpyxl. 148 tests, test navigateur de l'import. | Phase 2 : tableau de bord d'analyse, croisements, alertes qualité, rapport PDF/Word ; invitations, mot de passe oublié, changement d'organisation |
| 2026-11-04 | 1 — Correctif (**v0.4.1**, `f2s-pro-0.4.1-public_html.zip`) | Modèles et prix **vérifiés** sur la liste Mammouth : table complète des 107 modèles (`config/ai_prices.php`), secours d'un autre fournisseur pour chaque usage (`claude-haiku-5-5` pour les questionnaires), embeddings à 0,022 $ ; un modèle inconnu n'est plus compté gratuit (le plafond quotidien s'applique toujours). Clés `.env` facultatives `AI_MODEL_*_FALLBACK` ajoutées (aucune action requise). | Phase 2 (v0.5.0) |
| 2026-11-04 | 1 — Correctif (**v0.4.2**, `f2s-pro-0.4.2-public_html.zip`) | **Génération IA bloquée en production corrigée** : le CRON n'exécutait jamais l'étape longue (limite de temps posée par `worker-dispatch.php` + boucle de remises en file) ; délai d'appel IA porté à 120 s ; étapes longues confiées au CRON (CLI sans limite), la page de suivi ne lance que ce qui tient dans ses 30 s ; points de reprise après chaque étape ; erreurs définitives (clé refusée, modèle inconnu) arrêtées avec la cause ; tâches tuées par l'hébergeur relancées après ~3 min même avec l'ancien `JOBS_STALE_AFTER=600` ; page de suivi explicite (attente du CRON, CRON absent, nouvelle tentative et raison). `claude-haiku-5-5` devient le modèle des tâches simples (secours `gpt-6-luna`). Rejoué sous Apache avec un faux Mammouth HTTPS lent (40 s) : tâches bloquées reprises et terminées. 157 tests. | Phase 2 (v0.5.0) |
| 2026-11-04 | 1 — Correctif (**v0.4.3**, `f2s-pro-0.4.3-public_html.zip`) | **Plus aucune dépendance au CRON** (retour de production : CRON absent chez l'hébergeur) : génération IA **section par section**, retouche **partie par partie**, réparation des seules parties fautives après corrections automatiques — uniquement des appels courts (≈ 10–20 s) enchaînés par la page de suivi ; assembleur (identifiants uniques, listes partagées/renommées) ; processus tué = tentative comptée ; migrations appliquées à la première visite ; scripts compatibles PHP CGI des CRON ; scripts CSS/JS versionnés (fin du cache obsolète). Vérifié sous Apache **sans CRON** avec un faux Mammouth HTTPS : création 4 sections en ~40 s, retouche, migration par visite. 161 tests. (ADR 0004, amendement) | Phase 2 (v0.5.0) |
| 2026-11-04 | 1 — Correctif (**v0.4.4**, `f2s-pro-0.4.4-public_html.zip`) | Retour de production : « L'IA n'a pas produit un questionnaire valide ». **Normaliseur déterministe** des sorties IA (`FormNormalizer`) : textes en chaîne simple, synonymes de types, choix en ligne, codes non ASCII (conditions mises à jour), booléens en texte, rôles inconnus, identifiants renommés (références suivies) ; propriétés inconnues conservées dans `x_source`. Exemple complet dans le prompt de section ; message d'échec avec la première erreur réelle. Vérifié sous Apache avec un faux Mammouth volontairement « désordonné ». 163 tests. | v0.5.0 proposée : créateur IA express (un seul champ, sections en parallèle) + questions avec envoi de fichier |
| 2026-11-04 | 1 — **v0.4.5** (`f2s-pro-0.4.5-public_html.zip`) | **Dépublication** d'un formulaire (campagnes closes, liens publics fermés, nouvelle version brouillon ; version publiée et réponses intactes) ; **suppression** d'un formulaire (suppression logique `archived_at`, confirmation par saisie de l'identifiant, propriétaire/admin, liens publics morts, réponses conservées en base) ; **limites temporaires gratuites** 70 questions / 5 campagnes (migration 0007, appliquée à la première visite). Vérifié dans le navigateur sous Apache. 165 tests. | v0.5.0 proposée : créateur IA express + questions avec envoi de fichier ; console de contrôle des utilisateurs et forfaits à planifier |

### Prochaine tâche à donner à Claude Code

> « Lis CLAUDE.md, docs/MANIFEST.md et les ADR. Phase 2, v0.5.0 : tableau de bord automatique d'une campagne (niveaux 1–2 : effectifs, %, distributions, moyenne/médiane/dispersion, valeurs manquantes, réponses par jour ; alertes qualité : doublons, durées trop courtes, incomplets), calculés en SQL/PHP sur `answers` avec dénominateurs et filtres affichés, graphiques adaptés au type (barres, histogramme, courbe), petites cellules n < 5 masquées en partage public ; jeux étalons avec valeurs de référence calculées par Python (oracle). Livre le zip public_html. »
