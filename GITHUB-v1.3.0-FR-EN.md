# Publier 1.3.0 sur GitHub / Publish 1.3.0 on GitHub

Dépôt / Repository: [farhadtoor/voilavoila-cms](https://github.com/farhadtoor/voilavoila-cms).

## Français

Le paquet livré comprend les sources complètes, Forms, le connecteur WordPress inactif, les tests et les documents FR/EN. WordPress lui-même et les données privées ne doivent jamais être envoyés sur GitHub. Le fichier `BUILD-INFO.json` de l’archive identifie son commit source ; `MANIFEST-SHA256.txt` vérifie les fichiers distribués. Le rapport de validation décrit les tests locaux. Il ne signifie pas que GitHub Actions a déjà réussi.

### Mettre à jour le dépôt existant

Le patch `VoilaVoila-CMS-GitHub-d5fb72a-to-1.3.0.patch` vise exactement `d5fb72a0f54ce6a7e7343daf992398434b16760a`, l’état `main` récupéré pour cette préparation. Il préserve les fichiers d’attribution ajoutés par le projet. Si votre `main` a changé depuis, préférez une fusion sur une branche et résolvez les différences ; ne forcez pas l’application.

```sh
git clone https://github.com/farhadtoor/voilavoila-cms.git
cd voilavoila-cms
git fetch origin
git switch -c release/1.3.0 d5fb72a0f54ce6a7e7343daf992398434b16760a
git apply --check /chemin/VoilaVoila-CMS-GitHub-d5fb72a-to-1.3.0.patch
git apply --index /chemin/VoilaVoila-CMS-GitHub-d5fb72a-to-1.3.0.patch
git diff --cached --stat
git diff --cached --check
python3 tools/check_repo.py
python3 tests/run.py --report test-results/http.json
python3 tests/release_tools.py --report test-results/release-tools.json
php content/plugins/wp-porter/tests/run.php
git commit -m "release: prepare CMS 1.3.0"
git push -u origin release/1.3.0
```

Il faut PHP 8.3+ avec les modules du projet. Les tests navigateur demandent Node et Playwright ; ils sont séparés des dépendances nécessaires pour utiliser le CMS. Le patch est binaire Git : téléchargez-le comme fichier, ne le recopiez pas depuis une page web. N’appliquez pas à la suite un autre patch visant une autre base.

Variante sans patch : créez une branche sur votre `main`, extrayez le ZIP complet hors du dépôt, puis copiez ses fichiers d’application dans le dépôt sans supprimer vos fichiers personnalisés. Ne copiez pas de stockage d’un site. Examinez toutes les différences et adaptez les conflits avant le commit. `BUILD-INFO.json` et le manifeste identifient l’archive d’origine ; le constructeur régénère ces deux fichiers pour votre propre commit.

### Pull request et première publication

Ouvrez une PR de `release/1.3.0` vers `main`, avec les notes [v1.3.0](docs/releases/v1.3.0.md). Attendez les jobs **Repository checks**, **Build checked release archive**, PHP 8.3/8.4/8.5 et Chromium. Vérifiez les rapports de mise à jour/retour arrière. Le test WordPress réel est une commande d’intégration distincte, décrite dans [les tests](docs/TESTING-1.3.md) ; il n’est pas exécuté implicitement sur votre production.

Protégez `main` : PR requise, vérifications CI requises et interdiction des force-push. Utilisez les noms exacts des checks observés dans votre dépôt. Les actions sont épinglées par SHA ; laissez Dependabot proposer leurs mises à jour. Activez Issues, Discussions, Wiki et la déclaration privée de vulnérabilités si ces fonctions sont disponibles pour votre dépôt.

Après fusion et CI verte, créez le tag sur **le commit que vous allez distribuer**, puis construisez le paquet de ce même commit :

```sh
git switch main
git pull --ff-only
git status --short
git tag -a v1.3.0 -m "VoilaVoila CMS 1.3.0"
python3 tools/build_release.py
python3 tools/verify_release.py dist/VoilaVoila-CMS-1.3.0.zip --expected-commit "$(git rev-parse HEAD)"
git push origin v1.3.0
```

Le constructeur exige une copie propre et refuse d’écraser un ZIP existant. Si un tag 1.3.0 est déjà public, ne le déplacez pas : publiez un correctif sous un nouveau numéro. Dans **Releases → Draft a new release**, choisissez le tag, collez les notes FR/EN et joignez ZIP, `.sha256`, guide de mise à jour et rapport correspondant **au paquet réellement construit**. Un ZIP reconstruit après fusion aura un nouveau commit et une nouvelle somme ; ne réutilisez pas ceux du paquet de préparation. Pour une extension WP expérimentale, indiquez explicitement ce statut dans les notes ; le CMS natif n’exige pas son installation.

### Communauté et Wiki

Créez des pages Wiki Accueil/Home, Installation, Mise à jour/Upgrade, Commandes/CLI, Forms, Migration WordPress, Sécurité/Security et Contribuer/Contributing en copiant les fichiers Markdown correspondants du dépôt. Gardez un lien vers le fichier source et le numéro de version pour éviter une documentation décalée. Épinglez une discussion « démarrer ici », une catégorie support et une catégorie retours d’hébergement. Les failles doivent suivre `SECURITY.md`, sans mots de passe ni base SQLite joints publiquement. Proposez des issues `good first issue` avec résultat attendu et moyen de vérifier ; demandez PHP/hébergeur, version CMS et reproduction minimale dans les bugs.

Les réglages GitHub et l’exécution de votre CI n’ont pas été modifiés depuis cette conversation. Les commandes de push/tag ci-dessus sont à exécuter dans votre compte.

## English

The release contains complete source, Forms, an inactive optional WordPress connector, tests and bilingual documentation. Never publish installed WordPress engines, SQL dumps, native storage, connector secrets or credentials. Archive build metadata identifies its source commit; the supplied report describes local execution, not a successful hosted Actions run.

Apply `VoilaVoila-CMS-GitHub-d5fb72a-to-1.3.0.patch` on a branch based on `d5fb72a0f54ce6a7e7343daf992398434b16760a`, using the command sequence above. Preserve existing attribution files. If your `main` has advanced, merge/review differences on a branch; do not force a patch onto another base. Run repository, PHP, release-tool, Porter and browser checks before pushing the branch. You can instead copy the extracted full archive into a branch, retaining custom modules and excluding runtime storage, then review every change.

Open a PR to `main`, include [release notes](docs/releases/v1.3.0.md), and wait for package, PHP 8.3/8.4/8.5 and Chromium jobs. Real WordPress/FPM/MariaDB integration is a separate explicit test, not a production operation. Require PRs/checks and disallow force pushes on `main`. Enable Issues, Discussions, Wiki and private vulnerability reporting as available. Review pinned action updates through Dependabot.

After merge and green checks, tag the exact commit to distribute. Build and verify a fresh archive from that clean commit; the constructor refuses to overwrite published files. Never move a public release tag. Upload the resulting ZIP/checksum, upgrade guide and matching validation report to a GitHub Release. A post-merge rebuild has a different commit/checksum from this preparation archive; regenerate its evidence.

Start bilingual Wiki pages for installation, upgrades, CLI, Forms, WordPress migration, security and contribution from repository Markdown. Link back to versioned source docs. Pin a getting-started discussion, separate support from feature proposals, and collect real hosting feedback. Use actionable `good first issue` tasks. Route vulnerabilities through `SECURITY.md`; do not attach private databases or credentials to public issues.

No remote push, tag, PR, Release or repository setting has been changed on your behalf.
