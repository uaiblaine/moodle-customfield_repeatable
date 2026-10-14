## Summary

Describe the goal of this change.

## Change type

- [ ] fix
- [ ] feature
- [ ] refactor
- [ ] tests
- [ ] docs
- [ ] ci
- [ ] security

## Risk areas

- [ ] capabilities / permissions
- [ ] database schema / upgrade path
- [ ] web services
- [ ] privacy provider
- [ ] UI / templates / JS
- [ ] settings / configuration
- [ ] install / upgrade

## Validation

- [ ] `mdl ci <repo>` passes locally (or targeted `--only` runs for the touched gates)
- [ ] Relevant PHPUnit run (`mdl phpunit <stack> <component>`)
- [ ] Manual validation of the changed flow on a local stack
- [ ] `lang/en` and `lang/pt_br` in sync and alphabetically sorted
- [ ] `version.php` bumped if JS, `db/services.php` or DB schema changed
- [ ] `amd/build` rebuilt and committed with any `amd/src` change
- [ ] `CHANGELOG.md` updated

## MDL Shield

- [ ] Effective changed lines (`mdl mdlshield size <repo-dir>`): ____ (3,000 is the soft limit; split above it)
- [ ] A review is wanted: yes / no. Docs, lang, version-only, vendor sync, config, comment audit, revert and removal-only pull requests never get one.
- [ ] The owner posts `!mdlshield review full` once the local matrix is green; no session posts it unasked.

## Notes

Include any compatibility, migration or rollout considerations.
