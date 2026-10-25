# VoilaVoila CMS

**Un petit CMS personnel auto-hébergé en PHP + SQLite, pour un propriétaire unique.** Publiez un profil, des pages et des articles ; gérez les médias ; ajoutez des thèmes et des extensions natifs.

[English](README.md) · [Installer](docs/fr/installation.md) · [Mettre à jour](docs/fr/mise-a-jour-1.2.md) · [Contribuer](CONTRIBUTING.fr.md) · [Versions](https://github.com/farhadtoor/voilavoila-cms/releases)

La version actuelle du moteur est la **1.2.0**. La branche `main` contient aussi la documentation bilingue et les vérifications de développement. Les archives originales 1.0.0 et 1.1.0 sont conservées comme deux versions distinctes ; elles ne se superposent pas dans une même installation.

## Fonctions

- Profil public, pages et articles FR/EN, brouillons, aperçus privés et programmation des publications.
- Jusqu’à 15 révisions par contenu et refus d’un enregistrement provenant d’un éditeur périmé.
- Import JPG/JPEG/PNG/WebP et documents PDF/DOCX/ZIP/CSV ; octets/extensions conservés et liens dans l’éditeur.
- Deux thèmes natifs et installation de ZIP de thèmes/extensions au format API 1.
- Atelier privé, référencement/sitemap/RSS et lien PASS externe facultatif.
- Sauvegarde/restauration éditoriale et import des pages/articles WordPress WXR 1.0–1.2.
- Un propriétaire, avec clé d’installation côté serveur, mot de passe haché et code de récupération hors ligne.

Les pages, articles et révisions sont enregistrés dans SQLite. Les accès PHP et les erreurs disposent chacun d’un journal texte. L’interface et ses ressources sont locales ; aucun Composer, build npm ou serveur de base de données externe n’est requis.

## Essai local rapide

Prérequis : **PHP 8.3+**, DOM, mbstring, fileinfo et PDO SQLite ; GD avec les codecs nécessaires pour les images ; ZIP pour les archives. Utilisez une branche PHP maintenue et ses correctifs récents. Python, Node et Docker servent au développement et restent facultatifs pour utiliser le CMS.

Depuis le dossier du CMS :

```sh
php bin/console.php status
php bin/console.php install-key
php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php
```

Gardez le terminal ouvert, ouvrez [l’installateur local](http://127.0.0.1:8787/install.php), saisissez la clé générée et créez votre compte. Conservez hors ligne le code de récupération affiché une seule fois. Le serveur intégré est réservé aux essais. Pour publier un site, suivez le [guide HTTPS et protection du stockage](docs/fr/installation.md).

[Les références des deux versions](docs/history/README.md) comprennent le guide et la validation de la première version en FR/EN.

## Périmètre

Le moteur vise un profil personnel et un trafic modéré. Il ne comprend pas d’inscription publique, de rôles multiples, de vente de billets ou de paiement. L’extension PASS ouvre une billetterie HTTPS existante. Certains concepts viennent de WordPress ; ses thèmes, plugins et fonctions `wp_*` nécessitent un portage. L’import WXR récupère les pièces jointes depuis un ZIP local, sans téléchargement HTTP ni migration des données métier.

Une extension PHP native s’exécute avec les droits du processus de l’hébergement. Installez uniquement du code de confiance. Consultez les [protections](docs/fr/securite.md) et la [politique de signalement](SECURITY.md).

## Documentation et développement

Les guides couvrent l’[installation](docs/fr/installation.md), la [migration](docs/fr/mise-a-jour.md), l’[architecture et les versions](docs/fr/architecture.md), la [sécurité](docs/fr/securite.md), le [SDK](sdk/API.md), les [tests](docs/fr/tests.md), la [publication GitHub](docs/fr/publication.md) et la [communauté](docs/fr/communaute.md). Chaque guide dispose d’un équivalent anglais accessible dans le [README anglais](README.md).

Vérifications : `python3 tools/check_repo.py` puis `python3 tests/run.py`. Les tests utilisent des copies temporaires et des données fictives. Lisez le guide de tests. La CI fournie cible PHP 8.3/8.4/8.5 ; sa première exécution sur GitHub reste à effectuer. Les rapports historiques et les tests rejouables actuels sont distingués dans le guide.

## Participer et obtenir de l’aide

Questions et présentations de sites : [Discussions](https://github.com/farhadtoor/voilavoila-cms/discussions). Bugs reproductibles et propositions précises : [Issues](https://github.com/farhadtoor/voilavoila-cms/issues). Français et anglais acceptés. Lisez [CONTRIBUTING](CONTRIBUTING.fr.md), [SUPPORT](SUPPORT.md), le [code de conduite](CODE_OF_CONDUCT.md) et la [feuille de route](ROADMAP.md).

Le code est sous licence MIT. Le logo PASS fourni est exclu de cette licence ; voir [LICENSE](LICENSE) et [NOTICE](NOTICE). Aucun moteur WordPress ou de billetterie PASS n’est embarqué.

## Nouveautés 1.2

Documents téléchargeables, commentaires visiteurs modérés et import WordPress hors ligne. Les nouveaux plugins sont livrés désactivés.

[Documents](docs/fr/documents.md) · [Commentaires](docs/fr/commentaires.md) · [WordPress](docs/fr/wordpress.md) · [Mise à jour](docs/fr/mise-a-jour-1.2.md) · [GitHub FR/EN](GITHUB-v1.2.0-FR-EN.md).
