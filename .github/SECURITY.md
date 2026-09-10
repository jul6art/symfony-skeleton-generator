# Security Policy

## Supported versions

This repository is a **project generator**, not a runtime dependency: nothing
here is installed into a generated project, and there is no released package to
upgrade. Only the tip of `master` is maintained, and that is the version any fix
lands on.

| Version | Supported |
| --- | --- |
| `master` | ✅ |
| any older tag or fork | ❌ |

## What is in scope

A vulnerability in this repository is a defect in what the generator *writes*
or *runs*, most usefully:

* **An insecure default in an overlay file** — a firewall or `access_control`
  rule that lets through what it should refuse, a missing CSRF token on a
  state-changing form, a serialization group that exposes a password or a token,
  an API Platform operation without a `security:` expression, permissive CORS or
  `trusted_proxies` settings.
* **A route without an access decision** that `tests/Security/RouteAccessDecisionTest.php`
  fails to catch — the rule the whole skeleton is built on, so a hole in its
  enforcement is a vulnerability in itself.
* **A flaw in `bin/new-project`** — a placeholder substitution that allows
  injection, an unsafe path expansion, a file written with permissions it should
  not have, a secret (JWT key pair, `APP_SECRET`) generated predictably or left
  world-readable.
* **A secret committed to this repository** by accident.

Out of scope: vulnerabilities in Symfony, in the `jul6art/*` bundles or in any
other third-party package — report those to the project that owns the code.
Findings in a project *you* generated and then modified are yours, unless the
generated code is what introduced them.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Use [GitHub's private vulnerability reporting](https://github.com/jul6art/symfony-skeleton-generator/security/advisories/new)
(the **Security** tab → *Report a vulnerability*). It opens a draft advisory only
you and the maintainers can read, and it is the channel this project prefers —
no email address needs to be published for it to work.

Please include:

* the affected mode(s) (`web`, `api`, `api-platform`, `admin`, `backoffice`) and
  whether `--no-user` / `--no-registration` change the outcome,
* the file and line in this repository (`common/overlay/…`, `modes/<mode>/overlay/…`,
  `bin/new-project`), not only the path in your generated project,
* the shortest reproduction you have — ideally the `./bin/new-project` command
  line, then the request or console call that demonstrates the problem,
* what an attacker gains: which decision is bypassed, which data is read or
  written.

## What to expect

* An acknowledgement within **7 days**.
* An assessment — accepted, out of scope, or needing more detail — within
  **14 days**.
* For an accepted report: a fix on `master`, a note in the commit message, and
  credit in the advisory unless you ask otherwise.

Because generated projects are copies rather than dependencies, a fix here does
not reach anything already generated. Accepted reports that affect code already
shipped into projects are published as a [security advisory](https://github.com/jul6art/symfony-skeleton-generator/security/advisories)
describing the patch to apply by hand.

Please give the maintainers a reasonable window to ship a fix before disclosing
publicly. This project runs no bug-bounty programme and offers no payment.
