# Claude instructions for `customfield_repeatable`

Auto-loaded whenever Claude works in this plugin's tree. **Fleet-wide standards live in
`~/dev/CLAUDE.md`** (coding style, CI gates, lang strings, the `mdl` environment, git) — do not
repeat them here. This file keeps only what is true for this plugin, as rules with a one-line reason
each; the long version of a gotcha goes to `docs/` (export-ignored) with a pointer from the rule.

Plugin context: a Moodle **custom field type** (`core_customfield`, type `repeatable`, "Repeatable
sets") that stores repeatable sets of typed columns per instance (a course first; any area core
offers custom fields in). The canonical value is a JSON payload in core's `customfield_data.value`;
the plugin's own table `customfield_repeatable_set` holds one row per set as a projection for Report
Builder. **Hard dependency on `local_repeatable`** (`~/dev/moodle-local_repeatable`), the
repeatable-sets engine: schema, payloads, validation, the companion bridges, widget, column editor,
renderer (templates, CSS, AMD) and the projection writer live there; this plugin is its custom field
adapter, the second after `profilefield_repeatable`. The plan is
`moodle-profilefield_repeatable/docs/engine-extraction-design.md` §4 and §6 stage D (D1 = this type;
D2 = Report Builder datasources for course, cohort, group/grouping). Development on `main` = Moodle
5.2 (`$plugin->supported = [502, 502]`, MATURITY_ALPHA); at release it splits into one
`MOODLE_<NNN>_STABLE` branch per Moodle version (owner decision D6: 4.5 through 5.3), each with its
own `version.php` and `ci.yml` job. Mounted at `customfield/field/repeatable` on the stacks
`$plugin->supported` names (`~/dev/moodle-dev/plugins.conf`, `auto`).

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-customfield_repeatable --branch MOODLE_502_STABLE --php 8.4 --only phpcs,phpdoc   # quick gate
mdl ci moodle-customfield_repeatable --matrix --behat    # every CI leg locally: the pre-merge gate
mdl phpunit m502 customfield_repeatable                  # targeted tests
mdl behat m502 @customfield_repeatable                   # Behat smoke test
# Mutation sweep: absolute spec path, and BOTH database families.
mdl mutate moodle-customfield_repeatable /Users/uaiblaine/dev/moodle-customfield_repeatable/mutations/gates.conf --fast --db pgsql
mdl mutate moodle-customfield_repeatable /Users/uaiblaine/dev/moodle-customfield_repeatable/mutations/gates.conf --fast --db mariadb
xmllint --noout --schema ~/dev/moodle-502/public/lib/xmldb/xmldb.xsd db/install.xml
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs `mdl ci moodle-customfield_repeatable --matrix --behat` before merging:
push a branch, open the pull request, leave the merge to them. If `which mpci` prints nothing, the
session was resumed on a container that skipped the setup script: attach `moodle-dev` and run
`bash cloud/setup.sh` from it. See `moodle-dev/cloud/README.md`.

`mutations/gates.conf` breaks one guard per gate and names the test that must redden. Gates marked
`# needs local_dictionaries` are held by tests that skip without it.

## Code layout

```
classes/field_controller.php       schema textarea (engine column editor), locked keys from STORED rows, canonical save, delete
classes/data_controller.php        widget + hidden input, validation with the STORED payload, save + projection, export_value, restore
classes/form/schema_textarea.php   textarea that requires local_repeatable/columneditor when rendered
classes/form/widget_html.php       widget HTML that requires local_repeatable/repeatable when rendered
classes/local/field_schema.php     configdata['schema'] (a JSON string) -> engine schema; stored() reads {customfield_field}
classes/local/stored_values.php    usage()/locked_labels() over the field's {customfield_data} payloads
classes/local/source.php           implements the engine's projection\source: {customfield_data}, owner instanceid
classes/local/summary.php          export_value(): one escaped line per set (owner decision D4)
classes/local/projection.php       reproject_course() (course + groups + groupings), purge_orphans()
classes/observer.php               course_restored -> reproject_course()
classes/task/reproject_sets.php    scheduled, daily: rebuild stale projections, purge orphans
classes/privacy/provider.php       metadata (projection table) + core_customfield's customfield_provider
classes/reportbuilder/datasource/  course_sets, cohort_sets, group_sets (group + grouping): one row per set
classes/reportbuilder/local/entities/repeatable_set.php  engine `sets` subclass; is_available() = area handler's can_view($field, 0)
classes/reportbuilder/local/helpers/area_sets.php        an area's repeatable fields, their entities and joins, the DB allowlist
tests/classes/sets_report_testcase.php                  shared fixtures of the datasource tests
```

No `amd/`, `templates/` or `styles.css`: they are `local_repeatable`'s.

### Engine and adapter (local_repeatable)

- The engine decides every rule; this adapter hands it the facts only `core_customfield` knows: the
  stored payload of THIS field and instance (`data_controller::stored_payload()`), the editing user's
  `can_read_domain()` (`\local_dictionaries\api::can_read()` behind `companion_available()`), the
  keys holding values in the STORED field (`stored_values`), the seams `companion_available()` and
  `pattern_available()`, the projection table and owner column (`local\source`).
- Strings an engine class prints come from `local_repeatable`; this plugin keeps only its own
  (`pluginname`, `columns`(+`_help`), `specificsettings`, `summaryline`, the task, privacy).

## Versions

Each branch numbers itself in its Moodle version's namespace (4.5 `20241007XX`, 5.1 `20251006XX`,
5.2 `20260420XX`, 5.3 `20261005XX`; fleet rule), counter per branch. `install.xml` keeps
`VERSION="2024100700"` on every branch.

## Architecture gotchas

### core_customfield facts the type depends on

- **`customfield_data.value` is the single source of truth.** Every read decodes it; a restore
  writes it without the type (`course_handler::restore_instance_data_from_backup()`).
- Core deletes `customfield_data` itself in `field_controller::delete()` without calling
  `data_controller::delete()`: `field_controller::delete()` removes the field's projection rows
  first. `handler::delete_instance()` (course, cohort, question deletion) calls
  `data_controller::delete()`, which removes the record's rows. Group deletion leaves its
  `customfield_data` (core), so its rows stay a faithful projection of what is there.
- Core calls `restore_define_structure()` only for COURSE fields the restorer may see
  (`handler::restore_define_structure()` reads visible fields; `NOTVISIBLE` hides from everyone)
  and never for groups or groupings: the `course_restored` observer rebuilds the course's, groups'
  and groupings' values. `reproject_course()` matches the area on `customfield_data.component/area`,
  which exist from Moodle 5.0: **the 4.5 branch must join `customfield_category` instead.**
- **The configuration form is a modal `dynamic_form`**: core defines it, then renders it inside
  `start_collecting_javascript_requirements()`, so a `js_call_amd()` made while defining is lost.
  Both modules are required in the elements' render methods (`classes/form/`); never move them to
  `config_form_definition()` or `instance_form_definition()` (gates CF15, CF16).
- `export_value()` is printed unescaped by core (course listings via `core_customfield/field_data`,
  its Report Builder column, web services `value`): it is `local\summary::html()`, every value
  through `s()`, never `format_text()` over the JSON. Web services also return the raw JSON as
  `valueraw` (core).
- `get_default_value()` is null, or core's Report Builder filter wraps the column in
  `COALESCE(value, default)`. An empty payload is stored as `''`, so core's "is empty" sees no value.
- The uniqueness setting (core, every type) compares the stored text: validation hands the parent
  the canonical JSON as it would be stored, or `''`.
- The course upload tool builds a data controller with a made-up id (`id` = 1): `stored_payload()`
  reads `customfield_data` by id AND field id AND instance id, never by id alone (gate CF2).

### Validation and dictionaries

- Code checks run in one private method (`submitted_error()` → the engine's `local\validator`, built
  with the STORED payload and the current user's `can_read_domain()`). The form calls it from
  `instance_form_validation()`; **every other save path calls it again from `instance_form_save()`**
  and throws `errorsaverejected` (the field name only, never a code, label or domain): web services
  (`core_course_*_courses`), the course upload tool, group and cohort library functions, because
  core skips `instance_form_validation()` there (gates CF29, CF30). The one path that writes without
  the type is core's restore: it copies a value a user could already see on the source, and a crafted
  backup is restore-capability-gated (residual, by design).
- **No oracle on restricted dictionaries**: every path that turns a SUBMITTED code into an answer is
  decided on `can_read_domain()` of the editing user, with the stored codes of THIS instance as the
  only exemption (engine rules B11-B18).

### Projection

- `instance_form_save()` writes the value through `parent::instance_form_save()` and the engine's
  `projector::sync()` in one delegated transaction, as must any new writer.
- The projection carries `dataid`, `fieldid` and `instanceid`: a shared field (5.1+) can hold data in
  several areas under one field id, so a reader that needs the area joins `customfield_data` on
  `dataid`. The datasources join `s.instanceid = <instance>.id AND s.fieldid = <id>` AND an `EXISTS`
  on `customfield_data` `component`/`area` (gates CF33-CF37). Entity joins take no parameters, so the
  names are refused unless they are plain identifiers (CF48). The 4.5 branch has no such columns on
  `customfield_data` and must check the area through the field's category instead.

### Report Builder (stage D2)

- **Availability mirrors core, never a looser rule**: `repeatable_set::is_available()` is the AREA
  handler's `can_view($field, 0)`, as `core_reportbuilder\local\helpers\custom_fields` sets on its
  columns and filters; never `$field->get_handler()`, which for a shared field is core_customfield's
  and shows it to everyone (gate CF32). Every availability test compares with core's own column
  `<entity>:customfield_<shortname>` of the same field.
- Fields are listed as core lists them: the area handler's `get_categories_with_fields()`, enabled
  shared categories included. Entity names are saved-report identifiers: course and cohort
  `repeatable_<shortname>`, group `repeatable_group_<shortname>`, grouping
  `repeatable_grouping_<shortname>`; column names are the engine's. Each datasource test pins them.
- PostgreSQL and MySQL/MariaDB only (`area_sets::db_supported()`), as the profile field.
- The dictionary name-filter options of a shared field are scoped to the area: `repeatable_set`
  overrides the engine's `filter_options_scope()` with an `EXISTS` over `{customfield_data}` (alias
  `fod`) on the handler's component and area, bound as parameters.
- A field whose schema is unreadable keeps its rows: rewriters use `field_schema::parse()` (null →
  skip), never `from_configdata()` (empty schema → every set "vanished").

## Testing notes

- Fields come from the plugin's generator (`create_field($columns, $record)`), which goes through
  core's `core_customfield` generator and builds the schema with the engine's `schema_json()`. Values
  are saved through `course_handler::instance_form_save()` or `create_course()`, as core saves them.
- Dictionary tests skip without `local_dictionaries`; the restricted-domain tests run as an
  `editingteacher`, who has `moodle/course:update` but not `local/dictionaries:view`.
- `tests/privacy/provider_test.php` asserts `component_is_compliant()`: plugin CI never runs core's
  compliance sweep.
- `.mdlshield/config.yml` pins `versions: ["5.2"]`.

## When in doubt

Follow the patterns in existing files and in `profilefield_repeatable`, the first adapter; if a new
file matches no existing shape, re-examine the approach.
