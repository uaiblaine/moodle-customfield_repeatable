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

use local_repeatable\local\schema;
use local_repeatable\local\stored_values as engine_stored_values;

/**
 * What a field's stored payloads hold, read from the canonical {customfield_data} rows.
 *
 * Custom field adapter of {@see engine_stored_values}: it reads the payloads of every row of the
 * field, whatever its area, and the engine decides which keys hold values. Never from the
 * projection: a restore writes {customfield_data} without the type, so the projection can miss
 * values that exist.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stored_values {
    /**
     * Which column keys hold a non-blank value in some set of one field, and how many instances have sets.
     *
     * @param int $fieldid
     * @param schema $schema The field's stored schema ({@see field_schema::stored()}).
     * @return array ['users' => int instances with at least one set, 'keys' => string[] the keys in use, in schema order].
     */
    public static function usage(int $fieldid, schema $schema): array {
        global $DB;

        if ($fieldid <= 0) {
            return ['users' => 0, 'keys' => []];
        }
        $rs = $DB->get_recordset('customfield_data', ['fieldid' => $fieldid], 'id ASC', 'id, value');
        try {
            return engine_stored_values::keys_in_use(self::payloads($rs), $schema);
        } finally {
            $rs->close();
        }
    }

    /**
     * Key => stored label of every column of a STORED field whose key holds values.
     *
     * @param int $fieldid The stored field's id.
     * @return string[] Empty when no repeatable field has that id.
     */
    public static function locked_labels(int $fieldid): array {
        $schema = field_schema::stored($fieldid);
        if ($schema === null) {
            return [];
        }
        $labels = [];
        foreach (self::usage($fieldid, $schema)['keys'] as $key) {
            $labels[$key] = $schema->label($key);
        }
        return $labels;
    }

    /**
     * The raw payload of each record of a recordset, without loading them all.
     *
     * @param \moodle_recordset $rs Records with a `value` property.
     * @return \Generator The payloads, as stored.
     */
    private static function payloads(\moodle_recordset $rs): \Generator {
        foreach ($rs as $row) {
            yield (string)$row->value;
        }
    }
}
