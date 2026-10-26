# Pilote d'hébergement / Hosting pilot — 1.2.1

**Statut : à effectuer sur les offres réelles / Status: pending on actual hosting plans.**

La préparation 1.2.1 fournit des tests locaux, un diagnostic et cette fiche. Elle n'inclut pas d'accès à un compte OVH ou Infomaniak et ne valide pas leurs installations à distance.

| Hébergeur / Provider | Offre / Plan | PHP web | PHP CLI | Installation | Mise à jour / Upgrade | Retour arrière / Rollback |
| --- | --- | --- | --- | --- | --- | --- |
| OVHcloud | À renseigner / Record | Pending | Pending | Pending | Pending | Pending |
| Infomaniak | À renseigner / Record | Pending | Pending | Pending | Pending | Pending |

## Français — procédure

1. Créer un site ou sous-domaine de test HTTPS avec un répertoire dédié. Relever la véritable racine web et l'offre utilisée.
2. Sélectionner PHP 8.3+ depuis le gestionnaire de l'hébergeur ; vérifier séparément le PHP utilisé en SSH si cette option existe.
3. Envoyer le ZIP vérifié et tous les fichiers cachés. Régler le stockage selon le modèle de permissions du compte, sans droits universels.
4. Avec SSH, exécuter `php bin/console.php doctor --json`. Sans SSH, terminer l'installation puis consulter **Outils → Vérification de l'hébergement**.
5. Vérifier DOM, mbstring, fileinfo, PDO SQLite, GD avec JPEG/PNG/WebP et ZIP. Les recommandations sont mémoire 256M, upload 64M et POST 70M. Les limites réelles de l'offre peuvent être différentes.
6. Vérifier installation, connexion, page/article FR/EN, révision, image publique et privée, PDF, DOCX, ZIP et CSV.
7. Activer Commentaires, envoyer un message visiteur et le modérer. Tester un petit WXR et ZIP local, puis un réimport sans doublon.
8. En visiteur déconnecté, contrôler HTTP 403/404 pour `storage/cms.sqlite`, `storage/logs/error.txt`, `core/bootstrap.php`, `bin/console.php` et `files/tests/probe.txt`. Pour un test du dernier chemin, placer uniquement un fichier texte synthétique sans secret, puis le retirer.
9. Rejouer les cas dans un sous-dossier si cet emplacement est utilisé. Pour Nginx, adapter les règles à ce préfixe.
10. Exporter/restaurer une sauvegarde de test, mettre à jour une copie 1.1 ou 1.2, puis vérifier le retour arrière du code et de l'état privé.
11. Relever le statut, la date, la version PHP, les limites et l'erreur expurgée pour chaque cas. Conserver les rapports hors des accès publics.

Le diagnostic est en lecture seule et ne prouve pas à lui seul la protection HTTP. Le stockage externe utilise `VV_STORAGE_PATH` dans l'environnement PHP réellement chargé ; un fichier `.env` seul n'est pas lu par le CMS.

### Sauvegarde cohérente

Une copie brute de SQLite pendant des écritures peut être incohérente. Utiliser une sauvegarde adaptée à SQLite, ou suspendre tous les écrivains avant une copie de fichiers. Garder les médias du même état et stocker la copie contenant les identifiants hors de toute racine publique. La maintenance du CMS bloque l'usage public principal ; elle ne remplace pas un verrou global ou l'arrêt des autres écrivains.

### Références

- [Aide OVHcloud](https://help.ovhcloud.com/) — rechercher la configuration PHP et les sauvegardes correspondant à l'offre réelle.
- [Infomaniak : version PHP d'un site](https://www.infomaniak.com/fr/support/faq/2053/modifier-la-version-de-php-dun-site).
- [SQLite : méthodes de sauvegarde cohérente](https://www.sqlite.org/backup.html).

## English — procedure

Use a dedicated HTTPS pilot site and record the actual plan and document root. Choose PHP 8.3+, verify the web and optional CLI configurations separately, transfer hidden files and check the required modules/limits.

Run the read-only CLI doctor, or use the private Tools panel after installation. Exercise fresh installation, owner sign-in, bilingual publishing/revisions, original public/private media, every supported document type, comment moderation and repeat offline WordPress imports.

Check the private paths listed above as a signed-out visitor. Recheck under a URL subdirectory where applicable. Complete a synthetic backup/restore, upgrade and matching code/private-state rollback.

Record dates, configuration, statuses and redacted errors. Do not publish real storage, identities or logs in Issues. A successful preflight does not attest HTTP privacy.

For filesystem backups, stop all writers or use a suitable SQLite backup method, retain matching media and store credential-bearing backups outside every public document root. CMS maintenance is not a global database lock. Configure `VV_STORAGE_PATH` in the actual PHP environment; the CMS does not load a standalone `.env` file.
