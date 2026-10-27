# GitHub — VoilaVoila CMS 1.4.0

## Français

Les archives précédentes et leurs tags restent inchangés. Cette livraison doit créer une nouvelle release `v1.4.0`. Aucune publication GitHub n’est effectuée par le CMS.

1. Clonez votre dépôt ou utilisez votre copie locale existante et créez une branche.
2. Appliquez les fichiers du ZIP de mise à jour à la racine du dépôt, ou comparez le ZIP complet si votre copie a des modifications. Gardez les fichiers de votre projet et vos thèmes/extensions personnalisés.
3. N’ajoutez jamais le stockage privé, les bases SQLite, les clés OTP, les exports, les identifiants de paiement ni les journaux d’un site réel.

```bash
git switch -c release/1.4.0
git status --short
git add .
git diff --cached --stat
git commit -m "Release CMS 1.4.0: SEO, migration, operations and collapsible admin"
git push -u origin release/1.4.0
```

Ouvrez une pull request vers `main`, examinez les fichiers et attendez les vérifications de votre dépôt. Les workflows livrés construisent une archive, vérifient son manifeste, puis lancent les tests PHP 8.3/8.4/8.5, les mises à jour/retours arrière et les parcours Chromium sur l’archive extraite.

Pour préparer les fichiers depuis une copie Git propre et configurée :

```bash
python3 tools/check_repo.py
python3 tools/build_release.py
python3 tools/verify_release.py dist/VoilaVoila-CMS-1.4.0.zip
python3 tools/build_update.py --previous tests/fixtures/VoilaVoila-CMS-1.3.1.zip --current dist/VoilaVoila-CMS-1.4.0.zip --output dist/VoilaVoila-CMS-1.3.1-to-1.4.0-Update.zip
```

Un exemplaire générique non configuré nécessite explicitement `--allow-template` pour la construction. Pour votre dépôt public, complétez les coordonnées du projet au lieu de désactiver ce contrôle.

Après fusion, placez un nouveau tag sur le commit fusionné et créez la release avec les nouveaux fichiers. Ces commandes sont à exécuter après votre revue ; ne déplacez pas un tag déjà publié.

```bash
git switch main
git pull --ff-only
git tag -a v1.4.0 -m "VoilaVoila CMS 1.4.0"
git push origin v1.4.0
gh release create v1.4.0 --verify-tag --title "VoilaVoila CMS 1.4.0" --notes-file RELEASE-1.4.0-FR-EN.md dist/VoilaVoila-CMS-1.4.0.zip dist/VoilaVoila-CMS-1.4.0.sha256 dist/VoilaVoila-CMS-1.3.1-to-1.4.0-Update.zip
```

La tâche de construction refuse d’écraser un fichier existant. Si vous reconstruisez après fusion, déplacez les anciens fichiers locaux hors de `dist` pour fabriquer de nouvelles archives depuis le commit final. Publiez uniquement celles dont vous avez vérifié le commit et les sommes de contrôle.

Pour la communauté, utilisez Discussions pour l’aide, les modèles d’Issues pour les reproductions et [le protocole pilote](docs/PILOT-1.4.0-FR-EN.md) pour cinq volontaires. Ne publiez pas leurs exports privés et ne promettez pas de SLA avant de disposer de la capacité correspondante.

## English

Keep earlier release archives and tags immutable. This delivery creates a new `v1.4.0` release; the CMS never publishes to GitHub automatically.

Create a branch, apply the update ZIP to the repository root and review local differences. Preserve project-specific files and custom modules. Never commit live private storage, SQLite databases, OTP keys, exports, payment credentials or logs. Use the Git commands above, open a pull request and review the checks.

The supplied workflows build and verify an archive, then test the extracted package on PHP 8.3/8.4/8.5, upgrade/rollback fixtures and Chromium journeys. The build commands above require a clean configured repository; generic distribution templates require explicit `--allow-template`.

After merging, create a new annotated tag on the merged commit and run the release commands above. Do not move an existing published tag. Builds refuse to overwrite output files: move prior local outputs out of `dist` before rebuilding from the final merged commit. Publish only archives whose source commit and checksums you have verified.

Use Discussions for support, reproducible Issues for bugs and the [five-site pilot protocol](docs/PILOT-1.4.0-FR-EN.md) for voluntary feedback. Keep participant data private and do not advertise unsupported service commitments.
