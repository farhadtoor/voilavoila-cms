# Maintenance event example / Exemple d’événement de maintenance

Optional API 1 plugin for CMS 1.4.0, with no network dependency. / Extension API 1 facultative sans dépendance réseau.

Install `sdk/operations-example.zip` in Plugins and activate it. Run maintenance from Health & maintenance or the CLI. A local dashboard note records the last event. Deactivation preserves its data.

Importez `sdk/operations-example.zip` dans Extensions et activez-le. Lancez la maintenance depuis Santé & maintenance ou la CLI. Une note du tableau de bord conserve le dernier événement. La désactivation conserve ses données.

`maintenance_completed_v1` runs after the core transaction commits. This notification is best effort, with a unique run ID for consumer deduplication; it is not a durable delivery queue. The event contains no form values, credentials or log lines. The example writes only its own `plugin_data` key.

`maintenance_completed_v1` est émis après validation de la transaction. La notification est faite au mieux, avec un identifiant permettant la déduplication ; elle ne constitue pas une file de livraison durable. L’exemple écrit uniquement sa propre clé `plugin_data`.

MIT. Source code is in this folder. / Les sources sont dans ce dossier.
