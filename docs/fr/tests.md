# Tests et preuves — 1.2.1

[English](../en/testing.md)

Prérequis de développement : Python 3.9+, PHP 8.3+ avec DOM, mbstring, fileinfo, PDO SQLite, ZIP et GD JPEG/PNG/WebP. Git sert aux tests de fabrication ; Node/Playwright servent seulement aux tests navigateur.

## Contrôles des sources

```bash
python3 tools/check_repo.py
python3 tests/release_tools.py --report test-results/release-tools.json
python3 tests/run.py --report test-results/http.json
php content/plugins/wp-porter/tests/run.php
```

Le lanceur HTTP contient 190 contrôles : 50 HTTP, 35 SQLite/médias/logs, deux échecs/reprises de migration, 80 scénarios documents/commentaires/WordPress et 23 contrôles diagnostic/sous-dossier. Les outils de release ont 28 contrôles ; WP Porter en a 44 ; chaque mise à jour/retour arrière depuis une version précédente en a 11.

Les écritures utilisent un stockage temporaire et des données synthétiques. Les mots de passe des tests ne correspondent pas à un compte livré et ne doivent pas être réutilisés.

## Contrôler le ZIP installable

Enregistrer les sources dans Git avant fabrication. Le constructeur inclut l'identifiant du commit réel et refuse les sorties existantes.

```bash
python3 tools/build_release.py --version 1.2.1
python3 tools/verify_release.py dist/VoilaVoila-CMS-1.2.1.zip --expected-version 1.2.1 --extract test-results/from-zip
python3 test-results/from-zip/tools/verify_release.py --directory test-results/from-zip
python3 test-results/from-zip/tests/run.py --report test-results/extracted-http.json
```

Choisir un dossier d'extraction neuf si la destination contient déjà des fichiers. La vérification de l'installation couvre les fichiers déclarés et les copies résiduelles connues, pas les données d'exécution ni les ajouts personnalisés. Une somme de contrôle ne prouve pas l'identité de l'auteur.

## Parcours navigateur

Installer les dépendances de développement épinglées puis Chromium :

```bash
cd tests
npm ci --ignore-scripts
npx playwright install --with-deps --only-shell chromium
cd ..
python3 tests/browser_120.py --report test-results/browser.json
```

Les 18 scénarios s'exécutent par défaut dans un `/cms` temporaire : propriétaire, activation de Commentaires, documents, ordinateur/mobile, FR/EN et rendu sans JavaScript. Le serveur et les données de test sont supprimés à la fin. `VV_BROWSER_EXECUTABLE` permet un Chromium compatible déjà installé ; le rapport indique sa version réelle.

## Mise à jour et retour arrière

La CI fabrique des jeux de sources de confiance depuis les commits épinglés de la version 1.1 et de la version 1.2 corrigée. Ces jeux ne sont pas présentés comme identiques octet par octet aux anciens ZIP publiés. Avec les archives de test correspondantes :

```bash
python3 tests/upgrade_release.py --previous-zip /chemin/vers/previous-1.1.zip --previous-version 1.1.0 --report test-results/upgrade-1.1.json
python3 tests/upgrade_release.py --previous-zip /chemin/vers/previous-1.2.zip --previous-version 1.2.0 --report test-results/upgrade-1.2.json
```

Ces tests exécutent du PHP : utiliser uniquement des sources de confiance. Ils vérifient identifiants, contenus, réglages et fichier personnalisé, puis restaurent le code ancien et son état privé compatible.

## CI et qualification

Le workflow vérifie les sources/outils, fabrique un ZIP commun, le valide puis le transmet aux jobs PHP 8.3/8.4/8.5 et Chromium. Le conteneur PHP est construit depuis le paquet extrait et vérifie les fichiers déclarés avant les tests, y compris les ZIP du SDK.

Le rapport JSON de livraison identifie les exécutions locales réelles. La nouvelle exécution Actions reste à confirmer après publication du commit. Les [pilotes d'hébergement](../HOSTING-PILOT-FR-EN.md) attendent les offres réelles. Les rapports des [versions historiques](../history/README.md) sont des références distinctes.
