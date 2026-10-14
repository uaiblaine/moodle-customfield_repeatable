# Review context for customfield_repeatable

`customfield_repeatable` is a Moodle custom field type (`core_customfield`, type `repeatable`) that
stores repeatable sets of columns for an instance (a course first; any area core offers custom
fields in), for example several contacts with the same columns. The canonical value is a JSON object
in core's `customfield_data.value`; the plugin's own table `customfield_repeatable_set` holds one row
per set as a projection for Report Builder (a JSON text column, `dataid`, `fieldid`, `instanceid`,
`set_id`, no user id). The type is an adapter of the required engine plugin `local_repeatable`, which
holds the column schema, payload normalisation, validation, the edit widget, the column editor, the
display renderer with its templates, stylesheet and AMD modules, the projection writer and the
bridges to two optional plugins: `local_dictionaries` (code-to-label dictionaries, some restricted)
and `customfield_pattern` (masks and checks). It is the second adapter of the engine, after
`profilefield_repeatable`. Development is on `main` = Moodle 5.2.

## Who is trusted

- Users who may configure custom fields (`handler::can_configure()`, for courses
  `moodle/course:configurecustomfields`) define the column schema (a JSON string in
  `configdata['schema']`) through core's field configuration modal.
- The plugin declares **no capabilities, web services or page scripts**. Who may see or edit a
  value is core's handler: `can_view()`/`can_edit()` (for courses the field's visibility and locked
  settings, `moodle/course:update`, `moodle/course:changelockedcustomfields`). Core adds only
  editable fields to the instance form and validates only those.
- Anyone who may edit the instance (a course's editing teachers) is untrusted input for every value.
  Values are markup-bearing text.
- Dictionary domains can be restricted: an editor who cannot read a domain must learn nothing about
  its codes from the form.

## Surfaces

- `classes/data_controller.php`: `instance_form_validation()` builds the engine's validator with the
  payload STORED for this field and instance (`stored_payload()`, read from `customfield_data` by the
  controller's id AND its field id AND instance id, never the submission) and a closure deciding
  `\local_dictionaries\api::can_read()` for the editing user. `instance_form_save()` runs the same checks as the form (one shared private method), normalises the
  payload, writes the value through core's parent method and rewrites the projection rows in one
  delegated transaction. `export_value()` returns `local\summary::html()`: one line per set, every
  value through `s()`, never `format_text()`, because core prints it unescaped (course listings,
  core's Report Builder column, web services). `delete()` and `restore_define_structure()` keep the
  projection in step.
- `classes/field_controller.php`: the configuration form's schema textarea (the engine's column
  editor), `config_form_validation()` with the locked keys read from this field's stored
  `customfield_data` rows (`$this->get('id')`, the id core loaded the controller with, never
  `$data['id']`), `save()` canonicalising the schema, `delete()` removing the field's projection rows
  because core deletes `customfield_data` directly there.
- `classes/form/`: two form elements that require their AMD module when rendered, because the
  configuration form is a dynamic form rendered inside `start_collecting_javascript_requirements()`.
- `classes/local/projection.php` and `classes/observer.php`: after `course_restored`, rebuild the
  restored course's, groups' and groupings' values (core writes them directly and calls the type back
  only for course fields the restorer may see); `purge_orphans()` for the daily task.
- `classes/reportbuilder/`: three Report Builder datasources with one row per set, built on core's
  entities and the engine's `sets` entity (one entity per repeatable field of the area, listed by the
  area handler's `get_categories_with_fields()`, enabled shared categories included):
  - `datasource\course_sets`: main table `course`, base condition `id != SITEID` (as core's
    `core_course\reportbuilder\datasource\courses`), course and course category entities.
  - `datasource\cohort_sets`: main table `cohort`, no base condition (as core's
    `core_cohort\reportbuilder\datasource\cohorts`), the cohort entity; members are not joined.
  - `datasource\group_sets`: main table `course`, base condition `id != SITEID`, groups and each
    group's groupings joined as core's `core_group\reportbuilder\datasource\groups` does; members are
    not joined.
  - Availability of a datasource: PostgreSQL or MySQL/MariaDB (the engine's sub-item SQL has no other
    form) and at least one repeatable field in the area. Like every core datasource, no capability of
    its own: who may create and view custom reports is core Report Builder's (capabilities and
    audiences).
  - Availability of each field's columns and filters (`local\entities\repeatable_set::is_available()`):
    the AREA handler's `can_view($field, 0)`, exactly what `core_reportbuilder\local\helpers\custom_fields`
    sets on core's own column of the same field: a course field's visibility (`NOTVISIBLE` hides it from
    everyone, `VISIBLETOTEACHERS` asks `moodle/course:update` in the system context); a cohort field
    asks `moodle/cohort:manage` or `moodle/cohort:view` in the system context; a group or grouping field
    asks `moodle/course:managegroups` or `moodle/course:view` in the global course's context.
  - Join (`local\helpers\area_sets::entities()`): projection rows on the instance id, the field id and
    an `EXISTS` on the `customfield_data` record's `component` and `area`, because a shared field keeps
    one field id across areas whose instance ids collide. Report Builder entity joins take no
    parameters, so the component and area (the plugin's own constants, refused unless they are plain
    identifiers) and the cast field id are the only interpolated values.
  - Every value and dictionary label goes through `s()` in the engine's entity, and the dictionary
    filter options are escaped there (core's autocomplete prints option text unescaped).
- SQL uses placeholders; the engine's projection upsert interpolates only the fixed table and owner
  column names `local\source` hands it.
- Privacy: a metadata provider declaring the projection table plus core's `customfield_provider`
  callbacks (export writes the sets as plain text; `before_delete_data()`/`before_delete_fields()`
  delete projection rows). The value itself is core_customfield's.

## Facts that look like findings but are by design

- **`get_default_value()` is null**, so core's Report Builder filter does not wrap the JSON column
  in `COALESCE` with a default.
- **An empty value is stored as `''`**, not `{}`, so core's "is empty" sees no value.
- **Every save path runs the same code checks** in `instance_form_save()` (form, web services, course
  upload tool, group and cohort APIs), with the stored payload and the saving user's dictionary
  rights, and refuses a failing value with one message that names the field only. The one path that
  writes without the type is core's restore: it copies a value a user could already see on the source
  course, and a crafted backup is gated by the restore capabilities. This residual is by design.
- **Stored codes are resolved to labels without a visibility check** in the summary and display, as
  the dictionary plugin's contract allows; submitted codes are not.
- A pattern column's error message comes from `customfield_pattern` already escaped and is passed
  into the form error unescaped on purpose (engine).
- **Report columns are available per field, not per instance**, exactly as core's own custom field
  columns (`can_view($field, 0)`): a viewer of a report sees the sets of every instance in it. Which
  rows a report shows to whom is Report Builder's audience and capability model, as for every core
  datasource.
- **Dictionary filter options of a shared field** list the codes the field holds in every area
  (engine `sets::name_filter_options()` reads the projection by field id): a known engine residual
  until it takes an area scope.

## De-emphasise

- `lang/**`, `tests/**`, `docs/**`, `mutations/**` and `CHANGELOG.md` carry no production behaviour.
