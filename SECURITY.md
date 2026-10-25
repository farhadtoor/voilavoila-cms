# Security policy / Politique de sécurité

## English

The current 1.2.x line is the target for fixes. Version 1.0.0 is kept for history and migration; upgrade to 1.2.x. There is no promised support SLA or external security certification.

**Do not disclose vulnerabilities, proof-of-concept attacks or real private data in public issues or discussions.** Use [GitHub private vulnerability reporting](https://github.com/farhadtoor/voilavoila-cms/security/advisories/new). The maintainer must enable this repository feature before announcing the project. If it is unavailable, contact **email@farhad.fr** privately first, without attaching secrets, to arrange a secure exchange.

Include the affected version, deployment type, prerequisites, minimal reproduction using synthetic data, expected/actual behavior and impact. Identify a safe way to contact you. Coordinate disclosure with the maintainer; no fixed response time or bounty is offered. Testing must be limited to installations you own or are explicitly authorized to assess.

The [security design](docs/en/security.md) covers storage exposure, native extension trust, original media metadata and backup privacy. Redact cookies, owner/recovery/setup credentials, paths, IPs and personal content before sharing diagnostics.

## Français

Les corrections visent la branche courante 1.2.x. La 1.0.0 reste une référence historique et de migration ; passez à la 1.2.x. Aucun délai contractuel de support ni audit externe n’est annoncé.

**Ne publiez pas de faille, de démonstration d’attaque ou de données privées dans une issue ou discussion publique.** Utilisez le [signalement privé GitHub](https://github.com/farhadtoor/voilavoila-cms/security/advisories/new). Le mainteneur doit activer cette fonction avant d’annoncer le projet. Si elle n’est pas accessible, contactez d’abord **email@farhad.fr** en privé, sans joindre de secrets, pour convenir d’un échange sûr.

Précisez la version, l’hébergement, les conditions nécessaires, une reproduction minimale sur données fictives, le résultat attendu/obtenu et l’impact. Indiquez comment vous joindre. Coordonnez la divulgation avec le mainteneur ; aucun délai fixe ni prime n’est promis. Testez uniquement les installations vous appartenant ou pour lesquelles vous disposez d’une autorisation explicite.

Le [guide de sécurité](docs/fr/securite.md) décrit le stockage, la confiance accordée aux extensions, les métadonnées d’images et les sauvegardes. Retirez cookies, identifiants, codes, chemins, IP et contenus personnels des diagnostics partagés.
