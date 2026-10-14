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

namespace customfield_repeatable;

/**
 * Event observers keeping {customfield_repeatable_set} in step with core writes.
 *
 * Every other core path that writes or deletes a value goes through this type's controllers
 * (instance_form_save(), delete(), field_controller::delete(), the privacy callbacks). A restore
 * does not: it writes {customfield_data} directly, and calls the type back only for the course's
 * fields the restoring user may see.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Rebuild the projection of a restored course, its groups and its groupings.
     *
     * The event is triggered once the whole restore plan has run (restore_plan::execute()), after
     * every value is written.
     *
     * @param \core\event\course_restored $event
     * @return void
     */
    public static function course_restored(\core\event\course_restored $event): void {
        local\projection::reproject_course((int)$event->objectid);
    }
}
