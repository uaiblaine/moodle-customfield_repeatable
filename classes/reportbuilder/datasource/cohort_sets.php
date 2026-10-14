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

use core_cohort\reportbuilder\local\entities\cohort;
use core_reportbuilder\datasource;
use core_reportbuilder\local\filters\boolean_select;
use customfield_repeatable\reportbuilder\local\helpers\area_sets;

/**
 * Report Builder datasource yielding one row per set of the repeatable cohort custom fields.
 *
 * The core cohort entity on the cohort table, as in core's cohorts datasource
 * ({@see \core_cohort\reportbuilder\datasource\cohorts}), which has no base condition; its default
 * condition (visible cohorts) is carried over. Then one entity per repeatable cohort field, named
 * "repeatable_<shortname>". Cohort members are left out: joined, they would multiply the sets.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_sets extends datasource {
    /** @var string Component of the custom field area this datasource lists. */
    public const COMPONENT = 'core_cohort';

    /** @var string The custom field area this datasource lists. */
    public const AREA = 'cohort';

    /** @var string Prefix of each field's entity name, followed by the field's shortname. */
    public const ENTITY_PREFIX = 'repeatable_';

    /**
     * Return the user-friendly name of the datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasourcecohortsets', 'customfield_repeatable');
    }

    /**
     * Offered on PostgreSQL and MySQL/MariaDB, once the cohort area lists a repeatable field.
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
     * Initialise the datasource: the cohort entity, plus one sets entity per repeatable field.
     */
    protected function initialise(): void {
        $cohortentity = new cohort();
        $cohortalias = $cohortentity->get_table_alias('cohort');

        $this->set_main_table('cohort', $cohortalias);
        $this->add_entity($cohortentity);
        $this->add_all_from_entity($cohortentity->get_entity_name());

        $handler = area_sets::handler(self::COMPONENT, self::AREA);
        foreach (area_sets::entities($handler, self::ENTITY_PREFIX, 'entitysetnamed', $cohortalias) as $entity) {
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
        return ['cohort:name'];
    }

    /**
     * Return the column sorting added to the report when it is created.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return ['cohort:name' => SORT_ASC];
    }

    /**
     * Return the filters added to the report when it is created.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['cohort:context', 'cohort:name'];
    }

    /**
     * Return the conditions added to the report when it is created, as core's cohorts datasource does.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['cohort:visible'];
    }

    /**
     * Return the condition values set when the report is created: visible cohorts, as in core.
     *
     * @return array
     */
    public function get_default_condition_values(): array {
        return ['cohort:visible_operator' => boolean_select::CHECKED];
    }
}
