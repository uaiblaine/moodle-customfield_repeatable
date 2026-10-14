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

namespace customfield_repeatable\reportbuilder\local\helpers;

use core\lang_string;
use core_customfield\field_controller;
use core_customfield\handler;
use core_reportbuilder\local\helpers\database;
use customfield_repeatable\local\field_schema;
use customfield_repeatable\local\source;
use customfield_repeatable\reportbuilder\local\entities\repeatable_set;

/**
 * The repeatable fields of one custom field area, as Report Builder entities joined to the area's instances.
 *
 * Shared by the datasources of every area ({@see \customfield_repeatable\reportbuilder\datasource\course_sets},
 * cohort_sets, group_sets). The fields are listed as core lists them for its own custom field columns
 * ({@see \core_reportbuilder\local\helpers\custom_fields::get_columns()}): the area handler's
 * get_categories_with_fields(), which includes the shared categories (Moodle 5.1+) enabled for the
 * area. Each field becomes one entity whose projection rows are joined on the instance and the field,
 * and on the area the value was saved in, because a shared field keeps one field id across areas.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class area_sets {
    /**
     * Whether the database family has a form for the engine's sub-item SQL.
     *
     * Only PostgreSQL and MySQL/MariaDB: {@see \local_repeatable\local\sql::column_value()} has no other
     * form, and its JSON_EXTRACT fallback does not exist on SQL Server (nor on Oracle, which Moodle 4.5
     * still installs on), so every sub-item column would fail there. Same allowlist as
     * {@see \profilefield_repeatable\reportbuilder\datasource\repeatable_sets::is_available()}.
     *
     * @return bool
     */
    public static function db_supported(): bool {
        global $DB;

        return in_array($DB->get_dbfamily(), ['postgres', 'mysql'], true);
    }

    /**
     * The handler of an area, as core's custom_fields helper gets it.
     *
     * @param string $component The area's component (core_course, core_cohort, core_group).
     * @param string $area The area (course, cohort, group, grouping).
     * @return handler
     */
    public static function handler(string $component, string $area): handler {
        return handler::get_handler($component, $area);
    }

    /**
     * The repeatable fields an area's handler lists, its own and the enabled shared ones, by id.
     *
     * @param handler $handler
     * @return field_controller[]
     */
    public static function fields(handler $handler): array {
        $fields = [];
        foreach ($handler->get_categories_with_fields() as $category) {
            foreach ($category->get_fields() as $field) {
                if ($field->get('type') === field_schema::TYPE) {
                    $fields[(int)$field->get('id')] = $field;
                }
            }
        }
        ksort($fields);
        return $fields;
    }

    /**
     * One entity per repeatable field of an area, joined to the area's instance table.
     *
     * The projection rows are joined on the instance id and the field id, and only when the
     * {customfield_data} record they project was saved in this area: core stamps the area on every
     * record (Moodle 5.1+), and a shared field holds the values of several areas under one field id,
     * so a cohort's sets would otherwise appear under the course of the same id. Report Builder joins
     * take no parameters, so the component and area are written into the SQL: they are checked to be
     * plain identifiers first, and the field id is cast.
     *
     * @param handler $handler The area's handler: it lists the fields and decides their visibility.
     * @param string $entityprefix Prefix of each entity name, followed by the field's shortname.
     * @param string $titlestring Lang string of this plugin titling each entity, with the field name as {$a}.
     * @param string $instancealias SQL alias of the area's instance table ({course}, {cohort}, {groups}...).
     * @param string[] $parentjoins Joins that bring the instance table into the query, if it is not the main table.
     * @return repeatable_set[]
     * @throws \coding_exception When the handler's component or area is not a plain identifier.
     */
    public static function entities(
        handler $handler,
        string $entityprefix,
        string $titlestring,
        string $instancealias,
        array $parentjoins = []
    ): array {
        $component = $handler->get_component();
        $area = $handler->get_area();
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $component) || !preg_match('/^[a-z][a-z0-9_]*$/', $area)) {
            throw new \coding_exception('A custom field area must be named by plain identifiers.');
        }

        $entities = [];
        foreach (self::fields($handler) as $fieldid => $field) {
            $entity = new repeatable_set($field, $handler);
            $entity->set_entity_name($entityprefix . $field->get('shortname'));
            // Core renders entity titles itself, as it does a custom field's: plain spelling in.
            $entity->set_entity_title(
                new lang_string($titlestring, 'customfield_repeatable', $field->get_formatted_name(false))
            );

            $setalias = $entity->get_table_alias(source::TABLE);
            $dataalias = database::generate_alias();
            $entity->add_joins($parentjoins);
            $entity->add_join(
                "LEFT JOIN {" . source::TABLE . "} {$setalias}
                        ON {$setalias}.instanceid = {$instancealias}.id
                       AND {$setalias}.fieldid = " . (int)$fieldid . "
                       AND EXISTS (
                               SELECT 1
                                 FROM {customfield_data} {$dataalias}
                                WHERE {$dataalias}.id = {$setalias}.dataid
                                  AND {$dataalias}.component = '{$component}'
                                  AND {$dataalias}.area = '{$area}'
                           )"
            );
            $entities[] = $entity;
        }
        return $entities;
    }
}
