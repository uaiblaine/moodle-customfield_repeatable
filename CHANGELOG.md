# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

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
