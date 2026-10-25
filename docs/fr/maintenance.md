# Routine de publication du mainteneur

[English](../en/maintenance.md)

Pour la première publication, utilisez le [guide initial](publication.md) : archives et tags originaux sont préparés. Les commandes suivantes concernent un **futur** correctif, pas un remplacement silencieux de la 1.1.0.

1. Travaillez dans une branche. Décrivez le déclencheur, le comportement obtenu et les versions touchées. Conservez les rapports de failles privés jusqu’à une divulgation coordonnée.
2. Actualisez `VV_VERSION` dans `core/bootstrap.php`, les mentions de version courante, CHANGELOG, guides FR/EN et notes de version. Un manifeste de module ne change que si ce module évolue. Vérifiez migration de stockage/API et récupération.
3. Exécutez l’hygiène du dépôt, les tests HTTP pertinents, syntaxes PHP/JS et contrôles d’interface concernés. Exigez les jobs GitHub verts pour les versions prises en charge. L’existence d’un workflow ne prouve pas sa réussite.
4. Relisez et fusionnez. Depuis une copie propre du commit de version, construisez le ZIP. Exemple **après** passage du moteur à 1.1.1 et commit :

```sh
python3 tools/build_release.py --version 1.1.1
git tag -a v1.1.1 -m "VoilaVoila CMS 1.1.1"
git push origin main
git push origin v1.1.1
gh release create v1.1.1 dist/VoilaVoila-CMS-1.1.1.zip dist/VoilaVoila-CMS-1.1.1.sha256 --verify-tag --draft --title "VoilaVoila CMS 1.1.1" --notes-file docs/releases/v1.1.1.md
```

5. L’outil utilise les fichiers suivis, refuse un arbre modifié et des données privées, ajoute un manifeste SHA-256 neuf dans le ZIP et une empreinte à côté. Inspectez/décompressez le ZIP et testez installation vierge et mise à jour concernée avant de publier le brouillon.
6. Marquez la version courante Latest, liez migration/retour arrière, créditez les contributions et annoncez les limites connues. Ne déplacez pas un tag publié et ne remplacez pas son archive par des octets différents. Créez une nouvelle version.

Le code source et le stockage privé d’un site déployé restent distincts. Conservez des sauvegardes privées indépendantes. Une version GitHub n’est pas la sauvegarde du site d’un propriétaire. Entretenez PHP et le serveur séparément. Les modules contribués doivent documenter leurs droits et comportements privilégiés ; leur référencement ne vaut pas audit de sécurité.
