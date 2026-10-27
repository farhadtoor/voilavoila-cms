# Développer un module natif

[English](API.en.md)

L’API est propre à VoilaVoila CMS (version 1). Elle ne définit pas les fonctions WordPress. Un module PHP est du code de confiance exécuté avec les droits de l’hébergement. Gardez les contenus et réglages dans le stockage, pas dans les fichiers du thème.

## Manifeste

Un dossier de module contient `cms.json` :

```json
{
  "api": 1,
  "id": "mon-module",
  "type": "plugin",
  "name": "Mon module",
  "version": "1.0.0",
  "description": "Description française",
  "description_en": "English description"
}
```

Identifiant : lettre minuscule puis lettres, chiffres et tirets, 2 à 51 caractères. Le nom du dossier est l’identifiant. Pour une extension, ajoutez `plugin.php`. Pour un thème, utilisez `type: "theme"`, `index.php` et `style.css`.

Tous les fichiers PHP doivent commencer par :

```php
<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
```

Zipez le dossier complet, ou son contenu avec `cms.json` à la racine. L’installateur accepte un seul dossier racine, au plus 400 fichiers, 12 Mo compressés, 20 Mo décompressés, 8 Mo par entrée, sans liens symboliques ou chemins cachés. Les extensions de fichiers autorisées sont PHP, JSON, CSS, JS, PNG, JPG, WebP, WOFF2, MD et TXT. Le ZIP est installé sans être activé. Une mise à jour de code se fait par SFTP après sauvegarde et désactivation.

## Actions et filtres

```php
vv_action('head', function ($app, $current) {
    echo '<meta name="mon-module" content="actif">';
}, 10);

vv_action('content_html', function ($html, $content) {
    return $html; // Une fonction de filtre retourne la valeur modifiée.
});

vv_shortcode('bonjour', function () {
    return '<p>' . e(t('Bonjour !', 'Hello!')) . '</p>';
});
```

Les tags sont simples : `[bonjour]`. Ils n’acceptent pas d’attributs ni de code utilisateur. La sortie HTML d’une extension est sous sa responsabilité. Échappez les textes avec `e()` et validez les URL avec `VV\Content::link()`.

| Point | Arguments | Usage |
| --- | --- | --- |
| `init` | `$app` | Après chargement des extensions actives |
| `public_route` | `$app` | Avant le thème ; une route de lecture peut produire XML et terminer |
| `head` | `$app`, `$current` | Métadonnées publiques |
| `profile_links` | `$app` | Liens du rail de navigation |
| `profile_after` | `$app` | Compléments sous le profil |
| `sidebar` | `$app` | Rail contextuel |
| `footer` | `$app` | Fin du corps public |
| `admin_dashboard` | `$app` | Widget privé du tableau de bord |
| `content_html` | `$html`, `$content` | Filtre de contenu public |

`vv_action` enregistre aussi les filtres ; `vv_filter` les applique et transporte la valeur de retour. Les priorités sont croissantes. `vv_do` exécute une action. Utilisez des callbacks nommés avec un préfixe propre à votre module pour éviter les collisions si vous définissez des fonctions.

## Page d’administration et données privées

```php
$vv->adminPage('mon-module', t('Mon module', 'My module'),
    function ($app) {
        $note = (string) vv_option('mon-module', 'note', '');
        echo '<form method="post">' . csrf_field();
        echo '<input type="hidden" name="action" value="plugin.save">';
        echo '<label>Note<textarea name="note">' . e($note) . '</textarea></label>';
        echo '<button class="button primary">' . e(t('Enregistrer', 'Save')) . '</button></form>';
    },
    function ($app, $post) {
        $note = VV\Content::text(val($post, 'note'), 500);
        $app->store->change(function (&$state) use ($note) {
            $state['plugin_data']['mon-module']['note'] = $note;
            VV\Store::log($state, 'mon_module_saved');
        });
    }
);
```

Le contrôleur central exige déjà authentification, POST, origine et CSRF avant le callback `save`. Une action qui exporte des données, supprime des fichiers ou change un accès doit en plus appeler `VV\Security::reauth($app->store, $motDePasseActuel)` et respecter les permissions locales. Les callbacks publics ne bénéficient pas de cette autorisation.

`$app->state` est un instantané de lecture. La version 1.1 conserve cette façade avec SQLite. Les contenus sont des lignes SQL et ne doivent pas être écrits dans `cms_state` par une extension. Pour écrire, utilisez **toujours** `$app->store->change(...)`, qui relit l’état sous verrou. Écrivez uniquement sous `plugin_data[identifiant]`. N’ouvrez pas une nouvelle transaction pendant ce callback ; le verrou n’est pas réentrant. Après écriture, appelez `$app->refresh()` si vous devez relire dans la même requête. La désactivation d’un module conserve ses données.

## Thèmes

Contexte fourni à `index.php` et aux modèles : `$app`, `$current` (ou null), `$items`, `$query`, `$page`, `$per`, `$total`, `$notFound`, `$owner` et `$preview`.

Modèles facultatifs : `home.php`, `page.php`, `single.php`, `404.php`. Le moteur utilise le premier modèle demandé existant puis `index.php`. Le thème Profil Or partage sa structure avec Profil Graphite. Pour un thème totalement indépendant, copiez Profil Or et remplacez son manifeste, ses modèles et son CSS.

Fonctions utiles :

```php
e($texte);                              // Échappement HTML
t('Texte français', 'English text');     // Langue de l’interface
lang();                                // fr ou en
url('index.php', ['p'=>'a-propos']);      // URL locale, sous-dossier inclus
Content::field($content, 'title');       // Champ traduit, avec repli
Content::url($content);                 // Lien d’un contenu
$app->body($content);                   // Corps nettoyé + shortcodes + filtres
$app->publicItems('post', $recherche);   // Contenus publiés arrivés à échéance
```

Chargez les CSS/JS par fichiers locaux, avec `<link>` et `<script src="..." defer>`. La CSP native refuse les gestionnaires inline et les services tiers. Conservez les hooks `head`, `footer` et ceux dont votre présentation a besoin.

Les archives `bonjour-plugin.zip` et `signature-theme.zip` du SDK sont des exemples installables, pas des dépendances du moteur.

## Ajouts du CMS 1.2

Le manifeste peut fournir `requires_cms` et `requires_php`, versions minimales numériques (ex. `"1.2.0"` et `"8.3"`). L’installation et l’activation vérifient ces valeurs. Un plugin devenu incompatible est désactivé au chargement ; le thème retombe sur Profil Or. Il n’existe pas encore de graphe de dépendances ou de serveur de mises à jour signé.

`vv_action($nom, $callback, $priorite)` **enregistre** les callbacks, y compris les filtres. `vv_filter($nom, $valeur, ...$arguments)` **applique** un filtre ; ce n’est pas une fonction d’enregistrement.

Le filtre `wordpress_import_state_v1` reçoit `($state, $event)` et retourne le nouvel état. L’événement contient `import_comments`, des commentaires normalisés et `post_map`. Il s’exécute **dans la transaction d’import existante** : aucun appel imbriqué à `Store::change`, aucune requête réseau, aucun affichage. Une exception annule l’import. Le plugin Commentaires est le premier consommateur ; les autres plugins ne doivent pas modifier ses données.

Les pièces jointes utilisent `Media::metadata` et `Media::upload`, puis `media.php` pour la délivrance. Les validations internes de noms/ZIP/XML ne constituent pas une API de détection antivirus. Le mode hérité de `metadata` est réservé aux données 1.0 ; ne l’utilisez pas pour contourner le contrôle extension/MIME.

Le formulaire visiteur de Commentaires utilise `Security::guestToken` / `guestPost`, distincts de la session propriétaire. `postOrigin` seul n’est **pas** une autorisation ni une vérification CSRF. Toute action administrative doit conserver `Security::post` et le contrôle propriétaire.

## API 1 additions in 1.3 / Ajouts 1.3

`$app->currentContent()` exposes content only during `$app->body()` rendering; shortcode callbacks still take zero arguments. `backup_restore_data_v1` filters validated editorial backup data before any restore writes. Active plugins must reject incompatible schemas. `forms_received_v1` runs after commit with identifiers only; `forms_transport_v1` is a trusted deferred transport returning `accepted`, `failed` or `unknown`. See [Forms](../docs/FORMS.fr.md).

## CMS 1.4.0

[Versioned contracts / Contrats versionnés](../docs/MILESTONE-4-FR-EN.md) · [Installable maintenance example / Exemple installable](operations-example/README.md).

Register value filters with `vv_action`; `vv_filter` applies them. `content_record_v1` runs inside the native transaction and must not start another transaction or external I/O. `maintenance_completed_v1` runs after commit; delivery is best effort. / Déclarez les filtres avec `vv_action`, gardez les callbacks de valeur purs et dédupliquez les événements avec leur identifiant.
