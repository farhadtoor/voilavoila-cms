# Jalon 2 / Milestone 2 — version 1.2.1

## Périmètre / Scope

Livraison du correctif de stabilisation après 1.2.0 : arborescence propre, archive contrôlée, diagnostic d'hébergement, couverture réelle du navigateur et procédure de mise à jour/retour arrière. La publication GitHub et les pilotes réels restent des opérations distinctes.

Stabilization after 1.2.0: clean layout, checked archive, hosting preflight, runnable browser coverage and upgrade/rollback procedures. Hosted publication and real hosting pilots are separate operations.

## Décisions / Decisions

- API 1, propriétaire unique, contenus SQLite et formats de données existants conservés. / API 1, single owner, SQLite content and existing data formats retained.
- Aucun nouveau schéma, compte livré, service distant obligatoire ou système de licence. / No schema migration, shipped owner, mandatory remote service or licensing engine.
- Le diagnostic `Hosting` est en lecture seule ; `doctor` fonctionne avant le bootstrap de données. / Read-only preflight; doctor runs before data bootstrap.
- ZIP plat : version, commit et tous les fichiers sont contrôlés avant extraction. La vérification des fichiers installés couvre le manifeste, pas les ajouts personnalisés ou les données d'exécution. / Flat ZIP: checked version/commit/bytes before extraction; installed verification covers declared code only.
- Les copies résiduelles sous `files/` sont retirées et ces URL refusées. / Duplicate correction files removed and their HTTP paths denied.
- Les objets GD sont libérés sans appeler `imagedestroy`, obsolète depuis PHP 8.5. / Avoid the PHP 8.5-deprecated no-op.

## Chaîne de preuve / Evidence chain

1. Dépôt propre et contrôles du constructeur / Clean source and builder checks.
2. Construction depuis les octets du commit / Build from committed bytes.
3. Contrôle des chemins, modes, tailles, dépendances et manifeste / Paths, modes, limits, dependencies and manifest checks.
4. Extraction dans un dossier neuf / Extraction into a new directory.
5. Tests HTTP et migrations sur la copie extraite, sur trois PHP / HTTP/migrations against the extracted copy on three PHP runtimes.
6. Mises à jour et retour arrière depuis les sources 1.1 et 1.2 corrigée / Upgrade and rollback from 1.1 and corrected 1.2 source fixtures.
7. Parcours Chromium en sous-dossier / Chromium journey under a URL subdirectory.
8. Pilotes OVH/Infomaniak et publication après contrôle / Hosting pilots and publication after review.

## Contrôles disponibles / Available checks

| Suite | Contrôles / Checks |
| --- | ---: |
| HTTP, SQLite, médias, sécurité, migration, documents, commentaires et diagnostic | 190 |
| Construction, intégrité ZIP et vérification du code installé | 28 |
| Mise à jour 1.1 puis retour arrière / 1.1 upgrade and rollback | 11 |
| Mise à jour 1.2 puis retour arrière / 1.2 upgrade and rollback | 11 |
| WordPress Porter | 44 |
| Navigateur / Browser | 18 |

Ces nombres décrivent les suites ; le rapport JSON accompagnant la livraison identifie les exécutions réussies, versions et empreintes réelles. Les anciennes validations restent des références historiques. / These counts describe the suites; the accompanying JSON report identifies actual successful runs, versions and hashes. Older reports remain historical.

## Limites de qualification / Qualification limits

Les pilotes réels OVH et Infomaniak sont **en attente**. La nouvelle chaîne GitHub Actions ne peut être annoncée comme réussie qu'après son exécution sur le commit publié. Les tests locaux utilisent des données synthétiques et ne modifient pas un site en ligne.

Real OVH/Infomaniak pilots are **pending**. The new hosted workflow is not a passed run until executed on the published commit. Local checks use synthetic data, not a live site.

[Pilotes / Pilots](HOSTING-PILOT-FR-EN.md) · [Tests FR](fr/tests.md) · [Tests EN](en/testing.md) · [Mise à jour / Upgrade](../MISE-A-JOUR-1.2.1.md).
