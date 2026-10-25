# Start and maintain a small community

[Français](../fr/communaute.md) · [Publishing checklist](publishing.md)

## A clear promise

Describe the project consistently: a self-hosted PHP/SQLite personal CMS, one owner, native themes/plugins. Start with people who need that exact tool: personal site owners, independent creators and PHP self-hosters. Show a working public profile and a short administration walkthrough using fictitious content. Do not advertise ticketing, a full WordPress replacement or audited security.

## One place for each conversation

Use GitHub Discussions for Q&A, ideas and user showcases; Issues for confirmed defects or scoped work; PRs for review. Announcements carries releases and maintenance notices. Keep private vulnerability reports and conduct reports on the private channels in SECURITY/Code of Conduct. Start with GitHub before adding another chat server to maintain.

Suggested labels (create in Issues → Labels):

| Label | Meaning |
| --- | --- |
| `bug` | Reproducible application defect |
| `enhancement` | Scoped behavior proposal |
| `documentation` | Guide or example work |
| `translation` | FR/EN consistency |
| `accessibility` | Keyboard, contrast and assistive technology |
| `good first issue` | Small task with a reproduction and acceptance criteria |
| `help wanted` | Maintainer welcomes implementation help |
| `needs reproduction` | Missing a safe minimal reproduction |
| `migration` | Version/storage transition |

## First month — proposed sequence

1. **Preparation:** complete the publishing guide, check contact/rights, obtain green CI and publish the two releases. Use 1.1.0 as current.
2. **Pilot:** invite 5–10 willing testers individually in places where project sharing is allowed. Ask for PHP version, host type, installation outcome and one concrete obstacle, without logs or account data. Keep a redacted hosting compatibility table.
3. **Launch:** post the prepared announcement once in relevant PHP/self-hosting communities, respecting their rules. Include a screenshot/video made from fake data, the repository and installation guide. Ask for installation feedback rather than only stars.
4. **Follow-up:** fix the highest-impact confirmed problems, publish a small patch when ready, thank contributors and explain roadmap choices. Keep a few well-scoped starter issues available.

[Ready-to-adapt launch text](../community/launch.en.md), [welcome post](../community/welcome.md) and [starter issue suggestions](../community/first-issues.md) are included. Nothing has been posted automatically.

## Triage and review

Confirm the affected version and use a local reproduction. Request only minimal redacted evidence. Move support questions to Discussions kindly, link duplicates and explain why out-of-scope proposals are closed. For a large request, first decide whether a native plugin can solve it. Keep security reproduction details private.

Choose a realistic time for maintenance, for example one weekly triage session; do not promise a response time you cannot maintain. Credit meaningful contributions in release notes. Use patch versions for fixes and document storage/API changes before shipping. See the [maintenance guide](maintenance.md).

Measure successful installations, resolved onboarding problems, returning users and contributions. Stars can show interest but do not establish actual usage. Publish honest validation limits and do not keep abandoned tasks labeled as easy starter work.
