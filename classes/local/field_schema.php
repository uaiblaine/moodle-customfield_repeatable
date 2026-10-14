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

/**
 * The column schema of a repeatable custom field, read from its configuration.
 *
 * Custom field adapter: the schema is a version 2 document ({@see schema}) kept as a JSON STRING
 * under `schema` inside the field's `configdata`, which core stores JSON-encoded
 * (core_customfield\api::save_field_configuration()). Keeping it a string leaves
 * {@see schema::from_json()} and the strict comparison of stored pattern objects unchanged.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field_schema {
    /** @var string The configdata key holding the schema JSON. */
    public const CONFIG_KEY = 'schema';

    /** @var string The field type, as {customfield_field}.type stores it. */
    public const TYPE = 'repeatable';

    /**
     * The schema a field's configdata holds, or an empty schema when it holds none.
     *
     * An empty schema has no keys, so every payload normalises to no set: nothing is shown,
     * validated or projected from a field whose schema cannot be read.
     *
     * @param array|string|null $configdata Decoded configdata, or the JSON core stores.
     * @return schema
     */
    public static function from_configdata($configdata): schema {
        return self::parse($configdata) ?? schema::from_columns([]);
    }

    /**
     * The schema of a field controller, as its configdata holds it.
     *
     * @param \core_customfield\field_controller $field
     * @return schema
     */
    public static function from_field(\core_customfield\field_controller $field): schema {
        return self::from_configdata($field->get('configdata'));
    }

    /**
     * The schema of a STORED repeatable field, read from {customfield_field}.
     *
     * The recorded fact a definition is checked against: never the submitted configuration.
     *
     * @param int $fieldid
     * @return schema|null Null when no repeatable field has that id or its schema is not version 2.
     */
    public static function stored(int $fieldid): ?schema {
        global $DB;

        if ($fieldid <= 0) {
            return null;
        }
        $configdata = $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid, 'type' => self::TYPE]);
        if ($configdata === false) {
            return null;
        }
        return self::parse($configdata);
    }

    /**
     * The schema JSON of some configdata, as a schema.
     *
     * Readers that rewrite the projection use this, not {@see self::from_configdata()}: an empty
     * schema would read every payload as holding no set and delete its rows.
     *
     * @param array|string|null $configdata Decoded configdata, or the JSON core stores.
     * @return schema|null Null when it holds no version 2 schema.
     */
    public static function parse($configdata): ?schema {
        if (is_string($configdata)) {
            $configdata = json_decode($configdata, true);
        }
        if (!is_array($configdata) || !is_string($configdata[self::CONFIG_KEY] ?? null)) {
            return null;
        }
        return schema::from_json($configdata[self::CONFIG_KEY]);
    }
}
