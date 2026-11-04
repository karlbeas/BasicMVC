# ADR 0001 — Stack PHP/MySQL avec worker Python

- **Date** : 2026-11-04
- **Statut** : accepté — **partiellement remplacé par l'[ADR 0002](0002-production-shared-hosting.md)** (production sur hébergement mutualisé : pas de worker Python en production)

## Contexte

Deux propositions existaient : Next.js + Supabase + service Python (étude Claude) et PHP 8.3 + MySQL 8 + jQuery/Bootstrap (rapport Studio Redcarré). L'équipe maîtrise la stack PHP. La conversion XLSForm (`pyxform`) et les tests statistiques fiables (SciPy) n'ont pas d'équivalent mûr en PHP.

## Décision

- Application principale en PHP 8.3, MVC léger, MySQL 8, Bootstrap 5 + jQuery.
- Un worker Python séparé pour : conversions XLSForm/XForm, statistiques (pandas, SciPy, DuckDB), traitements lourds.
- Communication par une file de tâches en base (tables de jobs) traitée par CRON, jamais en synchrone dans une requête web.

## Conséquences

- Hébergement sur VPS (un mutualisé PHP seul ne suffit pas pour le worker).
- Deux suites de tests : PHPUnit et pytest.
- Contrat d'échange PHP ↔ worker en JSON, documenté et versionné dans `docs/schema/`.
