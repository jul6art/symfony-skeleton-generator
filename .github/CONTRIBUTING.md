# Contributing

Thanks for taking the time. This repository is a **Symfony project generator**,
which changes what "contributing" means here: there is almost no application
code to fix. What you are editing is the material five modes are made of —
package lists, overlay files, hooks and one Bash script — and the only real proof
that an edit is correct is a project generated from it whose `make qa` is green.

Read [README.md](../README.md) first; it documents the structure, the
placeholders and how a mode is put together. This file covers the workflow.

## Before you open anything

* **Bug or unclear behaviour** → open an
  [issue](https://github.com/jul6art/symfony-skeleton-generator/issues/new/choose)
  with the `./bin/new-project` command line you ran. The generator is
  deterministic, so a command line plus its output is usually the whole report.
* **A new mode, or a new package in an existing mode** → open an issue first.
  Every package added to `common/packages.txt` lands in five projects at once,
  and every mode is a maintenance commitment; that discussion is cheaper before
  the code than after.
* **A security problem** → do not open an issue. Follow
  [SECURITY.md](SECURITY.md).

## Ground rules that are not negotiable

These are the ones a pull request gets refused over:

1. **No route without a voter action.** Each route carries an explicit access
   decision, and that decision lives in a voter — including the deliberately
   public ones (`#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]`). Silence does
   not authorise, it forgets to refuse. `tests/Security/RouteAccessDecisionTest.php`
   ships in every mode and turns `make qa` red when a route is added without one.
   Read the rule in full at the top of the README.
2. **Roles are the matter of a decision, never its expression.** `ROLE_ADMIN` is
   read *inside* a voter; `access_control` and class-level `#[IsGranted]` stay
   the coarse belt.
3. **Reuse the in-house bundles.** `jul6art/core-bundle` and
   `jul6art/auth-bundle` already carry `AbstractRepository`, `AbstractManager`,
   `IdTrait`, `FactoryInterface`, the listener base classes, the `*AwareTrait`
   setters, and the `User` entity with its repository, manager and factory. A
   generated project never declares its own `App\Entity\User` (the `backoffice`
   mode is the documented exception, because its permission engine needs an
   entity it can extend). If you find yourself writing one of those pieces
   again, the fix belongs in the bundle.
4. **Nothing loads from a CDN at runtime.** Tailwind and Font Awesome are
   downloaded at install time into the project; no remote `src` in a layout.
5. **Only `backoffice` may need Node.** Every other mode stays Node-free —
   standalone Tailwind and Dart Sass binaries, no `npm` in the generated
   project.
6. **Templates and form labels carry keys, not strings.** Interface strings live
   in `translations/messages.*`, validation messages in `translations/validators.*`,
   and both English and French ship complete.
7. **The account brick stays a brick.** Anything to do with sign in, sign up,
   forgotten password, profile or user CRUD belongs in `overlay-user/`,
   `packages-user.txt`, `post-install-user.sh`, `gitignore-user.append` and
   `CLAUDE-user.append.md` — never in `overlay/`. `--no-user` must keep working,
   and `make qa` must stay green without the brick.

## Working on a change

```bash
git clone https://github.com/jul6art/symfony-skeleton-generator.git
cd symfony-skeleton-generator
git checkout -b fix/short-description
```

Requirements on your machine: PHP ≥ 8.5, the [Symfony CLI](https://symfony.com/download)
(the generator calls `symfony new` and `symfony composer`, since Composer is not
assumed to be global), Git, and — only if you touch the `backoffice` mode —
Node and npm.

**See what your edit does before running it:**

```bash
./bin/new-project test --web --dry-run   # prints everything, changes nothing
```

## Validating a change

There is no CI on this repository. Validation is local, and it is the same loop
for everyone: **generate, then run the project's own quality gate.**

```bash
./bin/new-project /tmp/skel-web --web
cd /tmp/skel-web && make install && make qa
```

`make qa` is php-cs-fixer + PHPStan level 8 + PHPUnit, and it is green on a
freshly generated project. If your change makes it red, the change is not done.

Which modes to regenerate depends on where you edited:

| You touched | Regenerate |
| --- | --- |
| `common/` | **all five** modes — `web`, `api`, `api-platform`, `admin`, `backoffice` |
| `modes/<mode>/` | that mode |
| `overlay-user/` or `packages-user.txt` | that mode, **and** the same mode with `--no-user` |
| `bin/new-project` | all five, plus one `--dry-run` and one `--no-extras` |

For anything with a user interface, also open the generated project
(`symfony serve`) and look at the screen: sign in, sign up, forgotten password,
profile, the CRUD, and both light and dark schemes. Several of the fixes in this
repository's history exist because a mode was only ever read, not run.

## Pull requests

* One subject per pull request. A mode addition and a fix to `common/` are two
  pull requests.
* Fill in the [template](pull_request_template.md): which modes you regenerated,
  and the `make qa` result for each. A pull request that does not say which
  modes were generated cannot be reviewed.
* Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/)
  with the mode as an optional scope — `fix(backoffice): …`, `feat: …`,
  `docs: …`, `chore: …`, and `feat!:` for a change that alters what existing
  modes generate. The subject says what changed, in the imperative. This
  repository's history is written in French; French and English are both fine,
  and the code, comments and documentation stay in English.
* Update the README in the same pull request when you add a mode, a placeholder
  or an option — the README is the reference for all three, and a mode that is
  not documented there is not finished.
* Rebase on `master` rather than merging it back in.

## Adding a mode

A mode is a data directory: there is no generator code to touch, and
`--list` picks up a new one on its own. The README's *Adding a mode* section has
the file-by-file table and a worked `console` example. Two things it is worth
repeating:

* A mode whose subject *is* accounts declares `REQUIRES_USER=1` in its
  `mode.conf` so `--no-user` is refused (that is what `admin` does).
* A mode that needs accounts copies the existing brick rather than rewriting it.
  That is exactly how `admin` was built: same front, same account brick,
  EasyAdmin in place of the hand-written CRUD.

## Code of conduct

Participation is covered by our [Code of Conduct](CODE_OF_CONDUCT.md).

## License

Contributions are accepted under the [MIT license](../LICENSE) that covers this
repository.
