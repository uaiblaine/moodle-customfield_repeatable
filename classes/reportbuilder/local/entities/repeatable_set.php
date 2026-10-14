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

namespace customfield_repeatable\reportbuilder\local\entities;

use core_customfield\field_controller;
use core_customfield\handler;
use core_reportbuilder\local\helpers\database;
use customfield_repeatable\local\field_schema;
use customfield_repeatable\local\source;
use local_repeatable\local\schema;
use local_repeatable\reportbuilder\local\entities\sets;

/**
 * Report Builder entity exposing one repeatable custom field of one area as one row per set.
 *
 * Custom field adapter: the projection is {customfield_repeatable_set}, the schema is the field's
 * configdata, and the columns and filters are available as core decides for its own custom field
 * columns ({@see self::is_available()}). One class serves every area because core decides with one
 * helper too: what differs per area is the handler it asks. The columns and filters are the
 * engine's ({@see sets}); the datasource joins the projection
 * ({@see \customfield_repeatable\reportbuilder\local\helpers\area_sets}).
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repeatable_set extends sets {
    /** @var field_controller The field this entity represents. */
    private field_controller $field;

    /** @var handler The handler of the area the report lists, which decides visibility. */
    private handler $handler;

    /**
     * Constructor.
     *
     * @param field_controller $field A repeatable field the area's handler lists (its own or a shared one).
     * @param handler $handler The handler of the area the report lists, never the field's own: a
     *                         shared field's handler is core_customfield's, which shows it to everyone.
     */
    public function __construct(field_controller $field, handler $handler) {
        parent::__construct(source::TABLE, (int)$field->get('id'));
        $this->field = $field;
        $this->handler = $handler;
    }

    /**
     * The field's column schema; an empty one when its configdata holds none.
     *
     * @return schema
     */
    protected function get_schema(): schema {
        return field_schema::from_field($this->field);
    }

    /**
     * Whether the current user may see this field in reports.
     *
     * Same decision as core's own custom field columns and filters: the area handler's can_view()
     * with instance id 0 ({@see \core_reportbuilder\local\helpers\custom_fields::get_columns()} and
     * get_filters()). Per-instance access is therefore not checked, as in core: for a course field
     * VISIBLETOTEACHERS asks moodle/course:update in the system context, a cohort field asks
     * moodle/cohort:manage or moodle/cohort:view in the system context, and a group or grouping field
     * asks moodle/course:managegroups or moodle/course:view in the context of the global course.
     *
     * @return bool
     */
    protected function is_available(): bool {
        return $this->handler->can_view($this->field, 0);
    }

    /**
     * Narrows the dictionary name-filter options to the codes saved in this entity's area.
     *
     * A shared field (Moodle 5.1+) keeps one field id across areas, so the engine's options query,
     * which reads the projection by field id, would list codes saved in other areas. The projection
     * row points at its {customfield_data} record, which core stamps with the area; the component and
     * area are those of the handler the entity was built with, the ones the datasource join uses
     * ({@see \customfield_repeatable\reportbuilder\local\helpers\area_sets::entities()}). They are
     * bound, so no identifier check is needed here.
     *
     * @param string $alias The alias the projection table has in the options query.
     * @return array{0: string, 1: array} SQL condition on the alias and its named parameters.
     */
    protected function filter_options_scope(string $alias): array {
        $component = database::generate_param_name();
        $area = database::generate_param_name();

        return [
            "EXISTS (SELECT 1
                       FROM {customfield_data} fod
                      WHERE fod.id = {$alias}.dataid
                        AND fod.component = :{$component}
                        AND fod.area = :{$area})",
            [
                $component => $this->handler->get_component(),
                $area => $this->handler->get_area(),
            ],
        ];
    }
}
