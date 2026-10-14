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

namespace customfield_repeatable\task;

use core\task\scheduled_task;
use customfield_repeatable\local\field_schema;
use customfield_repeatable\local\projection;
use customfield_repeatable\local\source;
use local_repeatable\local\projection\projector;

/**
 * Rebuild the per-set projection of every value whose canonical payload changed behind the type.
 *
 * Writers that change {customfield_data} without this type (a restore whose callbacks did not
 * reach a record, any direct write) leave {customfield_repeatable_set} stale; pages are unaffected
 * because they read the payload, but reports list the stale sets until this task runs. It also
 * deletes the rows whose record is gone.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reproject_sets extends scheduled_task {
    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskreprojectsets', 'customfield_repeatable');
    }

    /**
     * Compare every repeatable field's payloads with their projection, rewrite the stale ones, purge orphans.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $fields = $DB->get_records('customfield_field', ['type' => field_schema::TYPE], 'id ASC', 'id, shortname, configdata');
        foreach ($fields as $field) {
            $schema = field_schema::parse($field->configdata);
            if ($schema === null) {
                continue;
            }
            $count = projector::reproject_field(new source(), (int)$field->id, $schema);
            if ($count > 0) {
                mtrace('customfield_repeatable: rebuilt ' . $count . ' projection(s) of field ' . $field->shortname);
            }
        }
        $purged = projection::purge_orphans();
        if ($purged > 0) {
            mtrace('customfield_repeatable: deleted ' . $purged . ' orphaned projection row(s)');
        }
    }
}
