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

use backup;
use backup_controller;
use core_course\customfield\course_handler;
use customfield_repeatable\local\source;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * A restore writes {customfield_data} without the type: the projection is rebuilt after it.
 *
 * Metadata stays in docblocks: the plugin will also ship for Moodle 4.5, whose moodle-cs cannot
 * read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\data_controller
 * @covers     \customfield_repeatable\observer
 * @covers     \customfield_repeatable\local\projection
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Reset, as the admin.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The plugin's generator.
     *
     * @return \customfield_repeatable_generator
     */
    private function generator(): \customfield_repeatable_generator {
        return $this->getDataGenerator()->get_plugin_generator('customfield_repeatable');
    }

    /**
     * The projection rows of one value, as [instanceid, set_id, name].
     *
     * @param int $fieldid
     * @param int $instanceid
     * @return array
     */
    private function rows(int $fieldid, int $instanceid): array {
        global $DB;
        $rows = [];
        foreach ($DB->get_records(source::TABLE, ['fieldid' => $fieldid, 'instanceid' => $instanceid], 'set_id ASC') as $row) {
            $rows[] = [(int)$row->instanceid, (int)$row->set_id, json_decode($row->data, true)['name']];
        }
        return $rows;
    }

    /**
     * Back a course up and restore it as a new course, as the admin.
     *
     * @param int $courseid
     * @return int The new course id.
     */
    private function backup_and_restore(int $courseid): int {
        global $CFG, $DB, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $courseid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = 'customfield_repeatable_' . $courseid;
        $bc->get_results()['backup_destination']->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );
        $bc->destroy();

        $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = restore_dbops::create_new_course('Restored', 'restored', $categoryid);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Restoring a course rebuilds the projection of its values, visible to the restorer or not.
     *
     * Core calls the type back only for the fields the restoring user may see; a field course
     * visibility hides from everyone is rebuilt by the course_restored observer.
     */
    public function test_restore_rebuilds_the_projection(): void {
        $visible = $this->generator()->create_field(['Name'], ['shortname' => 'shown']);
        $hidden = $this->generator()->create_field(['Name'], [
            'shortname' => 'hidden',
            'categoryid' => $visible->get('categoryid'),
            'configdata' => ['visibility' => course_handler::NOTVISIBLE],
        ]);
        $course = $this->getDataGenerator()->create_course([
            'customfield_shown' => '{"@@ID#1":{"name":"Ana"},"@@ID#2":{"name":"Bia"}}',
            'customfield_hidden' => '{"@@ID#1":{"name":"Caio"}}',
        ]);

        $newcourseid = $this->backup_and_restore((int)$course->id);

        $this->assertSame(
            [[$newcourseid, 1, 'Ana'], [$newcourseid, 2, 'Bia']],
            $this->rows((int)$visible->get('id'), $newcourseid)
        );
        $this->assertSame([[$newcourseid, 1, 'Caio']], $this->rows((int)$hidden->get('id'), $newcourseid));
        // The source course keeps its own rows.
        $this->assertCount(2, $this->rows((int)$visible->get('id'), (int)$course->id));
    }

    /**
     * Restoring a course rebuilds the projection of its groups' values, which core restores with no callback.
     */
    public function test_restore_rebuilds_group_values(): void {
        global $DB;

        $category = $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_category([
            'component' => 'core_group',
            'area' => 'group',
        ]);
        $field = $this->generator()->create_field(['Name'], ['shortname' => 'groupsets', 'categoryid' => $category->get('id')]);
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
            'name' => 'Team',
            'customfield_groupsets' => '{"@@ID#1":{"name":"Ana"}}',
        ]);
        $this->assertSame([[(int)$group->id, 1, 'Ana']], $this->rows((int)$field->get('id'), (int)$group->id));

        $newcourseid = $this->backup_and_restore((int)$course->id);

        $newgroupid = (int)$DB->get_field('groups', 'id', ['courseid' => $newcourseid, 'name' => 'Team']);
        $this->assertSame([[$newgroupid, 1, 'Ana']], $this->rows((int)$field->get('id'), $newgroupid));
    }

    /**
     * The restore callback rebuilds the record core restored now.
     *
     * Called as core_customfield\handler::restore_define_structure() calls it, after the value was
     * written directly, as course_handler::restore_instance_data_from_backup() writes it.
     */
    public function test_restore_callback_rebuilds_the_restored_record(): void {
        global $DB;

        $field = $this->generator()->create_field(['Name'], ['shortname' => 'shown']);
        $course = $this->getDataGenerator()->create_course();
        $data = \core_customfield\data_controller::create(0, (object)['instanceid' => $course->id], $field);
        $data->set('value', '{"@@ID#1":{"name":"Ana"}}');
        $data->set('contextid', \context_course::instance($course->id)->id);
        $data->save();
        $this->assertSame([], $this->rows((int)$field->get('id'), (int)$course->id));

        $step = $this->createMock(\restore_structure_step::class);
        course_handler::create()->restore_define_structure($step, (int)$data->get('id'), 99);

        $this->assertSame([[(int)$course->id, 1, 'Ana']], $this->rows((int)$field->get('id'), (int)$course->id));
        $this->assertSame(1, $DB->count_records(source::TABLE, ['dataid' => $data->get('id')]));
    }

    /**
     * The course reprojection reaches the course's values and its groups', and nothing of another course.
     */
    public function test_reproject_course_is_scoped_to_the_course(): void {
        global $DB;

        $field = $this->generator()->create_field(['Name'], ['shortname' => 'shown']);
        $course = $this->getDataGenerator()->create_course(['customfield_shown' => '{"@@ID#1":{"name":"Ana"}}']);
        $other = $this->getDataGenerator()->create_course(['customfield_shown' => '{"@@ID#1":{"name":"Bia"}}']);
        // Direct writes behind the type, as a restore makes them.
        foreach ([$course, $other] as $target) {
            $DB->set_field('customfield_data', 'value', '{"@@ID#1":{"name":"New"}}', [
                'fieldid' => $field->get('id'),
                'instanceid' => $target->id,
            ]);
        }

        $this->assertSame(1, local\projection::reproject_course((int)$course->id));

        $this->assertSame([[(int)$course->id, 1, 'New']], $this->rows((int)$field->get('id'), (int)$course->id));
        $this->assertSame([[(int)$other->id, 1, 'Bia']], $this->rows((int)$field->get('id'), (int)$other->id));
        // A second run finds nothing stale.
        $this->assertSame(0, local\projection::reproject_course((int)$course->id));
    }
}
