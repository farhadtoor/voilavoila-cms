# Commentaires — plugin natif 1.0.0

`content/plugins/comments/` fournit un module facultatif pour **CMS 1.2.0+**, API 1. Il est livré désactivé et ne crée aucun compte utilisateur.

## Activer et modérer

1. Activez **Commentaires / Comments** dans Extensions.
2. Ouvrez le menu **Commentaires**. Les articles publics acceptent les messages ; les pages restent exclues par défaut.
3. Activez les pages dans les réglages du plugin si nécessaire.
4. Dans chaque éditeur, **Autoriser les commentaires si leur extension est active** permet de fermer un contenu.
5. Les messages reçus apparaissent **En attente**. Approuvez, remettez en attente, classez indésirable ou supprimez.

Les messages approuvés apparaissent sur leur contenu publié, 20 par page. Brouillons, contenus privés/futurs, aperçus, RSS et fil/recherche ne publient pas de commentaires. Fermer les nouveaux commentaires conserve les messages approuvés. Désactiver le réglage global masque tout le module.

## Données et protections

- Pseudonyme : 2–80 caractères ; message texte : 3–3 000. Aucun HTML actif, e-mail ou lien de profil.
- Modération obligatoire de chaque nouveau message.
- Jeton signé, cookie distinct du propriétaire, `HttpOnly`, `SameSite=Strict`, `Secure` en HTTPS, validité de deux heures.
- POST, contrôle d’origine/contexte intersite, champ leurre et accord explicite de publication.
- 5 tentatives par IP sur 15 minutes, 100 tentatives globales par heure ; identités de limitation hachées avec le secret du site.
- Modération propriétaire avec CSRF et contrôle de version contre les actions obsolètes.
- Aucun service externe, avatar distant, CAPTCHA, compte public, e-mail automatique ou script tiers.

Les messages sont dans SQLite, sous `cms_state.plugin_data.comments`. Pages/articles restent dans `contents`. Les transactions natives sont réutilisées sans nouvelle table. Limite : 1 000 messages ou 1 Mio, au premier plafond atteint. Les listes sont paginées.

Les messages ne stockent ni e-mail ni IP brute. Le journal d’accès peut conserver l’IP séparément. Le cookie sécurise le formulaire et n’est pas un identifiant marketing.

## Conservation et suppression

**Exporter les commentaires JSON** fournit réglages et messages au propriétaire. Les sauvegardes portables les incluent aussi.

Désactivation et suppression des fichiers du plugin conservent les données. Pour les effacer : **Supprimer tous les commentaires**, saisir `PURGE` et le mot de passe actuel. La suppression individuelle est aussi possible. Supprimer un article ne republie pas ses commentaires ailleurs : le propriétaire peut encore les exporter/supprimer.

Les commentaires WordPress importés sont tous en attente, même ceux précédemment approuvés. Indésirables, corbeille, pings et rétroliens sont exclus. Les réponses sont aplaties. Aucune preuve de consentement WordPress n’est inventée : vérifiez le contexte avant republication.

Ces plafonds conviennent à un petit site ; ils ne remplacent pas une modération et ne constituent pas un anti-spam complet.

[WordPress](wordpress.md) · [Documents](documents.md)
