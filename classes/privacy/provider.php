<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace customfield_repeatable\privacy;

use core_customfield\data_controller;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use customfield_repeatable\local\field_schema;
use customfield_repeatable\local\source;
use local_repeatable\privacy\set_exporter;

/**
 * Privacy provider of the repeatable custom field.
 *
 * The value lives in core's {customfield_data}, which core_customfield declares, finds, exports
 * and deletes for every area. This type keeps a copy of each set in {customfield_repeatable_set}
 * (the projection), so it declares that table and follows core's custom field callbacks
 * ({@see \core_customfield\privacy\customfield_provider}): the export writes the sets as plain
 * text, and every delete core makes of data or fields deletes their projection rows first. The
 * projection holds no user id; whose data it is follows the instance, as core decides it.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_customfield\privacy\customfield_provider,
    \core_privacy\local\metadata\provider {
    /**
     * The projection table, a copy of the sets of {customfield_data}.value.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        return $collection->add_database_table(source::TABLE, [
            'dataid' => 'privacy:metadata:customfield_repeatable_set:dataid',
            'fieldid' => 'privacy:metadata:customfield_repeatable_set:fieldid',
            'instanceid' => 'privacy:metadata:customfield_repeatable_set:instanceid',
            'set_id' => 'privacy:metadata:customfield_repeatable_set:set_id',
            'data' => 'privacy:metadata:customfield_repeatable_set:data',
            'timecreated' => 'privacy:metadata:customfield_repeatable_set:timecreated',
            'timemodified' => 'privacy:metadata:customfield_repeatable_set:timemodified',
        ], 'privacy:metadata:customfield_repeatable_set');
    }

    /**
     * Export one value: its sets, each mapping a column name to its value, as plain text.
     *
     * Core has put the summary HTML of export_value() in $exportdata->value; the sets replace it
     * ({@see set_exporter::export()}), so the export carries every column, not only the title and
     * subline ones.
     *
     * @param data_controller $data
     * @param \stdClass $exportdata
     * @param array $subcontext
     * @return void
     */
    public static function export_customfield_data(data_controller $data, \stdClass $exportdata, array $subcontext) {
        $schema = field_schema::from_field($data->get_field());
        $exportdata->value = set_exporter::export($schema, (string)($data->get_value() ?? ''));
        writer::with_context($data->get_context())->export_data($subcontext, $exportdata);
    }

    /**
     * Delete the projection rows of the data core is about to delete.
     *
     * @param string $dataidstest Test on {customfield_data}.id ("IN (SELECT ...)" or similar).
     * @param array $params Its named parameters.
     * @param array $contextids The affected contexts.
     * @return void
     */
    public static function before_delete_data(string $dataidstest, array $params, array $contextids) {
        global $DB;

        $DB->delete_records_select(source::TABLE, "dataid $dataidstest", $params);
    }

    /**
     * Delete the projection rows of the fields core is about to delete.
     *
     * Core runs before_delete_data() for their data first; this also removes rows left behind
     * by a record deleted without the type.
     *
     * @param string $fieldidstest Test on {customfield_field}.id.
     * @param array $params Its named parameters.
     * @param array $contextids The affected configuration contexts.
     * @return void
     */
    public static function before_delete_fields(string $fieldidstest, array $params, array $contextids) {
        global $DB;

        $DB->delete_records_select(source::TABLE, "fieldid $fieldidstest", $params);
    }
}
