# Forms 1.0 — VoilaVoila CMS 1.3

Activez **VoilaVoila Forms** dans Extensions. Ouvrez **Formulaires**, créez une définition, réglez sa durée de conservation et son texte d’information, puis publiez-la. Collez son tag, par exemple `[form-contact]`, dans une page ou un article public. Aucun JavaScript n’est nécessaire pour envoyer le formulaire.

Types : texte, e-mail, téléphone, texte long, sélection et case à cocher. Une étiquette vide retire un champ ; les nombres d’ordre permettent de les déplacer. Limites : 10 formulaires, 20 champs par formulaire, 500 demandes et 2 Mio pour Forms, dans la limite globale de 8 Mio des données d’extensions du CMS. Les fichiers joints ne sont pas acceptés par Forms ; utilisez la médiathèque pour vos documents publiés.

La boîte privée propose recherche, filtres, pagination et statuts Nouveau, Lu, Traité, Indésirable. Les demandes conservent une copie de la définition au moment de l’envoi. Les valeurs sont du texte, affiché avec échappement. Supprimer une définition ne supprime pas ses demandes ; désactiver l’extension conserve ses données.

Chaque envoi utilise un cookie visiteur et un jeton CSRF distincts de la session propriétaire, une preuve signée temporaire, un champ piège et une limite de 5 envois par IP par 15 minutes et 100 par site par heure. Les compteurs utilisent le stockage privé ; aucune IP brute n’est ajoutée aux demandes. Une preuve utilisée reste mémorisée deux heures pour limiter les doublons, même après suppression de la demande. Cela n’est pas un service antispam externe.

## Notifications et conservation

Les notifications sont désactivées au départ. Configurez une adresse destinataire et un expéditeur autorisé par votre hébergeur. Après réauthentification, le bouton de traitement ou la commande suivante prend jusqu’à 10 notifications en attente :

```sh
php bin/console.php forms:notifications
php bin/console.php forms:purge --confirm=PURGE
```

Vous pouvez planifier ces commandes dans le cron de votre hébergeur, avec le même `VV_STORAGE_PATH` et le bon exécutable PHP. La purge supprime les demandes expirées ; leur durée est fixée lors de chaque envoi. La désactivation de Forms empêche ces commandes d’agir. Le panneau permet également une purge explicite.

`accepted` indique que le transport a accepté le message, pas sa livraison. `failed` et `unknown` ne sont jamais réessayés automatiquement. Une interruption après remise au transport peut produire un résultat inconnu ; une reprise manuelle peut donc envoyer un doublon. Le traitement ne conserve pas de transaction SQLite pendant l’appel au transport. Les notifications en cours trop anciennes deviennent inconnues. Le courrier PHP dépend de la configuration réelle de votre hébergeur ; aucune livraison n’est certifiée par les tests locaux.

Export JSON fidèle et CSV avec neutralisation des préfixes de formules, après saisie du mot de passe actuel. Le CSV contient une ligne par champ. Les sauvegardes natives incluent Forms et demandent aussi le mot de passe. Après restauration avec Forms active, les notifications sont désactivées et les envois en attente/en cours deviennent inconnus. Gérez séparément les copies exportées et anciennes sauvegardes : la purge de la boîte ne les efface pas.

## API 1

`$app->currentContent()` donne le contenu pendant son rendu et restaure le contexte ensuite. Les callbacks des shortcodes restent appelés **sans argument**. L’action `forms_received_v1` intervient après validation de la transaction et fournit les identifiants, sans données personnelles. Le filtre `forms_transport_v1` peut fournir un transport de confiance retournant `accepted`, `failed` ou `unknown`. Le filtre `backup_restore_data_v1` valide les données d’une extension active avant toute écriture éditoriale.
