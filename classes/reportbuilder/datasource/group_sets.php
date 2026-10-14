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

use core_group\reportbuilder\local\entities\{group, grouping};
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use customfield_repeatable\reportbuilder\local\helpers\area_sets;

/**
 * Report Builder datasource yielding one row per set of the repeatable group and grouping custom fields.
 *
 * Built as core's groups datasource is ({@see \core_group\reportbuilder\datasource\groups}): the
 * course table with the site course excluded, its groups, and each group's groupings. Then one entity
 * per repeatable group field ("repeatable_group_<shortname>"), joined to the group, and one per
 * repeatable grouping field ("repeatable_grouping_<shortname>"), joined to the grouping. Group
 * members are left out: joined, they would multiply the sets. A grouping is reached through its
 * groups, as in core, so a grouping with no group has no row.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_sets extends datasource {
    /** @var string Component of the custom field areas this datasource lists. */
    public const COMPONENT = 'core_group';

    /** @var string The group custom field area. */
    public const GROUP_AREA = 'group';

    /** @var string The grouping custom field area. */
    public const GROUPING_AREA = 'grouping';

    /** @var string Prefix of each group field's entity name, followed by the field's shortname. */
    public const GROUP_ENTITY_PREFIX = 'repeatable_group_';

    /** @var string Prefix of each grouping field's entity name, followed by the field's shortname. */
    public const GROUPING_ENTITY_PREFIX = 'repeatable_grouping_';

    /**
     * Return the user-friendly name of the datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasourcegroupsets', 'customfield_repeatable');
    }

    /**
     * Offered on PostgreSQL and MySQL/MariaDB, once the group or the grouping area lists a repeatable field.
     *
     * @return bool
     */
    public static function is_available(): bool {
        if (!area_sets::db_supported()) {
            return false;
        }
        return !empty(area_sets::fields(area_sets::handler(self::COMPONENT, self::GROUP_AREA)))
            || !empty(area_sets::fields(area_sets::handler(self::COMPONENT, self::GROUPING_AREA)));
    }

    /**
     * Initialise the datasource: course, group and grouping entities, plus one sets entity per repeatable field.
     */
    protected function initialise(): void {
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');

        $this->set_main_table('course', $coursealias);
        $this->add_entity($courseentity);

        // Exclude the site course, as core's groups datasource does.
        $paramsiteid = database::generate_param_name();
        $this->add_base_condition_sql("{$coursealias}.id != :{$paramsiteid}", [$paramsiteid => SITEID]);

        // Re-use the context table alias/join from the course entity in the group and grouping entities, as core does.
        $contextalias = $courseentity->get_table_alias('context');
        $this->add_join($courseentity->get_context_join());

        $groupentity = (new group())
            ->set_table_alias('context', $contextalias);
        $groupsalias = $groupentity->get_table_alias('groups');
        $this->add_entity($groupentity
            ->add_join("LEFT JOIN {groups} {$groupsalias} ON {$groupsalias}.courseid = {$coursealias}.id"));

        $groupingentity = (new grouping())
            ->set_table_alias('context', $contextalias);
        $groupingsalias = $groupingentity->get_table_alias('groupings');
        $groupinginnerselect = "
            SELECT gr.*, grg.groupid
              FROM {groupings} gr
              JOIN {groupings_groups} grg ON grg.groupingid = gr.id";
        $this->add_entity($groupingentity
            ->add_joins($groupentity->get_joins())
            ->add_join("LEFT JOIN ({$groupinginnerselect}) {$groupingsalias}
                ON {$groupingsalias}.courseid = {$coursealias}.id AND {$groupingsalias}.groupid = {$groupsalias}.id"));

        $this->add_all_from_entities([
            $courseentity->get_entity_name(),
            $groupentity->get_entity_name(),
            $groupingentity->get_entity_name(),
        ]);

        $grouphandler = area_sets::handler(self::COMPONENT, self::GROUP_AREA);
        $groupsets = area_sets::entities(
            $grouphandler,
            self::GROUP_ENTITY_PREFIX,
            'entitygroupsetnamed',
            $groupsalias,
            $groupentity->get_joins()
        );
        $groupinghandler = area_sets::handler(self::COMPONENT, self::GROUPING_AREA);
        $groupingsets = area_sets::entities(
            $groupinghandler,
            self::GROUPING_ENTITY_PREFIX,
            'entitygroupingsetnamed',
            $groupingsalias,
            $groupingentity->get_joins()
        );
        foreach (array_merge($groupsets, $groupingsets) as $entity) {
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
        return ['course:coursefullnamewithlink', 'group:name'];
    }

    /**
     * Return the column sorting added to the report when it is created.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'course:coursefullnamewithlink' => SORT_ASC,
            'group:name' => SORT_ASC,
        ];
    }

    /**
     * Return the filters added to the report when it is created.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['course:fullname', 'group:name'];
    }

    /**
     * Return the conditions added to the report when it is created, as core's groups datasource does.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['course:fullname', 'group:name'];
    }
}
