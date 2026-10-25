# Tests et éléments de validation

[English](../en/testing.md)

## Rejouer les contrôles

Python 3.9+ et PHP 8.3+ avec DOM, mbstring, fileinfo, PDO SQLite, ZIP et GD avec JPEG/PNG/WebP. Aucun paquet Python tiers requis. Depuis la racine du dépôt :

```sh
python3 tools/check_repo.py
python3 tests/run.py
node --check assets/admin.js
node --check assets/site.js
```

Si PHP n’est pas dans PATH : `python3 tests/run.py --php /chemin/absolu/vers/php`. Rapport facultatif : `python3 tests/run.py --report test-results/report.json`. Node sert aux deux vérifications de syntaxe JavaScript, pas au fonctionnement du CMS ou de la suite Python.

`tests/run.py` copie l’application dans un dossier temporaire du système, utilise un stockage distinct et un port localhost, crée des images fictives avec GD puis nettoie le tout. Il ne lit ni ne modifie le vrai dossier `storage`. Utilisez ce lanceur ; les scripts individuels dépendent de son environnement temporaire. Les comptes/mots de passe fictifs sont des données de tests, pas un compte livré. Ne les réutilisez pas.

La suite comprend 50 scénarios HTTP, 35 contrôles SQLite/médias/journaux/migration et deux contrôles d’échec/reprise : **87 vérifications**. La migration utilise des données synthétiques de schéma 1, sans stockage d’un véritable propriétaire. Sont contrôlés les originaux JPEG/PNG/WebP et leurs empreintes, les tables/révisions, refus d’accès privé, séparation/rotation des journaux, annulation SQL, anciens ZIP et absence de double migration. Le lanceur portable a réussi localement le 25 octobre 2026 sur PHP 8.3.6. Voir [le rapport enregistré](../verification-github-kit.json).

`tools/check_repo.py` repère des données d’exécution, marqueurs de configuration inachevés et liens Markdown locaux cassés. L’option `--allow-template` accepte les marqueurs uniquement dans le modèle non configuré. Elle ne remplace pas une détection complète de secrets ni la relecture des fichiers préparés pour Git. Le script attend un arbre source propre : gardez le stockage d’un site réel ailleurs.

## CI

`.github/workflows/ci.yml` lance les contrôles de dépôt/JavaScript et des jobs PHP 8.3, 8.4 et 8.5 en conteneurs, sur push vers `main`, PR et déclenchement manuel. Checkout utilise une empreinte complète vérifiée, sans identifiants persistés ; jeton en lecture seule, aucun secret de déploiement ni publication automatique. Dependabot propose mensuellement les mises à jour d’actions, à relire avant fusion.

`tests/Dockerfile` active les codecs JPEG/WebP. Pour reproduire avec Docker : `docker build --build-arg PHP_VERSION=8.3 -f tests/Dockerfile -t vv-cms-tests .`, puis `docker run --rm vv-cms-tests`. Docker était absent ici : compilation du conteneur, jobs PHP 8.4/8.5 et workflow GitHub réel restent **non validés**. Consultez leurs premiers résultats avant de présenter ces versions comme testées. Ce conteneur de tests est distinct du Dockerfile d’hébergement à la racine.

## Rapports historiques

La 1.0.0 fournie avait des rapports de 50 tests HTTP et 29 contrôles navigateur. La 1.1.0 indique 50 HTTP, 35 fonctions/migration, deux échecs/reprises et 29 navigateur : 116 au total. Ces résultats concernent leur préparation d’origine, pas une nouvelle CI GitHub. L’ancien script navigateur n’est pas fourni comme suite portable actuelle. Un nouveau passage navigateur reste manuel, en attendant une contribution dédiée.

Apache/Nginx HTTPS réels, Docker de déploiement, double-clic macOS, limites d’un hébergeur réel, audit indépendant et publication GitHub n’ont pas été exécutés pendant cette préparation. Le moteur original est conservé ; les nouveaux tests visent sa copie isolée, pas un site de production existant.
