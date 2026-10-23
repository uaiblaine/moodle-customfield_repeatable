moodle-customfield_repeatable
=============================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-customfield_repeatable/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-customfield_repeatable/actions/workflows/ci.yml?query=branch%3Amain)

A custom field type holding repeatable sets of columns: several rows of the same typed columns on a
course, a cohort, a group or any other instance that has custom fields.

A course can have several contacts, several accreditations, several languages of instruction: each
is a set, and each set has the same columns. An administrator defines the columns once, in the
field's settings; teachers then add, edit and remove sets on the course settings form. A column can
be free text, a code from a dictionary of the companion plugin `local_dictionaries` (codes only, or
any code with suggestions), or a masked value checked by `customfield_pattern`.

The value is stored by core in `customfield_data` as one JSON object; the plugin keeps a copy of
each set in its own table, `customfield_repeatable_set`, for reports. The engine behind the type,
`local_repeatable`, is shared with the user profile field `profilefield_repeatable`.


Requirements
------------

- Moodle 5.1 (branch `MOODLE_501_STABLE`; releases `v5.1-rN`), PHP 8.2 or later. Other Moodle
  versions have their own branches.
- PostgreSQL 15+, MySQL 8.4+ or MariaDB 10.11+, as Moodle 5.1 requires. The Report Builder sources
  are offered on PostgreSQL and MySQL/MariaDB only.
- `local_repeatable` 2025100600 or later, from its `MOODLE_501_STABLE` branch (required: the
  repeatable-sets engine).
- Optional: `local_dictionaries` (dictionary columns, with code-to-label resolution and restricted
  dictionaries) and `customfield_pattern` (masked columns). Without them those columns are plain text.


Installation
------------

Install `local_repeatable` first, then this plugin to folder `/customfield/field/repeatable`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

The type is offered in every custom field area once installed and enabled (Site administration >
Plugins > Custom fields).


Usage
-----

### Defining the columns

In the custom field settings of an area (for courses: Site administration > Courses > Default
settings > Course custom fields), add a field of type "Repeatable sets". The column editor lists the
columns: a name, a key (generated from the name, then fixed), how users fill it, and which column is
the set's title and which up to three appear beside it. A column whose key already holds values can
be renamed but neither removed nor re-keyed. Without JavaScript the columns are edited as a JSON
document.

### Filling the sets

On the instance form (for courses, the course settings) the field is a table of sets with an
"Add new set" button. A dictionary column searches its dictionary when the user may read it; in a
restricted dictionary the user keeps the codes already stored and cannot submit others.

### Where the values show

- Course listings, core's Report Builder custom field column and web services show a compact
  summary: one line per set, the title value then the other summary values in brackets.
- Report Builder custom reports with one row per set, from three report sources:
  - **Course repeatable sets**: courses (site course excluded) with their category, and one entity
    per repeatable course field.
  - **Cohort repeatable sets**: cohorts, and one entity per repeatable cohort field.
  - **Group and grouping repeatable sets**: courses (site course excluded), their groups and each
    group's groupings, and one entity per repeatable group field and per repeatable grouping field.

  Each field's entity has a "Set number" column and one column (two for a dictionary column: its
  label and its code) per column of the field, each with a filter. A field's columns and filters
  are available exactly when core shows its own custom field column to the viewer. A source is
  offered on PostgreSQL and MySQL/MariaDB only, once its area has a repeatable field. A report
  should use one repeatable field: two fields in one report multiply each other's sets.
- Not yet one row per set (follow-ups): content bank, question, report custom fields, and the shared
  custom field area as a report source of its own. Shared fields enabled for the course, cohort,
  group or grouping area appear in that area's source, each with that area's values only.


Settings
--------

The plugin has no site settings. Each field's columns are its configuration, set in the field's
settings and stored by core in `customfield_field.configdata`; who sees and edits the field follows
core's per-field settings (visibility, locked) for the area.


Capabilities
------------

The plugin declares none: who may see and edit a value is decided by the custom field area (for
courses, the field's visibility and locked settings and the course capabilities).


Data stored
-----------

- **The value** of each field on each instance is stored by core in `customfield_data`, as one JSON
  object (the sets and their column values), and handled by core's custom field privacy API like any
  other custom field type: the component of each area (courses, cohorts, groups...) finds, exports
  and deletes it. Core keeps it until the instance or the field is deleted.
- **`customfield_repeatable_set`**, the plugin's only table, holds a copy of each set for Report
  Builder: the `customfield_data` record, the field and the instance it belongs to, the set number,
  the set's values as JSON, and when the row was created and last changed. It holds no user id. The
  rows are rewritten on every save of the value and after a course restore, removed with the value
  when core deletes the instance's custom field data (course or cohort deletion) and with the field
  when the field is deleted, and the daily task "Rebuild repeatable custom field sets for reports"
  rebuilds stale rows and removes rows whose value or field is gone. Group deletion leaves the
  group's `customfield_data` in place (core), and the copy stays with it.

The plugin stores nothing else: no setting, user preference, file or cache of its own.

Privacy API: the provider declares `customfield_repeatable_set` and plugs into core's custom field
privacy API, as every core custom field type does. When core exports a value, the export carries
every set as plain text (each column name with its value) instead of the one-line summary; when
core deletes values or fields, their copies in `customfield_repeatable_set` are deleted first.


Troubleshooting
---------------

- **The column editor or the sets table does not appear**: JavaScript is off or failed; the schema
  textarea and the hidden value still work as text. Check that `local_repeatable` is installed and
  its version matches the dependency.
- **A report lists sets that were changed or a restored course shows no sets in reports**: the
  projection is rebuilt after every save, after a course restore and by the daily task "Rebuild
  repeatable custom field sets for reports"; run that task to repair other direct writes.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation is maintained in this
repository (`lang/pt_br`) and reaches sites through [AMOS](https://lang.moodle.org/), as the plugins
directory requires; it is left out of the release package.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
