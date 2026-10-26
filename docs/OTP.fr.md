# VoilaVoila Authenticator OTP 1.0

Cette extension ajoute un **second facteur TOTP** à l’accès du propriétaire : mot de passe, puis code de l’application. Elle nécessite le correctif d’intégration **CMS 1.3.1**. Installer seulement les fichiers d’une extension sur une 1.3.0 non corrigée ne permettrait pas de protéger toutes les entrées privées ; le manifeste refuse donc cette activation.

## Activer

1. Sauvegardez le code et **tout le stockage privé**. Appliquez la mise à jour 1.3.0 → 1.3.1 fournie, ou utilisez le paquet complet 1.3.1 pour une nouvelle installation.
2. Installez `VoilaVoila-OTP-1.0.0.zip` dans Extensions si le dossier `otp` n’est pas déjà fourni. Activez **VoilaVoila Authenticator OTP**, puis ouvrez **Application OTP**.
3. Saisissez votre mot de passe actuel et choisissez « Configurer mon application ».
4. Dans une application TOTP (par exemple Aegis, 2FAS, Google Authenticator ou Microsoft Authenticator), ajoutez un compte en scannant le QR code. Une clé manuelle est disponible si nécessaire. Le QR est généré **localement dans le navigateur**, sans transmettre le secret à un service externe.
5. Confirmez le code actuel à six chiffres. L’activation n’a lieu qu’après cette preuve. La configuration temporaire expire après dix minutes.
6. Copiez les **dix codes de secours** affichés une seule fois, et conservez-les hors ligne, séparément du téléphone. Ils ne sont pas renvoyés par e-mail. Un rechargement masque la liste.
7. Testez une nouvelle connexion dans un autre navigateur : le mot de passe doit mener à l’écran OTP, sans accès préalable à l’administration ni aux aperçus/médias privés.

Chaque code de secours remplace une seule vérification OTP, après saisie du mot de passe. Un code TOTP déjà accepté est refusé s’il est présenté à nouveau. Le profil est SHA-1, six chiffres, pas de trente secondes, avec une fenêtre de ± un pas. Synchronisez l’horloge du serveur (NTP) et activez l’heure automatique du téléphone.

## Gestion et récupération

Le panneau indique le nombre de codes de secours restants. Le renouvellement et la désactivation demandent **le mot de passe actuel et un code TOTP ou de secours valide**. Un renouvellement invalide les anciens codes et les autres sessions. Si votre code TOTP vient d’être consommé, attendez le suivant.

Une récupération du mot de passe par le code natif du CMS **ne contourne pas** le second facteur. Le mode sans extensions ne le contourne pas non plus. La désactivation ou suppression de l’extension par le panneau est refusée tant que l’OTP est configuré. Si ses fichiers manquent, l’accès reste bloqué plutôt que de revenir à une connexion sans OTP.

Si le téléphone ET les codes sont perdus, un opérateur disposant de l’accès local SSH peut utiliser, avec le bon `VV_STORAGE_PATH` :

```sh
php content/plugins/otp/bin/otp.php status
php content/plugins/otp/bin/otp.php disable --confirm=DISABLE-OTP
```

Cette opération locale révoque toutes les sessions propriétaire et les sessions du pont WordPress. Elle exige les droits sur le serveur, pas une URL publique. Conservez l’accès SFTP/SSH ou une procédure de restauration avant d’activer l’OTP.

## Stockage et portée

Le secret confirmé est chiffré par AES-256-GCM dans l’état privé du propriétaire. La clé de chiffrement est séparée dans `VV_STORAGE_PATH/otp/key.php`, avec garde PHP et permissions privées. Les codes de secours sont conservés sous forme de HMAC, jamais en clair. Le provisionnement en attente est chiffré dans la session propriétaire. OpenSSL avec AES-256-GCM est requis.

L’export éditorial natif ne contient **ni le secret, ni les codes, ni la clé**. Une sauvegarde complète doit conserver ensemble la base SQLite propriétaire et tout le stockage privé, dont la clé OTP. Une restauration éditoriale conserve l’OTP du propriétaire actuel. Les sauvegardes complètes contiennent des éléments sensibles : protégez-les comme vos identifiants.

Le correctif 1.3.1 intervient dans l’établissement et la validation des sessions ; il protège aussi les entrées privées qui ne chargent pas les extensions. L’activation/rotation révoque les anciennes sessions via l’époque du compte. Le pont WordPress suit cette révocation et refuse les cookies interactifs privilégiés devenus périmés. Les clés API de services restent des identifiants distincts, soumis à leur propre gestion.

La TOTP ajoute un facteur à la connexion ; elle ne rend pas une session volée ou une extension PHP malveillante inoffensive. Les sessions déjà vérifiées restent soumises aux expirations du CMS. Seuls des modules serveur de confiance doivent être installés.

## Validation et sources

Le rapport livré distingue les vecteurs RFC 6238 SHA-1/SHA-256/SHA-512, les tests HTTP, le décodage du QR et les parcours navigateur. Une installation réelle des applications mobiles citées n’est pas automatisée par ces tests.

- [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238).
- [Format otpauth](https://github.com/google/google-authenticator/wiki/Key-Uri-Format).
- [Bibliothèque QR Nayuki, MIT](https://www.nayuki.io/page/qr-code-generator-library).

[English](OTP.md) · [Installation des deux extensions](../EXTENSIONS-ENGINE-OTP-FR-EN.md)
