# Publier les deux versions sur GitHub

[English](../en/publishing.md)

Ce guide s’adresse au propriétaire du projet. Le kit prépare des fichiers locaux ; il ne crée pas de dépôt, n’envoie pas de message et ne publie pas de version. Vous choisissez quand exécuter les commandes GitHub.

## 1. Préparer votre identité et le dépôt

Installez Git et Python 3 ; GitHub CLI (`gh`) est facultatif mais utilisé ci-dessous. Choisissez votre nom public de commit, e-mail Git (l’adresse noreply GitHub est acceptée), propriétaire/nom du dépôt et un **contact privé fonctionnel** pour les signalements de conduite/sécurité. Une adresse noreply ne reçoit pas les signalements.

Avant diffusion publique, relisez LICENSE/NOTICE et les droits du logo PASS. Les mentions originales sont conservées ; le copyright fourni n’indique pas de titulaire. Le titulaire réel peut préciser son crédit. Ne présentez pas le logo PASS comme étant sous MIT. Le modèle source ne contient ni compte installé, base, médiathèque ou journal privé.

Décompressez le kit complet, ouvrez un terminal dans `VoilaVoila-GitHub-Kit` et remplacez toutes les valeurs d’exemple :

```sh
python3 prepare-repository.py --github "farhadtoor/voilavoila-cms" --name "Votre nom public" --email "vous@example.com" --contact "contact@example.com"
```

Le résultat est créé par défaut à côté du kit, dans `../voilavoila-cms-git` ; un dossier existant est refusé. Le script vérifie les ZIP originaux, importe la 1.0.0 et crée `v1.0.0`, importe la 1.1.0 et crée `v1.1.0`, puis ajoute les documents/tests/CI configurés sur `main`. Les commits d’import utilisent votre identité et la date réelle de l’import, sans inventer l’ancien développement. Les tags précèdent le commit documentaire. Aucun dépôt distant ni envoi n’est effectué.


Pour un dépôt d’organisation, ajoutez `--maintainer votre-login-personnel` afin que CODEOWNERS désigne une personne et non l’organisation.

## 2. Relire localement

```sh
cd ../voilavoila-cms-git
python3 tools/check_repo.py
git status --short
git log --oneline --decorate -3
git tag --list
git ls-files storage
git diff v1.1.0 --stat
```

`git ls-files storage` doit montrer uniquement `storage/.htaccess` et `storage/index.php`. Vérifiez liens, contact et NOTICE. Si PHP est disponible, exécutez `python3 tests/run.py` ; le lanceur utilise une copie temporaire. Ne créez pas d’installation réelle dans le dépôt source avant publication et ne forcez pas l’ajout du stockage avec `git add -f`.

## 3. Créer le dépôt et envoyer

Authentifiez-vous sur votre ordinateur :

```sh
gh auth login
gh auth setup-git
gh repo create "farhadtoor/voilavoila-cms" --public --source=. --remote=origin --push
git push origin v1.0.0 v1.1.0
```

`--public` rend le code public : exécutez cette commande après relecture. Autre méthode : créez un dépôt **vide** sur le site GitHub, sans README, licence ou gitignore ajoutés automatiquement, puis :

```sh
git remote add origin https://github.com/farhadtoor/voilavoila-cms.git
git push -u origin main
git push origin v1.0.0 v1.1.0
```

Choisissez une méthode de création. Ne refaites pas `git init` dans le dossier préparé. Si votre dépôt distant possède déjà un historique, utilisez un nouveau dépôt vide pour cet import ou réconciliez les historiques volontairement ; aucune commande ici ne l’écrase de force. Préférez le terminal ou GitHub Desktop à l’envoi de fichiers par navigateur, pour garder historique, tags et fichiers cachés.

## 4. Régler le projet avant annonce

- Renseignez About et les sujets `cms`, `php`, `sqlite`, `self-hosted`, `single-user`, `themes`, `plugins`.
- Activez Issues et Discussions. Créez Announcements, Q&A, Ideas et Show and tell. Le guide communautaire contient le texte d’accueil.
- Activez le signalement privé : Settings → Security and quality → Advanced Security → Private vulnerability reporting. Les libellés peuvent évoluer. Vérifiez le lien privé ; SECURITY.md ne suffit pas à activer la fonction.
- Consultez les premiers résultats Actions. La CI cible PHP 8.3/8.4/8.5 ; seul le passage local PHP 8.3.6 enregistré est déjà terminé dans ce kit.
- Une fois les jobs verts, protégez `main` avec les contrôles requis correspondants. Gardez les PR comme pratique normale ; seul, vous ne pouvez pas approuver votre propre PR : n’exigez pas un second relecteur inexistant. Limitez force-push et suppression. Envisagez la protection des tags `v*` après l’import initial.
- Le workflow ne demande que la lecture. Gardez les secrets de production hors du projet. Relisez les PR Dependabot. Les fichiers de verrouillage des dépendances de workflow peuvent être évalués ensuite ; le kit utilise actuellement une empreinte complète de checkout.
- Ajoutez les labels proposés dans le guide communautaire. Wiki/Projects peuvent attendre un besoin concret. GitHub Pages peut servir une documentation statique distincte, pas le CMS PHP.

## 5. Préparer les deux Releases en brouillon

Depuis `voilavoila-cms-git`, en conservant le kit décompressé à côté :

```sh
gh release create v1.0.0 "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.0.0.zip" "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.0.0.sha256" --verify-tag --draft --latest=false --title "VoilaVoila CMS 1.0.0 — initial release" --notes-file docs/releases/v1.0.0.md
gh release create v1.1.0 "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.1.0.zip" "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.1.0.sha256" --verify-tag --draft --latest --title "VoilaVoila CMS 1.1.0 — SQLite, logs, original media" --notes-file docs/releases/v1.1.0.md
```

Adaptez les chemins si le dossier de sortie change. Via le site : Releases → Draft a new release, choisissez le tag existant, collez la note bilingue et joignez son ZIP avec le `.sha256`. Publiez la 1.0.0 comme historique, puis la 1.1.0 comme **Latest**. La 1.0.0 ne doit pas devenir la version courante. Les ZIP d’installation sont inchangés ; les archives source automatiques GitHub représentent chaque instantané importé. Les documents communautaires actuels sont sur `main`.

Dans le dossier de téléchargement, contrôlez un ZIP avec `sha256sum -c VoilaVoila-CMS-1.1.0.sha256` sous Linux ou `shasum -a 256 -c VoilaVoila-CMS-1.1.0.sha256` sous macOS. En PowerShell : comparez `Get-FileHash .\VoilaVoila-CMS-1.1.0.zip -Algorithm SHA256` au fichier fourni. Une empreinte détecte une différence de fichier, sans prouver l’identité de son auteur.

## 6. Accueillir les premiers utilisateurs

Suivez le [plan communautaire](communaute.md). Commencez par quelques installations pilotes ; demandez version PHP, type d’hébergement et résultat anonymisé. Les textes d’annonce sont prêts à adapter une fois les canaux d’aide/sécurité opérationnels. Le kit n’a publié aucun message ou contenu GitHub.

## Références officielles vérifiées

- [Import d’un code local](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github)
- [CLI : repo create](https://cli.github.com/manual/gh_repo_create)
- [CLI : release create](https://cli.github.com/manual/gh_release_create)
- [Signalement privé](https://docs.github.com/en/code-security/how-tos/report-and-fix-vulnerabilities/configure-vulnerability-reporting/configure-for-a-repository)
- [Sécuriser Actions](https://docs.github.com/en/actions/reference/security/secure-use)
- [Licence du dépôt](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/licensing-a-repository)

Préparation du 25 octobre 2026. Revérifiez les libellés GitHub et dépendances si vous publiez plus tard.
