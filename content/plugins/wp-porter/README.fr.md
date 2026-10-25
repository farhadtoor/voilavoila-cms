# WordPress Porter 1.0.0

Extension native pour **VoilaVoila CMS 1.1.0+**. Elle analyse un ZIP de thème ou d’extension WordPress et produit un portage natif limité, ou un projet explicite à finaliser avec un rapport par fichier/API. Le PHP importé est analysé comme des données, jamais chargé ni exécuté.

[English](README.md) · [Règles prises en charge](docs/RULES.md) · [Validation](docs/VALIDATION.md)

## Installer

1. Utilisez CMS 1.1.0+, PHP 8.3+, ZIP, Tokenizer, mbstring, DOM et fileinfo. La plupart sont déjà requis par le CMS ; activez Tokenizer/ZIP si nécessaire. Mémoire PHP conseillée : 256 Mio.
2. Dans l’administration propriétaire, ouvrez **Extensions**, installez `VoilaVoila-WP-Porter-1.0.0.zip` avec votre mot de passe actuel, puis activez **WordPress Porter**. Ce ZIP s’installe dans votre CMS autonome, pas dans WordPress.
3. Ouvrez **Portage WordPress** dans le menu.
4. Chargez le ZIP d’un seul thème ou plugin WordPress, pas une installation WordPress complète, une sauvegarde SQL ou un lot de plugins. Vous pouvez préciser type, identifiant natif unique et nom.
5. Lisez le statut, les dépendances et les correspondances natives. Saisissez votre mot de passe courant pour télécharger le ZIP et/ou le rapport JSON.
6. Sur une copie d’essai du CMS, installez le résultat dans **Thèmes** ou **Extensions**. Il reste inactif. Relisez `porting/REPORT.md` avant activation. L’installateur ne remplace pas un identifiant de module existant.

Le ZIP source reste intact. Aucun code n’est envoyé à une API. Aucun moteur WordPress, service IA, Composer ou build Node n’est utilisé. Aucune installation, activation ou mise à jour de production automatique.

## Deux résultats

**Portage natif limité (`converted`)** : la source correspond aux règles implémentées. Les shortcodes, filtres de contenu/hooks de pied de page simples ou modèles classiques pris en charge sont régénérés pour l’API native. Cela ne reproduit pas tous les comportements WordPress ni une présentation strictement identique. Contenus, menus, droits et nettoyage HTML sont ceux du CMS. Testez le rendu et les fonctions.

**Projet à finaliser (`manual`)** : un blocage est identifié. Le ZIP contient un module de départ clairement marqué et des références inertes, sans la logique métier d’origine. Une extension brouillon affiche seulement un avis administratif ; un thème brouillon affiche un bandeau public explicite. Portez les dépendances avant utilisation réelle. Gutenberg, thèmes enfants, WooCommerce, SQL, comptes/rôles, REST/AJAX, cron, e-mails/paiements, shortcodes dynamiques et PHP arbitraire relèvent généralement de ce mode.

Ce n’est pas une couche universelle de compatibilité WordPress. Le résultat contient `cms.json`, les points d’entrée natifs, un rapport bilingue et des références de source en `.txt`. Les références sont plafonnées et peuvent être partielles : conservez le ZIP complet. Auteur/licence et fichiers de licence à la racine sont repris sans réattribuer leurs droits au convertisseur.

## Conversion automatique

- Callbacks `add_shortcode()` retournant une chaîne constante, avec certains appels de traduction/échappement simples. Pas d’attributs ni de contenu englobé.
- Filtres `add_filter('the_content', ...)` retournant un préfixe/suffixe constant autour de leur unique paramètre, ou une chaîne de remplacement.
- Actions `add_action('wp_footer', ...)` affichant une chaîne constante, reliées au hook natif de pied de page.
- Modèles PHP classiques simples : champs natifs, boucles, conditions de base, parties à chemin littéral, navigation/recherche natives, styles locaux et ressources image/police autorisées. Le PHP importé est compilé en instructions JSON bornées, rendues par du code natif maîtrisé.

Les traductions WordPress `.mo`/`.po`, le JavaScript arbitraire et les données de menus/réglages ne sont pas migrés. Les textes littéraux restent tels quels ; les contenus FR/EN du CMS restent disponibles. Consultez les limites exactes dans [RULES](docs/RULES.md).

## Résultats privés et limites

Trois résultats au maximum sont gardés dans `wp-porter/` sous votre stockage privé configuré. Le JSON et le ZIP reçoivent le préfixe PHP de terminaison du CMS. L’accès exige la session propriétaire ; téléchargement/suppression demandent aussi le mot de passe courant. CSRF, HTTPS et limites de réauthentification restent actifs (CMS 1.1 : 10 tentatives en 15 minutes, réussies comprises). Pour un lot, utilisez la CLI sans affaiblir ces protections.

Expiration après 24 heures **lors de la prochaine ouverture de l’écran ou opération sur un résultat**. Aucun nettoyage en arrière-plan. Après désactivation sans nouvelle utilisation, les fichiers restent présents jusqu’à suppression manuelle ou nettoyage ultérieur. Supprimez les résultats avant désactivation si nécessaire. Ils peuvent contenir du code privé. Ils ne sont insérés ni dans les contenus ni dans les sauvegardes éditoriales. L’analyse ne modifie ni propriétaire, pages, articles, médias ou thème actif.

Entrée : ZIP 12 Mio, 1 200 entrées, 32 Mio décompressés, 8 Mio par fichier. Liens/fichiers spéciaux, chiffrement, traversée de chemins, doublons de casse, compression extrême et `.env` privés sont refusés. Fichiers cachés de dépôt/serveur et métadonnées macOS sont ignorés. PHP : 256 Kio/30 000 jetons significatifs par fichier, 400 noms d’API et profondeur bornée. Une limite d’analyse produit un blocage de portage, pas un succès. Le ZIP de sortie respecte les plafonds natifs : 400 entrées, 20 Mio décompressés et 12 Mio compressés ; les références sont réduites pour les respecter.

## CLI sans installer le CMS

Décompressez l’extension et ouvrez un terminal dans `wp-porter` :

```sh
php bin/porter.php analyze /chemin/theme.zip /chemin/rapport.json
php bin/porter.php convert /chemin/plugin.zip /chemin/natif.zip --id ported-exemple --name "Exemple"
php bin/examples.php /chemin/nouveau-dossier-exemples
php tests/run.php
```

Précisez `--type theme` ou `--type plugin` si nécessaire. Les sorties existantes sont refusées. La CLI n’initialise aucun stockage CMS et n’installe rien. Les limites d’analyse y restent actives. Rapports bilingues ; messages opérationnels CLI en anglais. Un code de sortie 0 confirme l’analyse/génération ; lisez le statut pour distinguer brouillon et portage limité.

Pour les pages/articles WordPress, utilisez séparément l’import WXR déjà présent dans le CMS. Il ne récupère ni médias distants ni données métier des plugins. Cette extension porte le code des modules, pas les données d’un site en ligne.

## Développement et licence

Code MIT ; voir LICENSE.txt. Les sources/ressources importées gardent leurs conditions. Une suite de tests CLI et quatre exemples WordPress synthétiques générables sont inclus. Le guide de validation distingue les contrôles réalisés et leurs limites. Le convertisseur n’est ni un scanner de vulnérabilités ni un audit de code tiers.
