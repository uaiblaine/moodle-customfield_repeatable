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

/**
 * Data generator of customfield_repeatable: repeatable course custom fields for tests.
 *
 * @package    customfield_repeatable
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Builds column schemas and repeatable custom fields.
 *
 * @package    customfield_repeatable
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfield_repeatable_generator extends component_generator_base {
    /**
     * The schema JSON of some columns ({@see local_repeatable_generator::schema_json()}).
     *
     * A column is a label (a free column) or an array of schema properties; a missing key is
     * generated from the label, a missing mode is "free", and the first column is the title
     * unless one is flagged.
     *
     * @param array $columns
     * @return string
     */
    public function schema_json(array $columns): string {
        /** @var local_repeatable_generator $engine */
        $engine = $this->datagenerator->get_plugin_generator('local_repeatable');
        return $engine->schema_json($columns);
    }

    /**
     * Create a repeatable custom field with some columns, by default in a new course category.
     *
     * Goes through core's generator, so the field is saved as the configuration form saves it
     * (core_customfield\handler::save_field_configuration()).
     *
     * @param array $columns See {@see self::schema_json()}.
     * @param array $record Other field properties: categoryid, shortname, name, configdata (merged).
     * @return \customfield_repeatable\field_controller
     */
    public function create_field(array $columns, array $record = []): \customfield_repeatable\field_controller {
        /** @var core_customfield_generator $core */
        $core = $this->datagenerator->get_plugin_generator('core_customfield');
        if (empty($record['categoryid'])) {
            $record['categoryid'] = $core->create_category()->get('id');
        }
        $record['configdata'] = ($record['configdata'] ?? []) + ['schema' => $this->schema_json($columns)];
        $field = $core->create_field($record + [
            'type' => 'repeatable',
            'name' => 'Repeatable test',
            'shortname' => 'repeatable_test',
        ]);
        return $field;
    }
}
