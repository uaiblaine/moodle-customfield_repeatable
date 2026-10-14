# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [v5.3-r1] - 2026-10-14

Moodle 5.3 branch (`MOODLE_503_STABLE`): version `2026100500`, release `v5.3-r1`, `MATURITY_STABLE`,
`$plugin->supported = [503, 503]`, requires `local_repeatable` 2026100500, CI against core
`MOODLE_503_STABLE`.

First release on the one-branch-per-Moodle-version layout and the first `MATURITY_STABLE` one:
`MOODLE_405_STABLE` (`v4.5-r1`), `MOODLE_501_STABLE` (`v5.1-r1`), `MOODLE_502_STABLE` (`v5.2-r1`,
also `main`) and `MOODLE_503_STABLE` (`v5.3-r1`). Each branch numbers itself in its Moodle version's
namespace (4.5 `20241007XX`, 5.1 `20251006XX`, 5.2 `20260420XX`, 5.3 `20261005XX`), declares
`$plugin->supported` for that version only, requires `local_repeatable` at that branch's version,
and runs CI against its own core branch with the full PHP x database matrix, installing
`local_repeatable` and `local_dictionaries` from their matching branches and `customfield_pattern`
(Moodle 5.1 and later) from its one branch, `main`. `main` keeps its development number,
`2026042002`; the other branches start at counter 00. `install.xml` carries `VERSION="2024100700"`
on every branch.

### Security

- Every save path now runs the form's code checks in `instance_form_save()`: core's web services
  (`core_course_create_courses`, `core_course_update_courses`) and the group and cohort APIs save
  custom field values without `instance_form_validation()`, so a user who could edit an instance but
  not read a restricted dictionary could store one of its codes and read its label back. A value that
  fails the checks is refused with one message that names the field only. The form, web services,
  course upload and group and cohort APIs share one validator construction.
- Deleting a record's or a field's value removes its projection rows and core's data in one transaction.

### Fixed

- The dictionary name-filter options of a shared field in the Report Builder sources list only the
  codes saved in the report's own area (course, cohort, group or grouping), not those of the other
  areas the field is enabled for. Requires local_repeatable 2026042004. Version 2026042002.

### Added

- The `repeatable` custom field type (stage D1 of the repeatable-sets engine plan): column schema
  edited with the engine's column editor in the field's settings, keys holding values locked;
  sets edited with the engine's widget on the instance form, validated with the instance's stored
  payload and the editing user's dictionary rights; value stored in `customfield_data.value`.
- `customfield_repeatable_set`, one projection row per set for reports, kept in step on save,
  instance and field deletion, privacy deletion and course restore (course, group and grouping
  values), with a daily repair task.
- `export_value()`: a compact escaped summary, one line per set (course listings, core's Report
  Builder column, web services).
- Privacy metadata for the projection table and core's custom field privacy callbacks.
- Report Builder sources with one row per set (stage D2, owner decision D2): "Course repeatable sets",
  "Cohort repeatable sets" and "Group and grouping repeatable sets", one entity per repeatable field
  of the area (shared fields enabled for the area included, with that area's values only). Columns
  and filters are available as core decides for its own custom field columns
  (`handler::can_view($field, 0)` of the area); the sources mirror core's courses, cohorts and groups
  datasources (site course excluded) and are offered on PostgreSQL and MySQL/MariaDB only. Version
  2026042001.
