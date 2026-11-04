# Intégration continue (GitHub Actions)

`github-actions-ci.yml` exécute `composer lint` et `composer test` sur **MySQL 8 et MariaDB 10.6**, puis construit l'archive de livraison en artefact.

Il est rangé ici car l'application GitHub Claude n'a pas la permission « workflows » : elle ne peut pas créer de fichier dans `.github/workflows/`. Pour l'activer, depuis votre poste :

```bash
mkdir -p .github/workflows
git mv docs/ci/github-actions-ci.yml .github/workflows/ci.yml
git commit -m "Enable CI" && git push
```

Ou accordez la permission « Workflows » à l'application Claude dans les réglages GitHub, puis demandez à Claude de le faire.
