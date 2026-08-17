<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v1&color=orange" alt="Version">
</p>

jul6art/symfony-skeleton-generator
==========================
Symfony skeleton generator
-----------------------------

# In-house Symfony skeleton

A Symfony project generator with three modes to choose from:

| Mode | Base | Contents |
| --- | --- | --- |
| `web` | `symfony new --webapp` | Twig + local Tailwind & Font Awesome, full account flow (register / login / forgot password / change password), user CRUD |
| `api` | `symfony new` (bare skeleton) | Doctrine, Serializer, Validator, JWT authentication, CORS |
| `api-platform` | `symfony new --api` | API Platform (REST + JSON-LD/Hydra, OpenAPI docs, JWT authentication, optional GraphQL) |

This repository **is not a versioned Symfony project**: `symfony new` is what
creates the project (so Flex recipes always stay up to date), and the skeleton
then applies its own packages and files on top.

## What each mode ships

### `web` — web application

- **Tailwind** through `symfonycasts/tailwind-bundle`: a standalone binary, no
  Node build. **Font Awesome Free** is downloaded into `assets/fontawesome/` by
  `make fontawesome` (run by `make install`). Nothing is loaded from a CDN at
  runtime — no remote `src` anywhere in the layout.
- Ready-made account flow: `/register`, `/login` (CSRF, remember me, throttling),
  `/reset-password` (`symfonycasts/reset-password-bundle`, e-mail + one-shot
  token), `/profile/password`. Logout is a POST form with a CSRF token, and
  every sensitive action (delete, logout) asks for confirmation in a modal.
- Public sign-up is a switch: `APP_REGISTRATION_ENABLED=0` in `.env` makes
  `/register` answer 404 and hides the "create an account" links. Generate the
  project already closed with `--no-registration`.
- User CRUD under `/admin/users`, restricted to `ROLE_ADMIN`.
- A `User` entity, a Tailwind form theme, flash messages and a responsive
  layout, all ready to build on.
- First administrator: `make user-create ARGS="me@example.com --admin"`.

### `api` — JSON API

- **JWT authentication** (`lexik/jwt-authentication-bundle`): `POST /api/login`
  returns a token, every other `/api` route expects
  `Authorization: Bearer <token>`. The key pair is generated at install time
  (`make jwt-keypair` to regenerate).
- **CORS** through `nelmio/cors-bundle`, driven by `CORS_ALLOW_ORIGIN`.
- `GET /health` as a database-free smoke test, `GET /api/me` for the account
  behind the token, and accounts created from the console (no public sign-up by
  default).
- `request/test.http` drives every shipped route from the IDE (PhpStorm / VS
  Code HTTP client): the login request stores the token for the following ones,
  and the 401 cases are part of the file. Host and e-mail live in
  `request/http-client.env.json`, the password in the gitignored
  `request/http-client.private.env.json`.

### `api-platform` — declarative API

`symfony new --api` plus house rules: pagination bounds, JSON-LD + JSON formats,
`stateless` operations, and make targets for the OpenAPI export and the
interactive docs.

- Same **JWT authentication** as the `api` mode: `POST /api/login` returns a
  token, `/api` expects `Authorization: Bearer <token>`, key pair generated at
  install time. The login endpoint is documented in the OpenAPI spec and
  Swagger UI gets its "Authorize" button.
- The `User` entity is exposed as a resource: `/api/users` for `ROLE_ADMIN`
  only, an account reads its own record, and `/api/me` returns the account
  behind the token through a state provider. The password belongs to no
  serialization group, so it cannot leak.

Every mode also ships a smoke test — public pages and probes answer, protected
areas stay closed — so `make qa` (php-cs-fixer, PHPStan level 8, PHPUnit) is
green on a freshly generated project.

## Usage

```bash
./bin/new-project my-site --web
./bin/new-project my-api  --api
./bin/new-project my-shop --api-platform
./bin/new-project ~/www/foo --web --version=7.4.* --docker
./bin/new-project test --api --dry-run     # prints everything, changes nothing
./bin/new-project my-project               # asks for the mode interactively
```

Options: `--mode=<name>`, `--version=`, `--php=`, `--docker`, `--no-extras`
(bare skeleton, without the skeleton's packages), `--no-registration` (closes
public sign-up — honored by the modes that expose one, i.e. `web`), `--no-git`,
`--dry-run`, `--list`, `--help`.

To have it at hand everywhere, add this to `~/.zshrc`:

```bash
sfnew() { /var/www/PRIVATE/symfony-skeleton/bin/new-project "$@"; }
```

## What the generator does

1. `symfony new <dir> --version=… --no-git [--webapp|--api] [--docker]`
2. writes `.php-version` (detected PHP version, or `--php=`)
3. `symfony composer require` of `common/packages.txt` +
   `modes/<mode>/packages.txt` (same with `--dev` for the `packages-dev.txt`
   files)
4. copies `common/overlay/` then `modes/<mode>/overlay/` into the project
   (mode files override common ones, and both override the files written by
   Flex recipes — that is intentional: these are your house rules)
5. appends `common/gitignore.append` and `modes/<mode>/gitignore.append` to the
   `.gitignore` generated by Flex (without touching the `###> … ###` blocks
   Flex manages), and `modes/<mode>/CLAUDE.append.md` to `CLAUDE.md`
6. replaces the placeholders in the copied files
7. runs the `post-install.sh` hooks if they exist
8. `git init` + initial commit

Since `composer` is not installed globally on this machine, everything goes
through `symfony composer`. If the `php` on the `PATH` is broken, the script
automatically looks for the most recent PHP ≥ 8.2 in the Homebrew directories.

## Placeholders

Usable in any overlay file:

`{{PROJECT_NAME}}` `{{PROJECT_SLUG}}` `{{PROJECT_SNAKE}}` `{{PROJECT_TITLE}}`
`{{MODE}}` `{{SYMFONY_VERSION}}` `{{PHP_VERSION}}` `{{DATE}}` `{{YEAR}}`

## Structure

```
common/
  packages.txt            # composer require, every mode
  packages-dev.txt        # composer require --dev, every mode
  gitignore.append        # macOS, IDE, node… appended to the Flex .gitignore
  overlay/                # files copied into every project
  post-install.sh         # (optional) post-generation hook
modes/
  web/
    mode.conf             # DESCRIPTION + SYMFONY_NEW_FLAGS
    packages.txt          # + packages-dev.txt, gitignore.append
    CLAUDE.append.md
    post-install.sh       # (optional) hook for this mode
    overlay/
  api/
    …
  api-platform/
    …
bin/new-project
```

Both `post-install.sh` hooks run with `PROJECT_DIR`, `PROJECT_NAME`, `MODE`,
`MODE_DIR`, `SKELETON_DIR` and `PHP_VERSION` in the environment. The `web` hook
fetches Font Awesome and builds Tailwind; the `api` hook generates the JWT key
pair. Both degrade gracefully when there is no network — the matching `make`
target can be re-run later.

## Adding a mode

A mode is just a data directory — there is no code to touch. Every file is
optional except `mode.conf`:

| File | Role |
| --- | --- |
| `mode.conf` | `DESCRIPTION` (shown by `--list`), `SYMFONY_NEW_FLAGS`, optional `NEXT_STEPS` printed once the project is generated |
| `packages.txt` | `composer require`, on top of `common/packages.txt` |
| `packages-dev.txt` | `composer require --dev` |
| `gitignore.append` | appended to the `.gitignore` written by Flex |
| `CLAUDE.append.md` | appended to the project's `CLAUDE.md` |
| `overlay/` | files copied into the project (placeholders included) |
| `post-install.sh` | hook run at the end of the generation |

Example — a `console` mode for a CLI-only project (no HTTP, no Doctrine):

```bash
mkdir -p modes/console/overlay/.make
cat > modes/console/mode.conf <<'EOF'
DESCRIPTION="Console application (bare skeleton + Console component)"
SYMFONY_NEW_FLAGS=""
EOF
printf 'symfony/console\n' > modes/console/packages.txt
cat > modes/console/overlay/.make/console.mk <<'EOF'
.PHONY: list-commands

list-commands: ## Lists the application commands
	$(CONSOLE) list
EOF
```

`./bin/new-project foo --console` works right away, and `--list` picks the mode
up on its own.

Same idea for a `microservice` mode (bare skeleton + Messenger + HTTP client)
or an `admin` mode (`--webapp` + EasyAdmin).

License
-------

The Symfony Skeleton Generator is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
