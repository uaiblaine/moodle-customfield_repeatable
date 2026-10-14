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

namespace customfield_repeatable\local;

use local_repeatable\local\projection\store;
use local_repeatable\local\schema;

/**
 * The custom field's canonical payloads and projection rows, as the engine reads them.
 *
 * Custom field adapter: the canonical payload is {customfield_data}.value, the owner is the
 * instance (a course, cohort, group...), and the projection is {customfield_repeatable_set},
 * owned by instanceid. Implements the engine contract {@see \local_repeatable\local\projection\source}.
 *
 * Every projection row also carries its dataid: the instance id alone does not say which area a
 * row belongs to when a shared field (Moodle 5.1+) holds data in several areas, so a reader that
 * needs the area joins {customfield_data} on dataid.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source implements \local_repeatable\local\projection\source {
    /** @var string The projection table of the custom field. */
    public const TABLE = 'customfield_repeatable_set';

    /** @var string The owner column of the projection table. */
    public const OWNER_COLUMN = 'instanceid';

    /**
     * The plugin that owns the data.
     *
     * @return string
     */
    public function component(): string {
        return 'customfield_repeatable';
    }

    /**
     * The projection table, owned by instanceid.
     *
     * @return store
     */
    public function store(): store {
        return new store(self::TABLE, self::OWNER_COLUMN);
    }

    /**
     * The schema of a repeatable custom field.
     *
     * @param int $fieldid
     * @return schema|null Null when no repeatable field has that id, or its schema is not version 2:
     *                     a field whose schema cannot be read keeps its rows rather than losing them.
     */
    public function schema(int $fieldid): ?schema {
        return field_schema::stored($fieldid);
    }

    /**
     * One {customfield_data} record of a repeatable field, read as it is now.
     *
     * @param int $dataid
     * @return \stdClass|null {id, fieldid, ownerid (the instance), data}, or null when it is gone or
     *                        belongs to a field of another type.
     */
    public function canonical(int $dataid): ?\stdClass {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT d.id, d.fieldid, d.instanceid, d.value
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
              WHERE d.id = :dataid
                AND f.type = :type",
            ['dataid' => $dataid, 'type' => field_schema::TYPE]
        );
        if (!$record) {
            return null;
        }
        return (object)[
            'id' => (int)$record->id,
            'fieldid' => (int)$record->fieldid,
            'ownerid' => (int)$record->instanceid,
            'data' => (string)$record->value,
        ];
    }

    /**
     * Every {customfield_data} record of one field, in id order.
     *
     * @param int $fieldid
     * @return \moodle_recordset Records (id, instanceid, data).
     */
    public function canonical_recordset(int $fieldid): \moodle_recordset {
        global $DB;

        return $DB->get_recordset_sql(
            "SELECT d.id, d.instanceid, d.value AS data
               FROM {customfield_data} d
              WHERE d.fieldid = :fieldid
           ORDER BY d.id ASC",
            ['fieldid' => $fieldid]
        );
    }

    /**
     * The projection rows of one instance's value of one field.
     *
     * For a shared field (Moodle 5.1+) the same instance id can name instances of several areas:
     * the rows of all of them are returned. A caller that knows the record reads by dataid
     * ({@see self::projection_rows_of_record()}).
     *
     * @param int $fieldid
     * @param int $ownerid The instance id.
     * @return \stdClass[] Rows (id, set_id, data, timemodified) in set order.
     */
    public function projection_rows(int $fieldid, int $ownerid): array {
        global $DB;

        return array_values($DB->get_records(
            self::TABLE,
            ['fieldid' => $fieldid, self::OWNER_COLUMN => $ownerid],
            'set_id ASC, id ASC',
            'id, set_id, data, timemodified'
        ));
    }

    /**
     * The projection rows of one {customfield_data} record.
     *
     * @param int $dataid
     * @return \stdClass[] Rows (id, set_id, data, timemodified) in set order.
     */
    public function projection_rows_of_record(int $dataid): array {
        global $DB;

        if ($dataid <= 0) {
            return [];
        }
        return array_values($DB->get_records(
            self::TABLE,
            ['dataid' => $dataid],
            'set_id ASC, id ASC',
            'id, set_id, data, timemodified'
        ));
    }
}
