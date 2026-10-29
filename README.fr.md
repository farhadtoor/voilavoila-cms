# VoilaVoila CMS 1.5.1

CMS personnel natif, propriétaire unique, FR/EN, PHP 8.3+ et SQLite par défaut.
Native personal CMS, single owner, bilingual, PHP 8.3+, SQLite by default.

**Cette version regroupe 1.5 et 1.6 :** modules ZIP avec examen/mise à jour/retour arrière, quarantaine des dépôts, édition FR/EN, navigation, médias et couche de base interchangeable. Le moteur et le portage automatique WordPress ont été retirés ; seul l’import éditorial hors ligne est conservé.

- [Notes 1.5 / Release](RELEASE-1.5.0-FR-EN.md)
- [Installation et déploiement / Deployment](docs/DEPLOYMENT-1.5.0.md)
- [Mise à jour 1.4 vers 1.5 / Upgrade](UPGRADE-1.5.0-FR-EN.md)
- [Base interchangeable / Database](docs/DATABASE-1.5.0.md)
- [Contrat natif / SDK](sdk/API.md)
- [Validation locale / Local validation](VALIDATION-1.5.0.md)

`install.php` accepte la première installation en local ou en HTTPS avec la clé privée à usage unique ; il se verrouille après création du propriétaire. La CLI reste disponible. Les validations de quarantaine réutilisent la confirmation du mot de passe pendant 15 minutes. ClamAV est une intégration optionnelle à configurer sur l’hébergement et peut être rendu obligatoire.

The one-time installer is available locally or over HTTPS with the private setup key, then locks after the owner is created. CLI installation is also available. Quarantine actions reuse password confirmation for 15 minutes. ClamAV must be separately configured on the host and can be made mandatory.

