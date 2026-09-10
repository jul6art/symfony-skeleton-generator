## What this changes

<!-- One subject per pull request. Say what the generated projects will do
     differently, not only which files you edited. -->

Closes #

## Where

- [ ] `common/` — **lands in all five modes**
- [ ] `modes/web/`
- [ ] `modes/api/`
- [ ] `modes/api-platform/`
- [ ] `modes/admin/`
- [ ] `modes/backoffice/`
- [ ] `bin/new-project`
- [ ] documentation only (`README.md`, `.github/`, `CLAUDE.append.md`)

## Validation

There is no CI here: a change is proven by a generated project whose quality
gate is green. Fill in what you actually ran — a pull request that does not say
which modes were generated cannot be reviewed.

| Mode generated | `make install` | `make qa` |
| --- | --- | --- |
| <!-- web --> | | |
| <!-- api --> | | |

Regenerate all five modes when you touched `common/` or `bin/new-project`; the
mode you touched otherwise; and the same mode a second time with `--no-user`
when you touched the account brick.

```
# the exact command lines you ran
```

<details>
<summary><code>make qa</code> output</summary>

```
```

</details>

- [ ] `make qa` is green (php-cs-fixer, PHPStan level 8, PHPUnit) on every mode
      listed above
- [ ] `./bin/new-project test --<mode> --dry-run` still prints a coherent plan
- [ ] I opened the generated project in a browser and looked at the screens I
      changed, in both light and dark schemes *(user-interface changes only)*

## House rules

<!-- Tick what applies; the first one is not negotiable. -->

- [ ] Every route I added or touched carries an explicit access decision, and
      that decision lives in a voter — public ones included
- [ ] Roles are read inside voters, not used as the expression of a decision
- [ ] I reused `jul6art/core-bundle` / `jul6art/auth-bundle` rather than
      rewriting a repository, manager, factory or the `User` entity
- [ ] Nothing is loaded from a CDN at runtime
- [ ] No Node outside the `backoffice` mode
- [ ] New interface strings are translation keys, translated in both `en` and
      `fr` (`translations/messages.*`, `translations/validators.*`)
- [ ] Account-related files stay in the `--no-user` brick, and `--no-user` still
      generates a project whose `make qa` is green
- [ ] The README is updated in this pull request *(new mode, option or
      placeholder)*

## Notes for the reviewer

<!-- Trade-offs, anything you left out on purpose, follow-up work. -->
