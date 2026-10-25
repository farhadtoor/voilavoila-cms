# Migrer depuis WordPress — 1.2.0

Deux outils distincts sont livrés : l’import de **contenus et médias** dans Outils & sauvegardes, et **WordPress Porter** pour analyser/porter une partie des thèmes et plugins. Le CMS n’exécute pas WordPress.

## Contenus, médias et commentaires

1. Sauvegardez WordPress et le CMS.
2. Dans WordPress : **Outils → Exporter → Tout le contenu**. Conservez le XML WXR.
3. Par SFTP, récupérez `wp-content/uploads` et créez un ZIP. Chemins acceptés : `2026/10/photo.jpg`, `uploads/2026/10/photo.jpg` ou `wp-content/uploads/2026/10/photo.jpg`. Évitez les métadonnées macOS ; n’envoyez pas une sauvegarde de tout WordPress.
4. Activez Commentaires dans le CMS si vous voulez les importer.
5. Dans **Outils & sauvegardes → Importer depuis WordPress**, joignez **le XML et le ZIP uploads au même import**.
6. Par défaut : brouillons et médias privés. Les options permettent de conserver les publications publiques et/ou rendre les médias publics. Un fichier public est accessible par son lien, même s’il est utilisé dans un contenu privé.
7. Saisissez le mot de passe propriétaire. Un point de restauration précède les écritures.
8. Consultez/téléchargez le rapport et la correspondance des URLs. Relisez les contenus, approuvez les commentaires choisis et testez anonymement.

Exemple de ZIP sur macOS, sans fichiers annexes :

```sh
cd "/chemin/du/dossier/qui/contient/uploads"
zip -r -X uploads.zip uploads -x '*/.*' '__MACOSX/*'
```

Le XML ne contient pas les octets des médias. Aucun fichier n’est téléchargé par HTTP : le ZIP fournit les pièces jointes. Un import sans ZIP retire les images externes du HTML ; joindre le ZIP plus tard ne réécrit pas les contenus existants. Utilisez une installation d’essai ou réinsérez les médias.

## Reprise effectuée

| WordPress | CMS |
| --- | --- |
| WXR 1.0/1.1/1.2 | Namespaces contrôlés, XML sans DTD/entités |
| Pages/articles | Identifiants natifs, titres, HTML nettoyé, résumés |
| Dates | Création/modification/publication UTC ; repli signalé si GMT manque |
| Contenus privés/protégés | Privés, accessibles au propriétaire ; mot de passe WordPress non importé |
| Contenus futurs | Programmation conservée si l’option de publication est cochée |
| Catégories/étiquettes | Mots-clés natifs à plat, 12 maximum |
| Épinglage | Conservé |
| Médias reconnus | Validation partagée, octets/extensions conservés ; dédoublonnage source/ID/empreinte |
| Images `-300x200` | Rattachées à l’original présent et reconnu |
| Image à la une | Couverture native si l’image correspondante est publique |
| Liens internes | Réécrits pour les contenus présents dans l’export |
| `[caption]`, `[gallery ids="…"]` | Légendes simples, galerie d’images locales reconnues |
| Commentaires ordinaires | Import facultatif dans le plugin actif, tous en attente |
| Nouvel import | Même URL source + ID ; contenu existant et modifications locales conservés |
| Rapport | Compteurs, 100 avertissements affichables, nombre total, carte des URLs |

Gardez la même URL source entre imports ; renseignez-la si elle manque du XML. Les imports 1.1 reconnus par leur ancienne clé reçoivent une provenance stable sans écraser leur contenu. Un fichier modifié sous le même ID WordPress ne remplace pas l’ancien : un avertissement signale le conflit.

La carte des URLs est informative : aucune redirection 301 n’est installée. Configurez les redirections sur l’ancien domaine/serveur après vérification. Les liens vers les éléments absents ne sont pas garantis. Le dernier rapport reste privé sur le serveur et n’entre pas dans l’export portable ; la provenance des contenus/médias est exportée et restaurée.

## Limites

XML : 8 Mio, 2 000 éléments ; corps : 200 Ko ; ZIP : 64 Mio envoyé/décompressé, 2 200 entrées, 8 Mio par fichier. Limites du site : 10 000 contenus, 2 000 médias. Les commentaires ont leurs propres plafonds.

Les médias absents/refusés sont signalés ; les contenus valides peuvent néanmoins être ajoutés. Un XML/ZIP invalide ou une erreur de transaction empêche la validation. Les nouveaux fichiers d’un import échoué sont retirés. Les données existantes ne sont pas remplacées. Aucun PHP sérialisé n’est désérialisé.

Non migrés : utilisateurs, rôles, réglages, menus complexes, hiérarchies d’URL, produits/commandes, données PASS, blocs dynamiques, constructeurs de pages et logique métier des plugins. Les shortcodes inconnus demandent une adaptation. Le HTML statique des blocs est nettoyé ; le JavaScript WordPress n’est pas exécuté.

## Thèmes et plugins : WordPress Porter

Activez **WordPress Porter** dans Extensions, puis ouvrez **Portage WordPress**. Fournissez un ZIP WordPress. Le résultat indique **converted** pour le sous-ensemble pris en charge, ou **manual** avec brouillon et rapport.

Le PHP source est analysé comme du texte et jamais exécuté. Relisez les sorties avant leur installation native séparée. Ce n’est pas une conversion universelle de WooCommerce, des constructeurs, de `$wpdb`, de l’authentification ou des paiements.

```sh
php content/plugins/wp-porter/bin/porter.php analyze source.zip report.json
php content/plugins/wp-porter/bin/porter.php convert source.zip native.zip --id mon-module
```

Porter 1.0.0 est fourni sans modification. ZIP et tokenizer sont nécessaires. Guide détaillé : `content/plugins/wp-porter/README.fr.md`.

Référence : [export WordPress officiel](https://developer.wordpress.org/reference/functions/export_wp/).

[Documents](documents.md) · [Commentaires](commentaires.md)
