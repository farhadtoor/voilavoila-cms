# Mise à jour 1.1.0 → 1.2.0

## Avant de remplacer les fichiers

1. Dans la 1.1, téléchargez une sauvegarde portable et conservez une copie du code.
2. Pour un retour complet incluant accès, secrets et révisions, faites aussi une copie **cohérente** de tout le stockage pendant un arrêt des écritures/processus PHP. Une copie brute de SQLite pendant des écritures n’est pas une sauvegarde fiable.
3. Activez la maintenance, fermez les onglets d’édition et gardez votre accès SFTP.
4. Vérifiez PHP 8.3+, DOM, mbstring, fileinfo, PDO SQLite, GD JPEG/PNG/WebP et ZIP. WordPress Porter utilise aussi tokenizer.

## Remplacement

Le pack complet est une installation 1.2.0. Le patch fourni contient les fichiers ajoutés/modifiés depuis le pack original 1.1.0, avec les mêmes chemins relatifs.

- Envoyez les fichiers du moteur et les nouveaux fichiers du patch dans le dossier existant, en conservant `storage`.
- Conservez vos thèmes/plugins personnalisés. Sauvegardez toute modification locale des fichiers qui vont être remplacés et comparez-la avant l’envoi.
- Le patch ajoute `content/plugins/comments` et `content/plugins/wp-porter` ; il ne remplace pas les plugins déjà personnalisés portant ces noms sans votre contrôle. Si vous avez déjà WordPress Porter 1.0.0, les fichiers fournis sont identiques.
- Conservez les protections `.htaccess`, `.user.ini` et celles du stockage. Les fichiers de développement/tests sont refusés par les nouvelles règles serveur.
- Remplacez les fichiers ensemble, puis ouvrez `admin.php`. Ne relancez pas une installation et ne supprimez pas la base.

La version reste en API 1. Le schéma SQLite de la 1.1 est inchangé : aucune conversion des pages/articles, aucun nouveau compte, aucune réinitialisation des accès. Les nouveaux plugins sont **disponibles mais désactivés** ; les activations existantes restent conservées.

## Vérifier

```sh
php bin/console.php status
```

Avec le PHP du site, la commande doit indiquer 1.2.0. Elle initialise le CMS, comme les autres commandes natives : utilisez le bon chemin de stockage. SSH n’est pas obligatoire.

Connectez-vous avec l’accès existant. Vérifiez pages/articles, une ancienne image, les téléchargements PDF/DOCX/CSV/ZIP et le refus d’accès direct à la base/journaux. Activez Commentaires seulement si vous voulez ouvrir cette fonction. Désactivez ensuite la maintenance.

Les sauvegardes portables restent au format 2 et conservent maintenant les nouveaux documents ainsi que la provenance WordPress. Un ancien moteur 1.1 ne sait pas restaurer les nouveaux formats.

## Retour arrière

Restaurez **ensemble** votre ancien code et sa copie cohérente du stockage. Le ZIP portable ne restaure pas les identifiants ni toutes les anciennes révisions. Remettre seulement le moteur 1.1 ne suffit pas après l’ajout des nouveaux types de documents. Pour un problème de plugin : `admin.php?view=plugins&safe=1` ou `php bin/console.php safe-mode`.

Depuis la 1.0, l’ancienne migration SQLite reste active ; voir [migration 1.0](mise-a-jour.md), puis appliquer les vérifications 1.2.

[Documents](documents.md) · [Commentaires](commentaires.md) · [WordPress](wordpress.md)
