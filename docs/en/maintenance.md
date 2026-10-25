# Maintainer release routine

[Français](../fr/maintenance.md)

For the first publication, follow [publishing](publishing.md): original archives and existing tags are already supplied. The commands below are for a **future** patch, not for silently replacing 1.1.0.

1. Work on a branch. Describe the trigger, resulting behavior and affected versions. Keep private reports private until coordinated disclosure.
2. Update `VV_VERSION` in `core/bootstrap.php`, current version mentions and CHANGELOG, bilingual docs and release notes. Update a module manifest only when that module changes. Validate any storage/API migration and recovery path.
3. Run source hygiene, relevant HTTP checks, PHP/JS syntax and UI checks affected by the change. Require green GitHub jobs for supported versions. Read results; do not infer success from the presence of a workflow file.
4. Review and merge. From a clean checkout of the release commit, run the package tool. For example, **after** changing the application version to 1.1.1 and committing it:

```sh
python3 tools/build_release.py --version 1.1.1
git tag -a v1.1.1 -m "VoilaVoila CMS 1.1.1"
git push origin main
git push origin v1.1.1
gh release create v1.1.1 dist/VoilaVoila-CMS-1.1.1.zip dist/VoilaVoila-CMS-1.1.1.sha256 --verify-tag --draft --title "VoilaVoila CMS 1.1.1" --notes-file docs/releases/v1.1.1.md
```

5. The packager uses tracked files, rejects a dirty tree and private runtime data, generates a fresh SHA-256 manifest inside the ZIP and a companion checksum. Inspect/extract the generated ZIP and test a clean install plus the applicable upgrade before publishing the draft.
6. Mark the new current release as Latest, link upgrade/rollback instructions, credit contributions and announce known limits. Never move an already published version tag or replace an archive with different bytes under the same version. Publish a new version instead.

The source is maintained independently from a deployed site's private storage. Keep independent private deployment backups. A GitHub source release is not a backup of a live owner's site. Manage PHP/server updates separately. A contributor's native module must record its rights and any privileged behavior; do not imply that a listing is a security endorsement.
