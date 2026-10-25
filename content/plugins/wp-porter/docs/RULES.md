# Implemented rules / Règles implémentées

## Plugins / Extensions

The accepted program consists of optional strict-types/ABSPATH guards, named constant callbacks and top-level registrations. Closures are allowed. Callback bodies must have a single return/echo expression. Parameters, classes, namespaces, includes, arbitrary statements and unknown functions require manual work, except the one content parameter supported for `the_content` filters. / Le programme accepté comprend les préambules connus, fonctions constantes et enregistrements à la racine. Closures acceptées. Un seul return/echo ; PHP arbitraire/classes/espaces de noms/includes demandent un portage, sauf le paramètre de contenu du filtre pris en charge.

| WordPress pattern | Generated native behavior / Résultat natif |
| --- | --- |
| `add_shortcode('tag', callback)` with zero parameters, constant return | `vv_shortcode`; HTML cleaned by `VV\Content::html` / HTML nettoyé |
| `add_action('wp_footer', callback[, priority, 0])`, constant echo | `vv_action('footer', ...)`; HTML cleaned / HTML nettoyé |
| `add_filter('the_content', callback[, priority, 1])` | `vv_action('content_html', ...)`; constant concatenation around one content parameter, or replacement / concaténation constante ou remplacement |
| `__`, `_e`, simple `esc_*` wrappers around constants | Literal source text; no WordPress translation catalogs / texte source sans catalogues WordPress |

Only simple `[tag]` names supported by the native CMS are accepted. No attributes, nested/enclosing shortcode parsing, database access, payment behavior or remote service adapter is generated. If any required behavior is unsupported, the module entry is a draft. / Aucun attribut, contenu englobé, SQL, paiement ou adaptateur distant généré. Un comportement requis non pris en charge entraîne un brouillon.

## Classic themes / Thèmes classiques

PHP is parsed into a restricted instruction tree. Supported structures: static HTML, echo expressions, constant strings/concatenation, supported calls, `if/else` and `while(have_posts())` in colon or brace syntax. No assignments, arbitrary loops, custom function calls or PHP includes are executed. `get_header/footer/sidebar/template_part` require literal paths and a complete non-recursive compiled graph.

Le PHP devient un arbre d’instructions limité : HTML statique, echo, chaînes/concaténations, appels pris en charge, if/else et boucle have_posts en syntaxe alternative ou accolades. Pas d’assignation, boucle arbitraire, fonction personnalisée ou inclusion PHP exécutée. Les parties exigent des chemins littéraux et un graphe complet sans récursion.

Fields: title, content, excerpt, permalink, date, ID and post type; basic bloginfo fields; theme asset URLs; home/site root URL; basic context predicates. Argument variants are restricted. Template loops read native content. `wp_nav_menu` renders public CMS pages marked for navigation, using the `menu_class` option only; it does not rebuild a WordPress menu. `get_search_form` renders the native `q` search. `wp_head/footer` call native hooks. The default date is UTC `Y-m-d`. `wp_body_open` is a no-op. Source static labels are not translated automatically.

Champs : titre, corps, résumé, lien, date, ID, type ; bloginfo de base, URLs de ressources, racine du site et conditions simples. Les variantes d’arguments sont limitées. Navigation = pages publiques natives marquées menu ; seule l’option menu_class est reprise, sans reconstruire les menus WordPress. Recherche = champ q natif. Head/footer = hooks natifs. Date par défaut UTC Y-m-d. wp_body_open n’a pas d’action. Les libellés statiques ne sont pas traduits automatiquement.

`functions.php` is never executed. Known local stylesheet enqueues can be read statically; their conditions/dependency/media/version semantics are not reproduced. Classic routing covers home/front-page, page, single/singular, 404 and literal partials. Custom archive/taxonomy/author templates are not a native route subsystem. `theme.json` is a reference, not a Gutenberg renderer.

functions.php n’est jamais exécuté. Certains styles locaux sont identifiés statiquement, sans reproduire conditions/dépendances/media/version. Les routes natives couvrent accueil, page, article, 404 et parties littérales. Archives, taxonomies et auteurs WordPress ne deviennent pas un moteur de routes supplémentaire. theme.json reste une référence.

Resources copied by an automatic theme port: local CSS, JPG/JPEG, PNG, WebP and WOFF2. No arbitrary JS is loaded; enqueueing it is a manual blocker. SVG/GIF/TTF/WOFF references and remote CSS resource URLs require manual adaptation. This is a deliberately limited converter, not a claim that all remaining resource references can be resolved without visual review.

Ressources reprises : CSS local, JPG/JPEG, PNG, WebP et WOFF2. Le JS arbitraire n’est pas chargé ; son enqueue crée un blocage. Références SVG/GIF/TTF/WOFF et ressources CSS distantes demandent une adaptation. Une relecture visuelle reste nécessaire pour les autres références.

## Native module completion / Finaliser un module

1. Use `porting/report.json` for source file names, line locations, detected capabilities and native API suggestions. API samples are capped at 12 locations per name; displayed issues are capped at 150 while blocker totals remain accurate.
2. Rewrite data access under `plugin_data[your-id]` through `Store::change`; no nested transactions. Do not translate `$wpdb` into a public SQL endpoint.
3. Use the native owner/POST/CSRF controller for admin writes and current-password reauthentication for sensitive operations. One owner is not a WordPress roles system.
4. Port client JavaScript to explicit native endpoints and permissions. There is no automatic equivalent of admin-ajax.php or WordPress REST.
5. Review rights, generated code and inactive references. Validate behavior, privacy, deployment headers and compatibility in a disposable CMS copy before activation.

En français : utilisez le rapport pour localiser les appels ; réécrivez les données sous plugin_data via Store::change ; utilisez les contrôles propriétaire/POST/CSRF et la réauthentification native ; concevez explicitement les endpoints JS ; relisez et testez avant activation. Les correspondances sont des indications, pas des migrations métier automatiques.

## Primary references / Références officielles

- https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
- https://developer.wordpress.org/reference/functions/add_shortcode/
- https://developer.wordpress.org/reference/hooks/the_content/
- https://developer.wordpress.org/themes/classic-themes/basics/template-files/
- https://developer.wordpress.org/themes/templates/templates/
- https://www.php.net/manual/en/function.token-get-all.php

The target native API is the one shipped in VoilaVoila CMS 1.1.0 sdk/API.md. / L’API cible est celle du sdk/API.md fourni avec CMS 1.1.0.
