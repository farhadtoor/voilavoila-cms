# Five-site pilot / Pilote de cinq sites — 1.4.0

Participation is voluntary. No hidden telemetry or automatic transmission is built into the CMS. / Participation volontaire, sans télémétrie ni envoi automatique.

| Anonymous site ID / Identifiant anonyme | Hosting offer + PHP / Offre + PHP | Install/upgrade + rollback / Installation/mise à jour + retour arrière | First form + received request / Premier formulaire + demande reçue | Incident/support minutes | Status / État |
| --- | --- | --- | --- | --- | --- |
| P01 | To record / À renseigner | To test / À tester | To test / À tester | — | Not started / Non commencé |
| P02 | To record / À renseigner | To test / À tester | To test / À tester | — | Not started / Non commencé |
| P03 | To record / À renseigner | To test / À tester | To test / À tester | — | Not started / Non commencé |
| P04 | To record / À renseigner | To test / À tester | To test / À tester | — | Not started / Non commencé |
| P05 | To record / À renseigner | To test / À tester | To test / À tester | — | Not started / Non commencé |

Include one actual OVH installation and one actual Infomaniak installation when volunteers have those offers. Record the real offer, PHP version, extensions, storage location choice and cron availability. / Inclure un OVH et un Infomaniak réels si les volontaires disposent de ces offres ; noter les réglages réels.

## Checklist / Parcours

1. Obtain permission and assign a P01–P05 identifier. Never collect passwords, API keys, OTP seeds, private databases or raw form submissions. / Recueillir l’accord et attribuer un ID ; ne pas collecter ces secrets.
2. Install or upgrade following the guide and perform a code + private-state rollback rehearsal on a test copy. / Suivre le guide et répéter un retour arrière sur copie de test.
3. Check private HTTP paths, owner authentication, OTP when enabled, original public/private media and both languages. / Vérifier accès privés, authentification, OTP, médias et langues.
4. Create a real first form, submit a non-sensitive test request, verify the private inbox and the actual notification reception separately from transport acceptance. / Vérifier boîte privée et réception réelle du mail séparément.
5. Review a WXR fixture, confirm it, approve a redirect, then make its target private and verify that redirect stops. / Tester le sas d’import et la visibilité des redirections.
6. Schedule maintenance; confirm retention, recent backups, a restore test and visible unknown/failed notification states. / Vérifier cron, rétention, restauration et statuts d’envoi.
7. Collect only voluntary counts: install time, activation, repeat usage, incidents, support minutes and requested improvements. Export the sanitized health JSON only after reviewing it. / Compter volontairement les usages et incidents, relire le diagnostic avant partage.
8. Convert issues into reproducible bug reports: CMS/PHP versions, synthetic reproduction, expected/actual result and recovery steps. / Créer des rapports reproductibles avec données synthétiques.

Pilot acceptance: repeat useful usage, no lost requests, working recovery and maintenance cost understood. An unstarted row is not deployment evidence. / Critères : usage répété utile, aucune demande perdue, récupération validée et coût de support compris. Une ligne vide ne prouve aucun déploiement.
