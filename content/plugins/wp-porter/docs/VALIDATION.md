# Validation — WordPress Porter 1.0.0

Date: 25 October / octobre 2026. PHP 8.3.6 with ZIP, Tokenizer, DOM, mbstring, fileinfo and PDO SQLite for CMS integration. / Avec les extensions indiquées, sur une installation isolée.

- 44 CLI checks: headers, callbacks, filters/footer, template compiler, native output, inert byte-exact source references, unsupported business/Gutenberg/child themes, no source execution, bounded parsing, traversal, duplicate paths, symlinks, CRC, encryption and active HTML.
- 32 HTTP/CMS checks: one-owner install, native plugin install/activation, owner/CSRF controls, private guarded results, password downloads, native installation and rendering of generated plugins/themes, FR/EN content, 404, unsupported source never executed, retention, deletion, native reauth rate limit and CLI non-overwrite/no CMS initialization.
- Four browser checks: desktop screen, mobile 390 px without page overflow, English interface, no JavaScript runtime errors.

Total: **80 checks passed**. The plugin includes the portable 44-check CLI suite (`php tests/run.php`) and the synthetic example generator. The HTTP and browser reports describe an isolated CMS run; their external Python/Playwright harnesses are not installed into the PHP plugin. The AES encryption fixture ran in this environment; the CLI suite skips that one fixture if ZIP encryption creation is unavailable.

En français : 44 contrôles du moteur et des ZIP, 32 contrôles HTTP dans le CMS et quatre contrôles navigateur, soit **80 réussites**. Les tests CLI et exemples sont inclus. Les rapports HTTP/navigateur concernent un environnement isolé ; leurs lanceurs Python/Playwright externes ne sont pas installés dans l’extension PHP. Le cas AES est testé ici ; il est sauté si la création de ZIP chiffrés manque sur une autre machine.

Source CMS: supplied VoilaVoila-CMS-1.1.0(1).zip, SHA-256 `e56a1ddc98f24e1d33fd11713d0ff014a74fc7b7201a0af3ffaf3df1a3172029`. The CMS engine was not modified. / Le moteur du CMS n’a pas été modifié.

Actual real hosting/TLS, Apache/Nginx configuration, other PHP versions and a broad catalog of third-party WordPress packages have not been executed. No independent audit or universal compatibility is claimed. Use a maintained PHP branch with its latest patches for deployment.

Hébergement/TLS réels, configuration Apache/Nginx, autres versions PHP et vaste catalogue de modules tiers ne sont pas testés. Aucun audit indépendant ou compatibilité universelle n’est annoncé. Déployez avec une branche PHP maintenue et ses correctifs récents.

Machine-readable results / Rapports : [CLI](validation-unit.json), [HTTP](validation-integration.json), [browser / navigateur](validation-browser.json).
