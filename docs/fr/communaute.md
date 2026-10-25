# Créer et entretenir une petite communauté

[English](../en/community.md) · [Guide de publication](publication.md)

## Une promesse claire

Présentez toujours le projet comme un CMS personnel PHP/SQLite auto-hébergé, propriétaire unique, thèmes/extensions natifs. Adressez-vous d’abord aux personnes ayant ce besoin : sites personnels, indépendants et amateurs d’auto-hébergement PHP. Montrez un profil public et une courte démonstration de l’administration sur des données fictives. N’annoncez pas de billetterie, de remplacement complet de WordPress ou de sécurité auditée.

## Un espace par usage

Discussions accueille questions, idées et présentations ; Issues décrit les défauts confirmés ou tâches précises ; les PR servent à la relecture. Announcements porte versions et informations de maintenance. Les failles et problèmes de conduite passent par les canaux privés indiqués. Commencez avec GitHub avant d’ajouter un serveur de discussion à entretenir.

Labels proposés, dans Issues → Labels :

| Label | Usage |
| --- | --- |
| `bug` | Défaut reproductible |
| `enhancement` | Évolution délimitée |
| `documentation` | Guide ou exemple |
| `translation` | Cohérence FR/EN |
| `accessibility` | Clavier, contraste et outils d’assistance |
| `good first issue` | Petite tâche avec reproduction et critères de réussite |
| `help wanted` | Contribution d’implémentation recherchée |
| `needs reproduction` | Exemple minimal sûr manquant |
| `migration` | Transition de version ou de stockage |

## Premier mois — proposition

1. **Préparation :** terminer la publication, vérifier contact/droits, obtenir une CI verte et publier les deux versions, avec la 1.1.0 comme courante.
2. **Pilote :** inviter 5 à 10 volontaires dans des espaces où le partage de projets est autorisé. Demander version PHP, type d’hébergement, résultat de l’installation et un obstacle précis, sans journaux ou accès. Tenir un tableau de compatibilité anonymisé.
3. **Annonce :** adapter le texte fourni et le publier une fois dans les communautés PHP/auto-hébergement pertinentes, en respectant leurs règles. Ajouter capture/vidéo sur données fictives, dépôt et guide. Demander des retours d’installation, au-delà des étoiles.
4. **Suivi :** corriger les défauts confirmés les plus gênants, publier un correctif lorsqu’il est prêt, remercier les contributions et expliquer les choix. Garder quelques tâches de démarrage bien cadrées.

Les [textes d’annonce](../community/launch.fr.md), le [message d’accueil](../community/welcome.md) et des [idées de premières issues](../community/first-issues.md) sont prêts à adapter. Aucun message n’a été envoyé automatiquement.

## Traitement des retours

Confirmez version et reproduction locale. Demandez uniquement des éléments minimaux anonymisés. Orientez les questions vers Discussions avec courtoisie, liez les doublons et expliquez les refus hors périmètre. Pour une grosse demande, étudiez d’abord une extension native. Gardez les détails d’exploitation de failles en privé.

Choisissez un rythme réaliste, par exemple un tri hebdomadaire, sans délai de réponse impossible à tenir. Créditez les contributions dans les versions. Réservez les versions correctives aux réparations et documentez les changements de stockage/API avant diffusion. Voir la [maintenance](maintenance.md).

Mesurez installations réussies, blocages résolus, utilisateurs qui reviennent et contributions. Les étoiles montrent un intérêt sans prouver l’utilisation. Publiez les limites de validation et retirez les tâches abandonnées des suggestions pour débuter.
