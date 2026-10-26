# Commandes / Commands — VoilaVoila CMS 1.3

Exécuter depuis le dossier du CMS, avec le même `VV_STORAGE_PATH` que le site. / Run from the CMS directory with the site's `VV_STORAGE_PATH`. Les commandes locales donnent un accès de propriétaire : protéger SSH/cron. / Local commands are owner operations: protect SSH/cron access.

| Commande / Command | Effet FR | Effect EN |
|---|---|---|
| `php bin/console.php doctor` | Diagnostic avant installation, sans ouvrir la base réelle | Pre-install hosting diagnostic, without opening the real database |
| `php bin/console.php doctor --json` | Rapport sans secret à partager | Shareable diagnostic without secrets |
| `php bin/console.php install-key` | Clé initiale côté serveur | Server-side first-install key |
| `php bin/console.php status` | Version, SQLite et modules | Version, SQLite and modules |
| `php bin/console.php migrate` | Ouvrir/migrer le stockage hérité | Open/migrate legacy storage |
| `php bin/console.php reset-owner` | Nouveau mot de passe via stdin, sessions révoquées | Password from stdin; revoke owner sessions |
| `php bin/console.php safe-mode` | Désactiver les extensions et restaurer Profil Or | Disable plugins and restore Profil Or |
| `php bin/console.php forms:notifications` | Traiter jusqu’à 10 notifications ; Forms active requise | Process up to 10 notifications; Forms must be active |
| `php bin/console.php forms:purge --confirm=PURGE` | Effacer les demandes expirées | Delete expired form requests |
| `php bin/console.php wp:quarantine:status` | État du connecteur facultatif | Optional connector status |
| `php bin/console.php wp:quarantine:prepare ZIP < /private/db-input.json` | Préparer WordPress vérifié, identifiants SQL privés par stdin | Prepare verified WordPress, private SQL credentials on stdin |
| `php bin/console.php wp:plugin:wrap ZIP` | Installer un plugin WP de confiance et créer son manifeste | Install trusted WP plugin and record its manifest |
| `php bin/console.php wp:theme:wrap ZIP` | Installer un thème WP de confiance et créer son manifeste | Install trusted WP theme and record its manifest |

Le Runtime demande une configuration serveur supplémentaire ; aucune de ces commandes n’est nécessaire pour utiliser le CMS natif. Il n’existe pas de commande `wp:quarantine:sync` ni `wp:assets:publish` dans cette version. / Runtime requires additional server setup; none of its commands is required by the native CMS. `wp:quarantine:sync` and `wp:assets:publish` are not implemented in this version.

Pour le développement / For development:

```sh
python3 tools/check_repo.py
python3 tests/run.py --report test-results/http.json
python3 tests/release_tools.py --report test-results/release-tools.json
php content/plugins/wp-porter/tests/run.php
python3 tools/build_release.py
python3 tools/verify_release.py dist/VoilaVoila-CMS-1.3.0.zip
```

[Installation FR](docs/fr/installation.md) · [Installation EN](docs/en/installation.md) · [Forms FR](docs/FORMS.fr.md) · [Forms EN](docs/FORMS.md) · [Runtime FR](docs/WP-RUNTIME.fr.md) · [Runtime EN](docs/WP-RUNTIME.md) · [GitHub](GITHUB-v1.3.0-FR-EN.md).

## Extensions 1.3.1 / Engine 0.2

| Command | FR / EN |
|---|---|
| `php content/plugins/wp-runtime/bin/engine.php status` | État du moteur / Engine status |
| `php content/plugins/wp-runtime/bin/engine.php sync` | Copie éditoriale publiée / Published editorial copy |
| `php content/plugins/wp-runtime/bin/engine.php refresh --confirm=REFRESH` | Mise à jour du pont, pool à redémarrer / Refresh bridge, restart private pool |
| `php content/plugins/otp/bin/otp.php status` | État du second facteur / Second-factor status |
| `php content/plugins/otp/bin/otp.php disable --confirm=DISABLE-OTP` | Récupération locale privilégiée / Privileged local recovery |

[OTP FR](docs/OTP.fr.md) · [OTP EN](docs/OTP.md) · [Installation FR/EN](EXTENSIONS-ENGINE-OTP-FR-EN.md).
