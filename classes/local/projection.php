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

use local_repeatable\local\payload;
use local_repeatable\local\projection\projector;

/**
 * Repairs of the projection that only the custom field adapter can scope.
 *
 * The staleness check and the rewrite are the engine's ({@see projector}), through this plugin's
 * {@see source}; this class decides WHICH records to look at: those of one restored course, and
 * the rows whose record is gone.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class projection {
    /** @var int Rows deleted per statement by {@see self::purge_orphans()}. */
    private const PURGE_CHUNK = 500;

    /**
     * Rebuild the rows of every repeatable value of one course, its groups and its groupings that no longer match.
     *
     * A restore writes these values without the type: core_course\customfield\course_handler and the
     * group and grouping handlers save {customfield_data} directly, and core asks the type to
     * restore only the course's fields the restoring user may see
     * (core_customfield\handler::restore_define_structure() reads get_instance_data(), visible
     * fields only); group and grouping values get no callback at all.
     *
     * The area is read from the record ({customfield_data}.component and area, which core stamps on
     * every row from Moodle 5.0), so the course id never matches a group of the same id.
     *
     * @param int $courseid
     * @return int Number of records whose rows were rewritten.
     */
    public static function reproject_course(int $courseid): int {
        global $DB;

        if ($courseid <= 0) {
            return 0;
        }
        $records = $DB->get_records_sql(
            "SELECT d.id, d.fieldid, d.value, f.configdata
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
              WHERE f.type = :type
                AND (
                       (d.component = :coursecomponent AND d.area = :coursearea AND d.instanceid = :courseid)
                    OR (d.component = :groupcomponent AND d.area = :grouparea
                        AND d.instanceid IN (SELECT g.id FROM {groups} g WHERE g.courseid = :groupcourseid))
                    OR (d.component = :groupingcomponent AND d.area = :groupingarea
                        AND d.instanceid IN (SELECT gg.id FROM {groupings} gg WHERE gg.courseid = :groupingcourseid))
                    )
           ORDER BY d.id ASC",
            [
                'type' => field_schema::TYPE,
                'coursecomponent' => 'core_course',
                'coursearea' => 'course',
                'courseid' => $courseid,
                'groupcomponent' => 'core_group',
                'grouparea' => 'group',
                'groupcourseid' => $courseid,
                'groupingcomponent' => 'core_group',
                'groupingarea' => 'grouping',
                'groupingcourseid' => $courseid,
            ]
        );
        if (empty($records)) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED, 'dataid');
        $rows = $DB->get_records_select(
            source::TABLE,
            "dataid $insql",
            $params,
            'dataid ASC, set_id ASC',
            'id, dataid, set_id, data'
        );
        $rowsbydataid = [];
        foreach ($rows as $row) {
            $rowsbydataid[(int)$row->dataid][] = $row;
        }

        $stale = [];
        foreach ($records as $record) {
            $schema = field_schema::parse($record->configdata);
            if ($schema === null) {
                continue;
            }
            $payload = payload::normalise((string)$record->value, $schema);
            if (!projector::is_current($payload, $rowsbydataid[(int)$record->id] ?? [])) {
                $stale[] = [(int)$record->id, $schema];
            }
        }
        return projector::write_stale(new source(), $stale);
    }

    /**
     * Delete the projection rows whose {customfield_data} record no longer exists, or no longer is repeatable.
     *
     * The type's own deletes remove their rows (field_controller::delete(), data_controller::delete(),
     * the privacy callbacks); this catches any other writer that deletes {customfield_data} directly.
     *
     * @return int Number of rows deleted.
     */
    public static function purge_orphans(): int {
        global $DB;

        $orphans = $DB->get_fieldset_sql(
            "SELECT s.id
               FROM {" . source::TABLE . "} s
          LEFT JOIN {customfield_data} d ON d.id = s.dataid
          LEFT JOIN {customfield_field} f ON f.id = d.fieldid AND f.type = :type
              WHERE f.id IS NULL
           ORDER BY s.id ASC",
            ['type' => field_schema::TYPE]
        );
        foreach (array_chunk($orphans, self::PURGE_CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'orphan');
            $DB->delete_records_select(source::TABLE, "id $insql", $params);
        }
        return count($orphans);
    }
}
