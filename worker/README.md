# worker/ — outils Python (développement et CI uniquement)

Depuis l'[ADR 0002](../docs/adr/0002-production-shared-hosting.md), **aucun code Python ne tourne en production** (hébergement mutualisé). Ce dossier accueillera :

- `oracles/` : scripts qui produisent les valeurs de référence des tests PHP (SciPy pour les tests statistiques, `pyxform` pour valider les XLSForm exportés) dans `tests/fixtures/` ;
- plus tard, si une ADR décide du passage sur VPS, un worker qui consommera la file `jobs` avec `runner = 'python'`.
