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

- Moodle 5.2 (this branch; `$plugin->supported = [502, 502]`).
- `local_repeatable` 2026042003 or later (required: the repeatable-sets engine).
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
- Report Builder one-row-per-set reports (course, cohort, group and grouping sources) are planned.


Capabilities
------------

The plugin declares none: who may see and edit a value is decided by the custom field area (for
courses, the field's visibility and locked settings and the course capabilities).


Privacy
-------

The value is stored by core in `customfield_data` and handled by core's custom field privacy API.
The plugin's table `customfield_repeatable_set` holds a copy of each set (no user id); it is declared
in the privacy metadata, the export writes the sets as plain text, and core's deletions delete the
copies too.


Troubleshooting
---------------

- **The column editor or the sets table does not appear**: JavaScript is off or failed; the schema
  textarea and the hidden value still work as text. Check that `local_repeatable` is installed and
  its version matches the dependency.
- **A report lists sets that were changed or a restored course shows no sets in reports**: the
  projection is rebuilt after every save, after a course restore and by the daily task "Rebuild
  repeatable custom field sets for reports"; run that task to repair other direct writes.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
