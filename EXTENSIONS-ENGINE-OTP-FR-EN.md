# WordPress Engine + Authenticator OTP — installation FR / EN

Le CMS reste autonome. Deux extensions sont fournies : **WordPress Engine 0.2** (identifiant historique `wp-runtime`) et **Authenticator OTP 1.0** (`otp`). La version **CMS 1.3.1** ajoute les points d’intégration nécessaires à l’OTP sans changer le schéma SQLite, les contenus ou les médias. Les deux extensions restent désactivées au départ.

## Français — parcours conseillé

1. Sauvegardez le code et tout le stockage privé de la 1.3.0. Appliquez le kit de mise à jour vers 1.3.1 sur une copie de test. N’écrasez jamais votre base avec celle d’un environnement de test. Une nouvelle installation peut utiliser directement le ZIP complet 1.3.1.
2. Le petit ZIP `VoilaVoila-WP-Engine-0.2.0.zip` s’installe dans le gestionnaire d’extensions. Si `wp-runtime` existe déjà, mettez à jour ses fichiers par SFTP ; ne supprimez ni son moteur privé ni sa base. Le **pack hors ligne complet**, plus lourd, comprend ce petit ZIP, le vrai WordPress 7.1.3, WooCommerce 11.2.1, leurs vérifications et licences. **Ne chargez pas ce pack complet dans le gestionnaire de ZIP natif** : il contient des fichiers à placer hors webroot.
3. Configurez le pool PHP-FPM privé, une base MySQL/MariaDB dédiée et deux origines HTTPS selon [le guide Engine](docs/WP-RUNTIME.fr.md). La 1.3 reste utilisable seule sur un hébergement sans ces capacités ; le moteur n’est pas une dépendance du CMS.
4. Activez Engine, préparez le WordPress privé avec les fichiers officiels du pack, puis ouvrez l’accès propriétaire. Installez/activez votre thème et vos plugins WordPress depuis les outils prévus. Pour WooCommerce, activez le profil **Compatibilité WordPress complète**. Ses sessions, API, nonces, tables produits/commandes et écrans métier restent dans le moteur.
5. Cliquez sur **Synchroniser les contenus publiés** pour que le thème WordPress lise une copie des pages/articles gérés dans le CMS. Recommencez après publication, ou planifiez la commande de synchronisation. Cette copie ne devient pas la source éditoriale ; produits et commandes ne sont pas modifiés par la synchronisation.
6. Installez/activez `VoilaVoila-OTP-1.0.0.zip`, ouvrez **Application OTP**, scannez le QR avec votre application, confirmez un code et conservez les codes de secours. [Guide OTP](docs/OTP.fr.md).
7. Testez sur votre hébergement : connexion OTP, thème, plugin, catalogue, panier, commande, livraison d’e-mail, paiement en environnement de test et restauration. Le rapport livré décrit les essais locaux ; il ne certifie pas votre hébergeur ni votre prestataire de paiement.

Les thèmes et plugins s’exécutent sur le vrai WordPress ; les API ne sont pas réécrites sous forme de stubs. La compatibilité de l’ensemble du catalogue WordPress ne peut pas être garantie. Le test WooCommerce livré crée une véritable commande avec un mode de paiement hors ligne ; aucun paiement réel ni e-mail externe n’est effectué.

## English — recommended path

1. Back up 1.3.0 code and complete private storage. Apply the 1.3.1 update kit on a test copy. Never deploy a test database. New installations can use the complete 1.3.1 ZIP.
2. Install the small `VoilaVoila-WP-Engine-0.2.0.zip` in the native plugin manager. Existing `wp-runtime` installations should update plugin files by SFTP while preserving the private engine/database. The larger **complete offline pack** includes this small ZIP, genuine WordPress 7.1.3, WooCommerce 11.2.1, verification inputs and licenses. **Do not upload the complete offline pack into the native module installer**; its private inputs belong outside webroot.
3. Configure the private PHP-FPM pool, a dedicated MySQL/MariaDB database and two HTTPS origins using the [Engine guide](docs/WP-RUNTIME.md). The standalone CMS remains usable without these server capabilities.
4. Activate Engine, prepare private WordPress from the supplied official inputs and open the owner handoff. Install/activate trusted WordPress modules. Enable **Full WordPress compatibility** for WooCommerce. Its sessions, nonces, APIs, product/order tables and business administration remain within the engine.
5. Sync published native content to the WordPress theme. Repeat after publication or schedule the sync command. Native CMS remains the editorial source; product/order data is untouched by this operation.
6. Install/activate `VoilaVoila-OTP-1.0.0.zip`, scan the locally generated QR, confirm a code and save recovery codes. [OTP guide](docs/OTP.md).
7. Qualify your actual host, mail, payment provider, backups and business modules. The supplied report is local evidence, not hosted-provider/payment certification.

WordPress APIs run in the genuine engine, not stub implementations. Universal module compatibility is not claimed. The WooCommerce test creates a real offline-payment order; it does not charge money or send external mail.

## GitHub

Dépôt / Repository: [farhadtoor/voilavoila-cms](https://github.com/farhadtoor/voilavoila-cms). Le patch de cette livraison vise le commit source 1.3.0 `f4f79e278ab25094aa0577c67d46b2fb38d56fe6`. / This delivery's patch targets that 1.3.0 source commit.

```sh
git switch -c release/1.3.1 f4f79e278ab25094aa0577c67d46b2fb38d56fe6
git apply --check /path/VoilaVoila-CMS-1.3.0-to-1.3.1.patch
git apply --index /path/VoilaVoila-CMS-1.3.0-to-1.3.1.patch
python3 tools/check_repo.py
python3 tests/run.py --report test-results/http.json
python3 tests/otp_131.py --report test-results/otp.json
git diff --cached --check
git commit -m "release: add WordPress Engine and authenticator integration"
git push -u origin release/1.3.1
```

Si ce commit n’existe pas encore dans votre clone, votre 1.3.0 n’a peut-être pas été publiée sur GitHub. Copiez les sources de la nouvelle archive sur une branche et examinez les différences ; ne forcez pas un patch sur une autre base. / If that commit is absent, the supplied 1.3.0 may not yet be in your GitHub clone. Copy the new source archive onto a branch and review changes instead of forcing a patch onto another base.

Le `main` GitHub consulté pendant cette préparation est encore `d5fb72a0f54ce6a7e7343daf992398434b16760a`. Le kit contient donc aussi `VoilaVoila-CMS-GitHub-d5fb72a-to-1.3.1.patch`, qui inclut les changements 1.3.0 et 1.3.1. Pour cette base, créez la branche depuis `d5fb72a`, puis utilisez ce patch à la place de celui de la séquence ci-dessus. **Choisissez un seul patch correspondant à votre base ; ne les appliquez pas l’un après l’autre.** / The GitHub `main` observed during preparation is still `d5fb72a`; the kit also supplies a patch from that base, including both releases. Choose one matching patch, never both.

Ouvrez une PR, attendez la CI de votre dépôt, puis créez un tag et une Release sur le commit distribué. Aucun push/tag/PR n’a été fait par cette préparation. **Ne commitez jamais le moteur installé, les clés OTP, les identifiants SQL ou le stockage privé.** Conservez le pack tiers volumineux comme pièce jointe de Release ou téléchargement privé, pas dans le dépôt Git source. / Open a PR, wait for hosted CI, then tag/release the exact distributed commit. No remote operation has been performed here. **Never commit installed engines, OTP keys, SQL credentials or private storage.** Store large third-party bundles as Release assets or private downloads, not in source Git history.

## Retour arrière / Rollback

Restaurez ensemble le code et le stockage privé complet d’avant mise à jour. Une ancienne 1.3.0 n’a pas les points d’enforcement OTP : revenir à cette version retire cette protection. Ne mélangez pas ancien code et état OTP actif en supposant que la protection reste appliquée. Le moteur WordPress a son propre retour arrière fichiers + SQL + secrets. / Restore both pre-update code and complete private storage. Unpatched 1.3.0 has no OTP enforcement; rolling back removes that protection. WordPress has a separate files + SQL + secrets rollback.
