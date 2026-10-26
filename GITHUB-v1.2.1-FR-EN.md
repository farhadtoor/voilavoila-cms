# GitHub — VoilaVoila CMS 1.2.1 / FR + EN

26 October 2026. Source de départ / baseline: `8467df9e936951b9e5a4a5bde0d0221aac353bc8` on `main`.

## Français

### 1. Appliquer le patch dans le dépôt existant

Enregistrer ses modifications en cours, puis ouvrir un terminal **à la racine du clone**. La branche de documentation `codex/cms-plugin-roadmap` reste indépendante.

```bash
git switch main
git pull --ff-only
git switch -c release/1.2.1
```

Télécharger `VoilaVoila-CMS-1.2.0-to-1.2.1.patch`. Adapter son chemin réel :

```bash
git apply --check ~/Downloads/VoilaVoila-CMS-1.2.0-to-1.2.1.patch
git apply --index ~/Downloads/VoilaVoila-CMS-1.2.0-to-1.2.1.patch
python3 tools/check_repo.py
git diff --cached --check
git diff --cached --stat
```

Le patch prépare les changements dans l'index, y compris les suppressions des onze doublons. En cas de conflit, examiner les fichiers concernés : ne pas forcer le remplacement de modifications locales. Les données privées de `storage/` et les modules personnalisés ne sont pas inclus dans le patch.

```bash
git commit -m "release: prepare CMS 1.2.1"
git push -u origin release/1.2.1
```

Ouvrir une pull request vers `main` dans GitHub, lire les changements et attendre les contrôles du nouveau commit. Le simple push d'une branche n'est pas un push sur `main` ; la PR déclenche le workflow configuré.

### 2. Comprendre les nouveaux contrôles

1. `Repository checks` : hygiène du code et scénarios de fabrication/extraction.
2. `Build checked release archive` : ZIP produit depuis un commit propre, puis validation du manifeste et de la référence Git.
3. `PHP 8.3 / 8.4 / 8.5` : téléchargement du **même ZIP**, vérification, extraction et construction du conteneur à partir de cette application.
4. Suites HTTP, migration, diagnostic, sous-dossier, mises à jour depuis 1.1 et 1.2, retour arrière et tests WP Porter.
5. `Chromium on the release archive` : vrai parcours navigateur, mobile, FR/EN et sans JavaScript, depuis le ZIP extrait.

Les artefacts `cms-package` contiennent ZIP et checksum. Les autres artefacts contiennent les rapports de test. Les Actions sont épinglées par commit ; le workflow n'utilise aucun secret de production et ne publie pas de release automatiquement.

La nouvelle CI 1.2.1 doit être confirmée sur GitHub après publication du commit. Les validations locales sont décrites séparément.

### 3. Fabriquer et publier la release

Après fusion de la PR et vérification de la CI de `main` :

```bash
git switch main
git pull --ff-only
python3 tools/build_release.py --version 1.2.1
python3 tools/verify_release.py dist/VoilaVoila-CMS-1.2.1.zip --expected-version 1.2.1 --extract test-results/release
```

Le constructeur exige une arborescence Git propre et refuse d'écraser un fichier de sortie existant. L'archive préparée dans cette conversation correspond à son commit local de fabrication ; une construction depuis votre commit fusionné enregistre votre propre identifiant de commit et a donc sa propre somme de contrôle.

Vérifier ou rejouer les tests du paquet comme expliqué dans le [guide de tests](docs/fr/tests.md). Pour les nouvelles Actions, utiliser l'artefact issu du commit qui sera tagué.

Créer le nouveau tag seulement lorsqu'il n'existe pas encore :

```bash
git tag -a v1.2.1 -m "VoilaVoila CMS 1.2.1"
git push origin v1.2.1
```

Dans GitHub : **Releases → Draft a new release**, sélectionner `v1.2.1` et joindre :

- `VoilaVoila-CMS-1.2.1.zip` ;
- `VoilaVoila-CMS-1.2.1.sha256` ;
- le patch de mise à jour et le rapport de validation correspondant ;
- les notes de `docs/releases/v1.2.1.md`.

Conserver les tags et archives déjà publiés. Tant que les pilotes d'hébergement ne sont pas documentés, présenter la qualification de ces environnements comme en attente ; utiliser une préversion si vous ouvrez un programme pilote. Choisir **Latest** uniquement pour la version que vous souhaitez recommander.

### 4. Installation et pilote

Le ZIP complet contient les fichiers de l'application directement à sa racine. Lire [MISE-A-JOUR-1.2.1.md](MISE-A-JOUR-1.2.1.md) et la [fiche de pilote](docs/HOSTING-PILOT-FR-EN.md). Conserver les sauvegardes contenant identifiants, base ou médias privés hors de Git et des pièces jointes d'Issues.

Les informations publiques existantes du dépôt sont conservées : propriétaire `farhadtoor`, dépôt `voilavoila-cms`, licence MIT et avertissement sur le logo PASS. Aucun compte utilisateur n'est livré.

## English

### Apply and review

Start from a clean, synchronized `main` and create `release/1.2.1`. Use the shared commands above to check and apply `VoilaVoila-CMS-1.2.0-to-1.2.1.patch` with `--index`. This stages the exact source changes and known duplicate removals. Resolve conflicting local changes deliberately.

The patch does not contain private storage or custom modules. Commit, push the release branch and open a PR to `main`. The existing documentation branch remains separate. A branch push alone is not a push to `main`; opening the PR triggers the configured checks.

### CI and release

The workflow builds one checked ZIP, then PHP 8.3/8.4/8.5 jobs and a real Chromium journey test the extracted package. Upgrade/rollback and WP Porter checks are included. Build/test reports are separate artifacts; there is no production credential or automatic publication.

After merging and checking `main`, build and verify the release, then create `v1.2.1` without moving historical tags. Use GitHub's draft-release UI to attach the installable ZIP, matching checksum, update patch and validation report, with the bilingual release notes.

The supplied build records its local source commit; rebuilding your merged commit produces metadata/checksums for your own commit. Do not claim unexecuted hosting pilots or a new hosted CI run as passed. Keep published archives immutable and document pilot status.

### Deployment

Read the [upgrade guide](MISE-A-JOUR-1.2.1.md) and [hosting checklist](docs/HOSTING-PILOT-FR-EN.md). The hosted CMS needs PHP/SQLite, not Node or Python. Include hidden configuration files and retain private storage/custom modules. Keep backups and credentials out of GitHub.
