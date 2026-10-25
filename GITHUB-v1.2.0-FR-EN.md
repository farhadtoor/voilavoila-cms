# Publier VoilaVoila CMS 1.2.0 sur GitHub / Publish on GitHub

Ce guide prépare la publication ; aucun dépôt, push, tag distant, message ou release n’a été créé automatiquement. / This guide prepares publication; no repository, push, remote tag, message or release was created automatically.

## FR — Fichiers à utiliser

- **VoilaVoila-CMS-1.2.0.zip** : sources complètes, installation, nouveaux plugins, docs FR/EN, tests, CI et modèles GitHub. Décompressez et travaillez dans `voila-cms`.
- **VoilaVoila-CMS-Update-1.1.0-to-1.2.0.zip** : fichiers ajoutés/modifiés pour une installation 1.1 ; conserve les données car aucun stockage d’exécution n’est inclus. Vérifiez les modifications locales avant remplacement.
- **SHA256SUMS-1.2.0.txt** : empreintes des livrables. Une empreinte contrôle les octets ; ce n’est pas une signature d’auteur.
- Les notes à copier dans la Release sont `docs/releases/v1.2.0.md`.
- La procédure de mise à jour serveur est `docs/fr/mise-a-jour-1.2.md`. Le patch n’est pas un nouveau programme d’installation.

N’envoyez jamais la base, les sauvegardes de votre site, les journaux, la clé d’installation, le code de récupération ou des fichiers d’authentification sur GitHub. Le pack fourni contient uniquement les deux fichiers de protection initiaux de `storage`.

## FR — Informations GitHub prêtes à copier

**Nom proposé** : `voilavoila-cms`

**Description française** :
> CMS personnel PHP/SQLite à propriétaire unique : pages, articles, médias, documents téléchargeables, thèmes, extensions et commentaires modérés. Migration WordPress hors ligne.

**Description anglaise** :
> Single-owner PHP/SQLite CMS with pages, posts, media, downloadable documents, native themes/plugins, moderated comments and offline WordPress migration.

GitHub n’affiche qu’une description principale : choisissez l’anglais et gardez le français dans le README.

**Topics** :
`php`, `sqlite`, `cms`, `self-hosted`, `single-user`, `plugins`, `themes`, `wordpress-migration`, `comments`, `open-source`.

**Titre de PR** :
`feat: add documents, moderated comments and offline WordPress migration`

**Message de commit** :
`feat: prepare CMS 1.2.0 with documents, comments and WordPress import`

**Titre de release** :
`VoilaVoila CMS 1.2.0 — Documents, comments & WordPress migration`

**Discussion d’annonce** :
> La version 1.2.0 ajoute les documents PDF/DOCX/ZIP/CSV avec validation et téléchargement contrôlé, un plugin facultatif de commentaires modérés et un import WordPress hors ligne avec médias et rapport. Le projet garde un seul propriétaire. Les guides sont disponibles en français et en anglais. Les retours sur des installations d’essai PHP 8.3+ sont les bienvenus ; indiquez l’hébergeur, la version PHP, les extensions et des étapes reproductibles sans joindre de données privées.

## FR — Personnaliser les fichiers

Dans le dossier des sources, remplacez les exemples par votre **vrai identifiant GitHub**, votre dépôt, le responsable du code et un contact privé de sécurité fonctionnel :

```sh
python3 tools/configure_project.py \
  --github "YOUR_LOGIN/voilavoila-cms" \
  --maintainer "YOUR_LOGIN" \
  --contact "security@your-domain.example"
python3 tools/check_repo.py
```

Remplacez cet e-mail d’exemple par votre contact privé réel. Activez aussi les signalements privés GitHub. Une URL HTTPS de contact privée fonctionnelle est également acceptée. Le script personnalise les modèles `GITHUB_OWNER`, `GITHUB_REPOSITORY`, `GITHUB_MAINTAINER` et `MAINTAINER_CONTACT` dans les docs/politiques ; il ne touche pas aux données du site et ne contacte pas GitHub.

Les scripts, README, CONTRIBUTING, SECURITY, SUPPORT, CODE_OF_CONDUCT, modèles d’issues/PR, CODEOWNERS, Dependabot et workflow CI sont inclus. Vérifiez les informations publiques avant commit.

## FR — Si votre dépôt GitHub existe déjà

1. Téléchargez une copie récente et créez une branche. Le dossier de travail doit être propre avant de copier la nouvelle version :

```sh
git clone https://github.com/YOUR_LOGIN/voilavoila-cms.git
cd voilavoila-cms
git status --short
git switch -c release/1.2.0
```

2. Copiez **le contenu** du nouveau dossier `voila-cms` dans ce clone, fichiers cachés compris, sans ajouter le dossier parent. Gardez le dossier `.git` existant. Comparez vos personnalisations ; cette copie ne constitue pas une fusion automatique des modifications locales.

3. Lancez la personnalisation ci-dessus, puis les contrôles :

```sh
python3 tools/check_repo.py
php -v
python3 tests/run.py
node --check assets/admin.js
node --check assets/site.js
git diff --stat
git status --short
```

PHP doit être 8.3+ avec DOM, mbstring, fileinfo, PDO SQLite, GD JPEG/WebP, ZIP et tokenizer pour le test Porter. Python et Node servent aux contrôles de développement ; ils ne sont pas nécessaires pour exploiter le CMS. Les tests utilisent des copies temporaires et des données synthétiques. Si PHP n’est pas installé, utilisez l’image de test Docker décrite dans `docs/fr/tests.md`.

4. Après revue du diff :

```sh
git add .
git commit -m "feat: prepare CMS 1.2.0 with documents, comments and WordPress import"
git push -u origin release/1.2.0
```

5. Sur GitHub, créez la Pull Request vers la branche principale réelle du dépôt. Vérifiez les jobs CI et les fichiers modifiés, puis fusionnez. Le nom `main` ci-dessous est à adapter si votre dépôt utilise un autre nom.

6. Depuis la branche fusionnée, créez le tag **une seule fois** :

```sh
git switch main
git pull --ff-only
git tag -a v1.2.0 -m "VoilaVoila CMS 1.2.0"
git push origin v1.2.0
```

Gardez les tags et assets 1.0.0/1.1.0 existants. Aucun `--force` ni retag des versions publiées n’est nécessaire.

## FR — Si vous n’avez pas encore de dépôt

Sur GitHub, cliquez sur **New repository**, nommez-le `voilavoila-cms` et choisissez sa visibilité. Pour la procédure Git ci-dessous, créez-le vide : sans README, licence ou gitignore généré, car ces fichiers existent déjà dans le pack.

Dans le nouveau dossier des sources, personnalisez et contrôlez les fichiers, puis :

```sh
git init -b main
git add .
git commit -m "feat: initial public source for VoilaVoila CMS 1.2.0"
git remote add origin https://github.com/YOUR_LOGIN/voilavoila-cms.git
git push -u origin main
git tag -a v1.2.0 -m "VoilaVoila CMS 1.2.0"
git push origin v1.2.0
```

Ce démarrage fournit un snapshot 1.2.0. Il ne recrée pas artificiellement l’historique 1.0/1.1. Si vous avez utilisé le précédent kit d’import historique, suivez la procédure **dépôt existant**.

GitHub Desktop peut remplacer les étapes Git : ouvrir/cloner le dépôt, créer une branche, copier les sources, vérifier le diff, commit, push, PR. `gh` est facultatif.

## FR — Créer la Release dans le navigateur

1. Ouvrez **Releases → Draft a new release**.
2. Sélectionnez le tag existant **v1.2.0** correspondant au code relu.
3. Collez le titre et le contenu de `docs/releases/v1.2.0.md`.
4. Joignez le ZIP complet testé, le patch et `SHA256SUMS-1.2.0.txt`.
5. Si vous personnalisez/reconstruisez les archives, recalculez leurs empreintes : elles doivent correspondre exactement aux fichiers joints. Ne remplacez jamais silencieusement un asset déjà publié.
6. Enregistrez le brouillon, vérifiez les téléchargements. Publiez après la CI et votre essai sur hébergement. Si vous distribuez d’abord une version pilote, cochez **Pre-release** ; marquez **Latest** lorsque vous avez retenu cette version comme version courante.

Le ZIP d’installation dédié est le téléchargement à recommander aux utilisateurs. Les liens automatiques « Source code » de GitHub sont des archives du tag et ne portent pas nécessairement le même nom ou la même empreinte.

Reconstruction locale à partir de sources personnalisées, déjà commitées et sans modification en attente :

```sh
python3 tools/build_release.py --version 1.2.0
```

Le ZIP et son fichier `.sha256` sont créés dans `dist`, sans publication. La version doit correspondre à `VV_VERSION`. Le constructeur refuse les sorties existantes et les données d’exécution ; utilisez le tag/commit voulu. Pour une future modification du code, augmentez la version au lieu de changer 1.2.0 publiée.

Vérifier les fichiers livrés sur macOS ou Linux, depuis le dossier qui les contient :

```sh
shasum -a 256 -c SHA256SUMS-1.2.0.txt
```

## FR — Wiki et communauté

Dans Settings, activez Issues et Discussions, puis Wiki si disponible pour le dépôt. Créez des pages Installation, Mise à jour, Documents, Commentaires, Migration WordPress et Commandes à partir des guides. Le Wiki est un dépôt Git séparé ; une page doit être créée avant son clonage.

Propositions de labels : `bug`, `enhancement`, `documentation`, `good first issue`, `help wanted`, `security`, `wordpress-import` et `hosting`. Un signalement de vulnérabilité doit passer par le canal privé défini dans SECURITY, pas par une issue publique.

Présentez la licence MIT existante sans attribuer cette licence aux éléments graphiques exclus ou aux thèmes/plugins tiers. Les portages WordPress doivent conserver leurs notices et faire l’objet d’une vérification des droits avant redistribution.

## EN — Files and repository information

The full ZIP includes source, installation, optional plugins, FR/EN docs, tests, CI and GitHub templates. The update ZIP contains changed/added files from original 1.1.0 without runtime storage. Review local customizations before replacing files.

Suggested repository: `voilavoila-cms`. Use the English description, topics, PR/commit/release titles above. Release notes: `docs/releases/v1.2.0.md`. Server upgrade: `docs/en/upgrade-1.2.md`. Never publish databases, backups, logs, installation/recovery keys or credentials.

Personalize with `tools/configure_project.py --github OWNER/REPO --maintainer LOGIN --contact REAL_CONTACT`, then `python3 tools/check_repo.py`. This configures documentation/policies locally; it never contacts GitHub. Enable private reporting when using the security-advisories URL.

### Existing repository

Clone current source, ensure a clean working tree, create `release/1.2.0` and copy the contents of the new `voila-cms` folder into it, including dotfiles but preserving the existing `.git`. Review your custom changes manually.

Run the commands in the existing-repository section: repository checks, PHP version, isolated tests and JS syntax. Review the diff, commit, push the branch and open a PR. After checks and review, merge into the real default branch. Pull that branch, create annotated `v1.2.0` once and push only that tag. Keep published 1.0/1.1 tags/assets unchanged; never force-push them.

### New repository

Create an empty GitHub repository without generated README/license/gitignore. In the personalized source folder, run the new-repository command block above. This makes a current 1.2.0 snapshot; it does not invent earlier history. If you used the earlier history-import kit, use the existing-repository procedure instead. GitHub Desktop is an alternative; GitHub CLI is optional.

### Release

Use **Releases → Draft a new release**, select existing `v1.2.0` and paste the release title/notes. Attach the tested full ZIP, update ZIP and checksums. Recalculate hashes for customized/rebuilt files. Save a draft, verify downloads and publish after CI and your hosting pilot. Use **Pre-release** for a pilot if desired and **Latest** when selecting the current release.

Dedicated installation ZIPs are the recommended downloads. GitHub-generated source archives may differ in name and hash. The local release builder requires committed clean source and matching `VV_VERSION`, creates `dist` outputs and never uploads. Do not modify already published assets; bump the code version for later changes.

PHP 8.3+ plus extensions is the runtime requirement. Python, Node and Docker are development tools. CI for PHP 8.3/8.4/8.5 is supplied; actual hosted CI remains to run on your repository.

Enable Issues/Discussions and Wiki where available. Suggested wiki pages: Installation, Upgrade, Documents, Comments, WordPress migration and Commands. The Wiki is a separate repository and needs an initial page before cloning. Use the existing bilingual contribution, conduct, security and support policies. Report vulnerabilities privately. Retain third-party license notices before redistribution.

## Références / References

- [GitHub: managing releases](https://docs.github.com/en/repositories/releasing-projects-on-github/managing-releases-in-a-repository)
- [GitHub: adding or editing Wiki pages](https://docs.github.com/en/communities/documenting-your-project-with-wikis/adding-or-editing-wiki-pages)
- [GitHub: adding local code](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github)
