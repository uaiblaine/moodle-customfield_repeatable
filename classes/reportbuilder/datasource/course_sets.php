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

namespace customfield_repeatable\reportbuilder\datasource;

use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use customfield_repeatable\reportbuilder\local\helpers\area_sets;

/**
 * Report Builder datasource yielding one row per set of the repeatable course custom fields.
 *
 * The core course and course category entities, as in core's courses datasource
 * ({@see \core_course\reportbuilder\datasource\courses}), with its base condition (the site course
 * excluded); then one entity per repeatable course field, named "repeatable_<shortname>", so a course
 * with N sets yields N rows. Selecting columns from two repeatable fields in one report cross-joins
 * their sets, so a report should target one field.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_sets extends datasource {
    /** @var string Component of the custom field area this datasource lists. */
    public const COMPONENT = 'core_course';

    /** @var string The custom field area this datasource lists. */
    public const AREA = 'course';

    /** @var string Prefix of each field's entity name, followed by the field's shortname. */
    public const ENTITY_PREFIX = 'repeatable_';

    /**
     * Return the user-friendly name of the datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasourcecoursesets', 'customfield_repeatable');
    }

    /**
     * Offered on PostgreSQL and MySQL/MariaDB, once the course area lists a repeatable field.
     *
     * @return bool
     */
    public static function is_available(): bool {
        if (!area_sets::db_supported()) {
            return false;
        }
        return !empty(area_sets::fields(area_sets::handler(self::COMPONENT, self::AREA)));
    }

    /**
     * Initialise the datasource: course and category entities, plus one sets entity per repeatable field.
     */
    protected function initialise(): void {
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');

        $this->set_main_table('course', $coursealias);

        // Exclude the site course, as core's courses datasource does.
        $paramsiteid = database::generate_param_name();
        $this->add_base_condition_sql("{$coursealias}.id != :{$paramsiteid}", [$paramsiteid => SITEID]);

        $this->add_entity($courseentity);

        $categoryentity = new course_category();
        $categoryalias = $categoryentity->get_table_alias('course_categories');
        $this->add_entity($categoryentity
            ->add_join("JOIN {course_categories} {$categoryalias} ON {$categoryalias}.id = {$coursealias}.category"));

        $this->add_all_from_entities([$categoryentity->get_entity_name(), $courseentity->get_entity_name()]);

        $handler = area_sets::handler(self::COMPONENT, self::AREA);
        foreach (area_sets::entities($handler, self::ENTITY_PREFIX, 'entitysetnamed', $coursealias) as $entity) {
            $this->add_entity($entity);
            $this->add_all_from_entity($entity->get_entity_name());
        }
    }

    /**
     * Return the columns added to the report when it is created.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['course:coursefullnamewithlink'];
    }

    /**
     * Return the column sorting added to the report when it is created.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return ['course:coursefullnamewithlink' => SORT_ASC];
    }

    /**
     * Return the filters added to the report when it is created.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['course_category:name', 'course:fullname'];
    }

    /**
     * Return the conditions added to the report when it is created.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
