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

use core_course\customfield\course_handler;
use customfield_repeatable\local\source;
use customfield_repeatable\local\summary;
use local_repeatable\local\bridge\dictionaries;

/**
 * Every save path runs the form's code checks: core's web services and group APIs do not validate.
 *
 * A teacher who may edit a course but not read a restricted dictionary must not be able to store a
 * code of it through a path that skips the form validation, or the stored code would resolve to its
 * label (display, export_value) without a read check. Metadata stays in docblocks: the plugin will
 * also ship for Moodle 4.5, whose moodle-cs cannot read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\data_controller
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_path_test extends \advanced_testcase {
    /** @var field_controller The course field. */
    private field_controller $field;

    /**
     * Skip without the companion, then create the restricted dictionary and a course field on it.
     *
     * 'cities' is restricted to local/dictionaries:view, which a teacher lacks: 3550308 and 3304557.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!dictionaries::lookup_available()) {
            $this->markTestSkipped('local_dictionaries with api::lookup() is not installed.');
        }
        $this->resetAfterTest();
        summary::reset_label_memo();

        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'cities', 'visibility' => 0]);
        $generator->create_item(['domain' => 'cities', 'code' => '3550308', 'label' => 'Secretville']);
        $generator->create_item(['domain' => 'cities', 'code' => '3304557', 'label' => 'Hiddenburg']);

        $this->setAdminUser();
        $this->field = $this->generator()->create_field(
            [['key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'domain' => 'cities', 'title' => true]],
            ['shortname' => 'places', 'name' => 'Places']
        );
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
     * A payload of one set holding a city code.
     *
     * @param string $code
     * @return string
     */
    private function payload(string $code): string {
        return json_encode(['@@ID#1' => ['city' => $code]]);
    }

    /**
     * A course with a stored city, saved by the admin, and an editing teacher in it.
     *
     * @param string|null $code The stored code; null stores nothing.
     * @return array [course, teacher]
     */
    private function course_with(?string $code): array {
        $course = $this->getDataGenerator()->create_course();
        if ($code !== null) {
            course_handler::create()->instance_form_save((object)[
                'id' => $course->id,
                'customfield_places' => $this->payload($code),
            ]);
        }
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        return [$course, $teacher];
    }

    /**
     * What is stored for a course: the value text and the projection row count.
     *
     * @param int $courseid
     * @return array [value or null, number of projection rows]
     */
    private function stored(int $courseid): array {
        global $DB;
        $fieldid = (int)$this->field->get('id');
        $value = $DB->get_field('customfield_data', 'value', ['fieldid' => $fieldid, 'instanceid' => $courseid]);
        $rows = $DB->count_records(source::TABLE, ['fieldid' => $fieldid, 'instanceid' => $courseid]);
        return [$value === false ? null : $value, $rows];
    }

    /**
     * The number of projection rows of one field and instance.
     *
     * @param int $fieldid
     * @param int $instanceid
     * @return int
     */
    private function rows(int $fieldid, int $instanceid): int {
        global $DB;
        return $DB->count_records(source::TABLE, ['fieldid' => $fieldid, 'instanceid' => $instanceid]);
    }

    /**
     * Assert a callable throws the uniform rejection, naming the field and nothing submitted.
     *
     * @param callable $save
     * @return void
     */
    private function assert_rejected(callable $save): void {
        try {
            $save();
            $this->fail('The save was not refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorsaverejected', $e->errorcode);
            $this->assertSame(get_string('errorsaverejected', 'customfield_repeatable', 'Places'), $e->getMessage());
            foreach (['3304557', '3550308', 'Hiddenburg', 'Secretville', 'cities'] as $secret) {
                $this->assertStringNotContainsString($secret, $e->getMessage());
            }
        }
    }

    /**
     * A teacher cannot store an unstored code of a dictionary they cannot read through create_course()/update_course().
     */
    public function test_teacher_cannot_save_an_unreadable_code_through_update_course(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        [$course, $teacher] = $this->course_with(null);
        $this->setUser($teacher);

        $this->assert_rejected(fn() => update_course((object)[
            'id' => $course->id,
            'customfield_places' => $this->payload('3304557'),
        ]));
        $this->assertSame([null, 0], $this->stored((int)$course->id));

        $this->assert_rejected(fn() => create_course((object)[
            'fullname' => 'New course',
            'shortname' => 'newcourse',
            'category' => $course->category,
            'customfield_places' => $this->payload('3304557'),
        ]));

        // Control 1: the admin, who may read the dictionary, stores the code.
        $this->setAdminUser();
        update_course((object)['id' => $course->id, 'customfield_places' => $this->payload('3304557')]);
        $this->assertSame([$this->payload('3304557'), 1], $this->stored((int)$course->id));
    }

    /**
     * The course web service refuses the same value with a warning that leaks nothing, and stores nothing.
     */
    public function test_course_web_service_refuses_an_unreadable_code(): void {
        [$course, $teacher] = $this->course_with(null);
        $this->setUser($teacher);
        $_POST['sesskey'] = sesskey();

        $customfield = ['shortname' => 'places', 'value' => $this->payload('3304557')];
        $result = \core_external\external_api::call_external_function('core_course_update_courses', [
            'courses' => [['id' => $course->id, 'customfields' => [$customfield]]],
        ], false);

        $this->assertFalse($result['error'], json_encode($result));
        $this->assertCount(1, $result['data']['warnings']);
        $warning = $result['data']['warnings'][0];
        $this->assertSame('errorsaverejected', $warning['warningcode']);
        $this->assertStringNotContainsString('3304557', $warning['message']);
        $this->assertStringNotContainsString('Hiddenburg', $warning['message']);
        $this->assertSame([null, 0], $this->stored((int)$course->id));
    }

    /**
     * A teacher keeps a code the course already stores, and cannot swap it for another through a save.
     */
    public function test_teacher_keeps_an_already_stored_code(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        [$course, $teacher] = $this->course_with('3550308');
        $this->setUser($teacher);

        // Control 2: the stored code is exempt from the read check on the save path, as on the form.
        update_course((object)['id' => $course->id, 'customfield_places' => json_encode([
            '@@ID#1' => ['city' => '3550308'],
            '@@ID#2' => ['city' => '3550308'],
        ])]);
        $this->assertSame(2, $this->stored((int)$course->id)[1]);

        // The exemption covers only the stored code.
        $before = $this->stored((int)$course->id);
        $this->assert_rejected(fn() => update_course((object)[
            'id' => $course->id,
            'customfield_places' => json_encode(['@@ID#1' => ['city' => '3550308'], '@@ID#2' => ['city' => '3304557']]),
        ]));
        $this->assertSame($before, $this->stored((int)$course->id));
    }

    /**
     * The group API, which also saves without the form validation, refuses the same code.
     */
    public function test_group_save_path_refuses_an_unreadable_code(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $category = $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_category([
            'component' => 'core_group',
            'area' => 'group',
        ]);
        $groupfield = $this->generator()->create_field(
            [['key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'domain' => 'cities', 'title' => true]],
            ['shortname' => 'groupplaces', 'name' => 'Places', 'categoryid' => $category->get('id')]
        );
        [$course, $teacher] = $this->course_with(null);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Team']);

        $this->setUser($teacher);
        $this->assert_rejected(fn() => groups_update_group((object)[
            'id' => $group->id,
            'courseid' => $course->id,
            'name' => 'Team',
            'customfield_groupplaces' => $this->payload('3304557'),
        ]));
        $this->assertSame(0, $this->rows((int)$groupfield->get('id'), (int)$group->id));

        // Control: the admin stores it.
        $this->setAdminUser();
        groups_update_group((object)[
            'id' => $group->id,
            'courseid' => $course->id,
            'name' => 'Team',
            'customfield_groupplaces' => $this->payload('3304557'),
        ]);
        $this->assertSame(1, $this->rows((int)$groupfield->get('id'), (int)$group->id));
    }
}
