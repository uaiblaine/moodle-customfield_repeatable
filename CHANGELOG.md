# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Security

- Every save path now runs the form's code checks in `instance_form_save()`: core's web services
  (`core_course_create_courses`, `core_course_update_courses`) and the group and cohort APIs save
  custom field values without `instance_form_validation()`, so a user who could edit an instance but
  not read a restricted dictionary could store one of its codes and read its label back. A value that
  fails the checks is refused with one message that names the field only. The form, web services,
  course upload and group and cohort APIs share one validator construction.
- Deleting a record's or a field's value removes its projection rows and core's data in one transaction.

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
