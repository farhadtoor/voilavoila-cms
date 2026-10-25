# Publish the two versions on GitHub

[Français](../fr/publication.md)

This guide is for the project owner. The kit prepares local files; it does not create a repository, send messages or publish a release. You choose when to run the GitHub commands.

## 1. Prepare your identity and repository

Install Git and Python 3; GitHub CLI (`gh`) is optional but used below. Choose your public commit name, Git author email (your GitHub noreply email is allowed), repository owner/name and a **working private contact channel** for conduct/security reports. A noreply email cannot receive reports.

Before public distribution, review LICENSE/NOTICE and the PASS logo rights. Original source notices are preserved; the supplied copyright has no named holder. Clarify attribution as the actual rights holder if needed. Do not claim the PASS logo is MIT-licensed. No account, database, media library or private log is included in the source template.

Unzip the full GitHub kit, open a terminal inside `VoilaVoila-GitHub-Kit` and run (replace all example values):

```sh
python3 prepare-repository.py --github "farhadtoor/voilavoila-cms" --name "Your public name" --email "you@example.com" --contact "contact@example.com"
```

The default output is the sibling folder `../voilavoila-cms-git`; an existing destination is refused. The script verifies the original ZIP checksums, imports 1.0.0, tags `v1.0.0`, imports 1.1.0, tags `v1.1.0`, then adds configured docs/tests/CI on `main`. It uses your local Git identity for this import and records present-time commits; it does not invent past development history. Neither tag includes the later documentation commit. No remote or push is performed.


For an organization repository, also pass `--maintainer your-user-login` so CODEOWNERS names a real user rather than the organization.

## 2. Review locally

```sh
cd ../voilavoila-cms-git
python3 tools/check_repo.py
git status --short
git log --oneline --decorate -3
git tag --list
git ls-files storage
git diff v1.1.0 --stat
```

`git ls-files storage` must show only `storage/.htaccess` and `storage/index.php`. Read the README links, private contact and NOTICE. Run `python3 tests/run.py` when PHP is available. The test creates a temporary copy, not a real installation. Do not create a live owner in the source repository before publishing. Never add storage with `git add -f`.

## 3. Create and push

Authenticate on your own computer:

```sh
gh auth login
gh auth setup-git
gh repo create "farhadtoor/voilavoila-cms" --public --source=. --remote=origin --push
git push origin v1.0.0 v1.1.0
```

The `--public` command makes source public; run it after reviewing the local result. If you prefer GitHub's web interface, create a new **empty** repository without auto-added README/license/gitignore, then use:

```sh
git remote add origin https://github.com/farhadtoor/voilavoila-cms.git
git push -u origin main
git push origin v1.0.0 v1.1.0
```

Use one creation route. Do not run `git init` again in the prepared folder. If a remote repository already has history, use a new empty repository for this import or reconcile deliberately; these instructions do not force-push over it. A terminal or GitHub Desktop is preferable to browser file upload because history/tags and dotfiles must be retained.

## 4. Configure the project before announcing it

- Set About text and topics such as `cms`, `php`, `sqlite`, `self-hosted`, `single-user`, `themes`, `plugins`.
- Enable Issues and Discussions; create Announcements, Q&A, Ideas and Show and tell categories. Add the prepared welcome text from the community guide.
- Enable private vulnerability reporting: Settings → Security and quality → Advanced Security → Private vulnerability reporting. GitHub wording can change. Confirm the private report link works; SECURITY.md alone does not enable the feature.
- Check the first Actions results. CI targets PHP 8.3/8.4/8.5, but only the recorded local PHP 8.3.6 run was completed in this kit.
- Once jobs pass, protect `main` with the corresponding required checks. Keep PR review as the normal practice; a sole maintainer cannot approve their own PR, so do not require an unavailable second reviewer. Restrict force pushes/deletion. Consider tag protection for `v*` after this initial import.
- Review Actions permissions: the workflow only needs read access. Keep private production credentials out of this source project. Inspect Dependabot PRs before merging. Workflow dependency lockfiles can be evaluated as a later hardening step; this kit currently uses a full checkout SHA pin.
- Add the suggested labels manually from the community guide. Keep Wiki/Projects disabled until you have a real need. GitHub Pages can host separate static docs, not the CMS PHP runtime.

## 5. Create both Releases as drafts

From `voilavoila-cms-git`, with the extracted kit still next to it:

```sh
gh release create v1.0.0 "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.0.0.zip" "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.0.0.sha256" --verify-tag --draft --latest=false --title "VoilaVoila CMS 1.0.0 — initial release" --notes-file docs/releases/v1.0.0.md
gh release create v1.1.0 "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.1.0.zip" "../VoilaVoila-GitHub-Kit/releases/VoilaVoila-CMS-1.1.0.sha256" --verify-tag --draft --latest --title "VoilaVoila CMS 1.1.0 — SQLite, logs, original media" --notes-file docs/releases/v1.1.0.md
```

Adjust paths if you chose another destination. Alternatively use Releases → Draft a new release, select the existing tag, paste the matching bilingual note and attach its ZIP and `.sha256`. Publish 1.0.0 as historical, then 1.1.0 as **Latest**. Do not mark 1.0.0 as latest. The supplied installable ZIPs are unchanged; automatic GitHub source archives represent the respective imported snapshot. Current community docs live on `main`.

In the downloads folder, verify one archive with `sha256sum -c VoilaVoila-CMS-1.1.0.sha256` (Linux) or `shasum -a 256 -c VoilaVoila-CMS-1.1.0.sha256` (macOS). In PowerShell, compare `Get-FileHash .\VoilaVoila-CMS-1.1.0.zip -Algorithm SHA256` with the accompanying checksum. A checksum detects mismatched bytes; it is not an author signature.

## 6. Welcome the first users

Follow the [community plan](community.md). Start with a small pilot group; request PHP version, host type and a redacted result. Use the bilingual launch drafts after setting up security and support channels. No outreach or GitHub content was posted by this kit.

## Official references checked for this guide

- [Import local code](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github)
- [GitHub CLI: repo create](https://cli.github.com/manual/gh_repo_create)
- [GitHub CLI: release create](https://cli.github.com/manual/gh_release_create)
- [Private vulnerability reporting](https://docs.github.com/en/code-security/how-tos/report-and-fix-vulnerabilities/configure-vulnerability-reporting/configure-for-a-repository)
- [Secure Actions use](https://docs.github.com/en/actions/reference/security/secure-use)
- [Repository licensing](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/licensing-a-repository)

Prepared on 25 October 2026. Recheck GitHub UI names and dependency updates when publishing later.
