# SEO 1.1 — CMS 1.4.0

Native optional API 1 plugin. No external service or production JavaScript dependency.

Enable SEO in Plugins. Configure the HTTPS canonical site URL in My profile & site. The SEO settings page manages homepage titles/descriptions in French and English, the public sharing image, homepage indexing and supported structured data. Each content editor has its own SEO & social sharing panel.

Empty values use the active theme’s title/description and native content. Core metadata normalization emits one title, description, robots tag, canonical and supported social metadata, while retaining scripts/styles and body bytes. Deactivation keeps content metadata but returns public rendering to the theme’s useful defaults.

Visible public content only is included in the bilingual XML sitemap and RSS. Per-content exclusions, drafts, private posts, previews, future publications and site-wide noindex are respected. Paginated lists use their own canonical URL. Supported JSON-LD is limited to WebPage and BlogPosting; no fabricated Organization, Product, ratings or business facts are emitted.

Editorial backups include SEO metadata and homepage configuration. Restore validates text and public image IDs at rendering time. MIT, inherited from the CMS.

## Français

Activez SEO dans Extensions puis renseignez l’URL HTTPS du site. Le menu Référencement SEO règle l’accueil en FR/EN, l’image de partage publique, l’indexation et les données structurées prises en charge. Chaque article/page dispose de son propre volet.

Un champ vide reprend le thème et le contenu natif. Le noyau émet un seul jeu de métadonnées, en conservant les octets des scripts/styles et du corps de page. La désactivation conserve les réglages mais rend les valeurs publiques par défaut du thème.

Le sitemap bilingue et le RSS excluent les contenus privés, brouillons, aperçus, publications futures et exclusions SEO. Les schémas restent limités à WebPage et BlogPosting, sans invention d’organisation, d’avis ou de produit. Les sauvegardes éditoriales conservent les réglages. Licence MIT du CMS.
