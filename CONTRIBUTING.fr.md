# Contribuer

[English](CONTRIBUTING.md)

Les rapports de bugs, corrections de documentation, traductions, vérifications d’accessibilité, thèmes natifs et évolutions ciblées sont bienvenus.

## Avant de commencer

Lisez le [périmètre et l’architecture](docs/fr/architecture.md), la [feuille de route](ROADMAP.md), le [code de conduite](CODE_OF_CONDUCT.md) et la [politique de sécurité](SECURITY.md). Utilisez Discussions pour les questions et idées initiales. Présentez les évolutions importantes dans une issue avant de les développer. Une proposition ne garantit pas son intégration.

## Travail local

1. Faites un fork du dépôt, puis clonez votre fork. Partez de `main`.
2. Créez une branche ciblée : `git switch -c fix/description-courte`.
3. Utilisez une installation locale jetable. Les tests ne doivent jamais viser le stockage de production.
4. Limitez la modification à un objectif cohérent. Conservez le propriétaire unique et, si possible, la compatibilité avec l’API native 1.
5. Exécutez les vérifications ci-dessous et actualisez les documents français et anglais concernés. Si vous avez besoin d’aide pour traduire, indiquez-le dans la PR.
6. Ouvrez une pull request expliquant le problème, le résultat, les vérifications et l’impact de migration. Référencez l’issue et évitez les changements de formatage sans rapport.

```sh
python3 tools/check_repo.py
python3 tests/run.py
node --check assets/admin.js
node --check assets/site.js
```

Les tests fonctionnels demandent PHP et ses extensions ; Node sert uniquement aux vérifications JavaScript. Consultez le [guide de tests](docs/fr/tests.md). Ajoutez une vérification de régression ciblée lorsqu’elle aide à valider un changement de comportement. Une correction documentaire demande une relecture et un contrôle des liens, sans nouveau test d’exécution.

## Conventions

Suivez les conventions des fichiers existants. Utilisez des requêtes préparées, `Store::change()` pour les écritures, une validation serveur et des sorties HTML échappées. Les fichiers PHP des modules exigent la protection `VV_ROOT`. N’imbriquez pas les transactions. Une extension écrit dans son propre espace `plugin_data`. Discutez toute nouvelle dépendance d’exécution et son coût de maintenance.

N’ajoutez jamais le stockage réel, les bases, clés, cookies de session, images privées, journaux bruts ou sauvegardes. Employez des exemples fictifs sur `example.test`. Relisez `git diff --cached` avant l’envoi. Le script d’hygiène ne détecte pas tous les secrets possibles.

## Relecture et licence

Le mainteneur peut demander une correction, découper une proposition ou expliquer un refus hors périmètre. Les échanges sont bénévoles, sans délai garanti. Vous devez pouvoir soumettre vos contributions et permettre leur diffusion sous la licence du code du projet. Documentez l’origine et les droits des ressources tierces dans NOTICE. Ce modèle n’impose ni CLA ni procédure DCO.

Pour commencer : correction précise d’un guide, recette d’hébergement vérifiée, amélioration du clavier ou test d’un défaut signalé. Le label `good first issue` sera utilisé pour des tâches délimitées par le mainteneur.
