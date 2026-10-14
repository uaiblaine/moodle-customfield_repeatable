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

use core_course\customfield\course_handler;
use core_reportbuilder\manager;
use customfield_repeatable\reportbuilder\local\entities\repeatable_set;
use customfield_repeatable\tests\sets_report_testcase;

/**
 * Tests for the course_sets Report Builder datasource.
 *
 * Metadata stays in docblock annotations: the plugin will ship on Moodle 4.5 (owner decision D6),
 * whose moodle-cs cannot read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @category   test
 * @covers     \customfield_repeatable\reportbuilder\datasource\course_sets
 * @covers     \customfield_repeatable\reportbuilder\local\entities\repeatable_set
 * @covers     \customfield_repeatable\reportbuilder\local\helpers\area_sets
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_sets_test extends sets_report_testcase {
    /**
     * The datasource is offered once the course area lists a repeatable field, and only then.
     */
    public function test_is_available_reflects_a_course_area_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertFalse(course_sets::is_available());

        // A repeatable field of another area, and a course field of another type, do not count.
        $this->area_field('core_cohort', 'cohort', 'cohortcontacts');
        $category = $this->getDataGenerator()->create_custom_field_category([]);
        $this->getDataGenerator()->create_custom_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => 'plain',
        ]);
        $this->assertFalse(course_sets::is_available());

        $this->area_field('core_course', 'course', 'contacts');
        $this->assertTrue(course_sets::is_available());
    }

    /**
     * The datasource is hidden on databases the engine's sub-item SQL has no form for.
     */
    public function test_is_available_only_on_supported_db_families(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_course', 'course', 'contacts');
        $this->assert_db_allowlist(course_sets::class);
    }

    /**
     * One row per set, each under its own course: never another course's, another field's or the site course's sets.
     */
    public function test_one_row_per_set_of_the_right_course_and_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_course', 'course', 'contacts');
        $this->area_field('core_course', 'course', 'others');
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'C1']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'C2']);
        $this->getDataGenerator()->create_course(['fullname' => 'C3']);

        $this->save_sets('core_course', 'course', (int)$course1->id, 'contacts', [
            ['name' => 'Ann', 'role' => 'Head'],
            ['name' => 'Bob', 'role' => 'Clerk'],
        ]);
        $this->save_sets('core_course', 'course', (int)$course2->id, 'contacts', [['name' => 'Cid', 'role' => 'Dean']]);
        $this->save_sets('core_course', 'course', (int)$course1->id, 'others', [['name' => 'Other', 'role' => 'X']]);
        $this->save_sets('core_course', 'course', SITEID, 'contacts', [['name' => 'Site', 'role' => 'Front']]);

        $report = $this->sets_report(course_sets::class, [
            'course:fullname',
            'repeatable_contacts:setnumber',
            'repeatable_contacts:name',
            'repeatable_contacts:role',
        ]);

        $this->assertSame([
            ['C1', '1', 'Ann', 'Head'],
            ['C1', '2', 'Bob', 'Clerk'],
            ['C2', '1', 'Cid', 'Dean'],
            ['C3', '', '', ''],
        ], $this->rows_of($report, ['C1', 'C2', 'C3']));

        $site = get_site();
        $this->assertSame([], $this->rows_of($report, [$site->fullname]), 'The site course is excluded');
        $this->assertCount(3, array_unique(array_column($this->rows($report), 0)), 'Only C1, C2 and C3');
    }

    /**
     * Only the rows of a record that is there, in the course area, are listed; rows left behind by a deleted record are not.
     *
     * Moodle 4.5 has no shared fields, so the field id already pins the area; the join's check on the
     * record (its field's category names the area) still drops the rows of a {customfield_data} record
     * deleted behind the type, until the daily task purges them.
     */
    public function test_rows_of_a_deleted_record_are_not_listed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $field = $this->area_field('core_course', 'course', 'contacts');
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'C1']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'C2']);
        $this->save_sets('core_course', 'course', (int)$course1->id, 'contacts', [['name' => 'Ann', 'role' => 'Head']]);
        $this->save_sets('core_course', 'course', (int)$course2->id, 'contacts', [['name' => 'Bob', 'role' => 'Clerk']]);

        // A direct delete behind the type: the record goes, its projection row stays.
        $DB->delete_records('customfield_data', ['fieldid' => $field->get('id'), 'instanceid' => $course2->id]);
        $this->assertSame(1, $DB->count_records('customfield_repeatable_set', ['instanceid' => $course2->id]));

        $report = $this->sets_report(course_sets::class, ['course:fullname', 'repeatable_contacts:name']);
        $this->assertSame([['C1', 'Ann'], ['C2', '']], $this->rows_of($report, ['C1', 'C2']));
    }

    /**
     * The area's names are written into the join SQL only when they are plain identifiers.
     */
    public function test_area_names_must_be_plain_identifiers(): void {
        $this->resetAfterTest();

        $handler = $this->createMock(\core_customfield\handler::class);
        $handler->method('get_component')->willReturn('core_course');
        $handler->method('get_area')->willReturn("course' OR '1' = '1");
        $handler->method('get_categories_with_fields')->willReturn([]);

        $this->expectException(\coding_exception::class);
        \customfield_repeatable\reportbuilder\local\helpers\area_sets::entities($handler, 'repeatable_', 'entitysetnamed', 'c');
    }

    /**
     * Visibility cases of a course field: its visibility setting, the viewer, and the expected availability.
     *
     * @return array
     */
    public static function visibility_provider(): array {
        return [
            'everyone, plain user' => [course_handler::VISIBLETOALL, 'user', true],
            'teachers, plain user' => [course_handler::VISIBLETOTEACHERS, 'user', false],
            'teachers, a course\'s editing teacher' => [course_handler::VISIBLETOTEACHERS, 'teacher', false],
            'teachers, system manager' => [course_handler::VISIBLETOTEACHERS, 'manager', true],
            'teachers, admin' => [course_handler::VISIBLETOTEACHERS, 'admin', true],
            'nobody, plain user' => [course_handler::NOTVISIBLE, 'user', false],
            'nobody, admin' => [course_handler::NOTVISIBLE, 'admin', false],
        ];
    }

    /**
     * Every column and filter of a field is available exactly when core's own column of the same field is.
     *
     * Core decides with course_handler::can_view($field, 0): NOTVISIBLE hides from everyone, admins
     * included; VISIBLETOTEACHERS asks moodle/course:update in the system context, so a course's
     * editing teacher does not see it.
     *
     * @dataProvider visibility_provider
     * @param int $visibility The field's visibility (course_handler::NOTVISIBLE, VISIBLETOTEACHERS or VISIBLETOALL).
     * @param string $viewer Who views the report: user, teacher, manager or admin.
     * @param bool $expected Whether the columns and filters are available.
     */
    public function test_availability_follows_core_per_field_visibility(int $visibility, string $viewer, bool $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_course', 'course', 'contacts', ['Name', 'Role'], ['visibility' => $visibility]);
        $report = $this->sets_report(course_sets::class, []);

        $user = $this->getDataGenerator()->create_user();
        if ($viewer === 'teacher') {
            $course = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        } else if ($viewer === 'manager') {
            $this->getDataGenerator()->role_assign('manager', $user->id, \core\context\system::instance()->id);
        }
        if ($viewer !== 'admin') {
            $this->setUser($user);
        }

        [$ours, $core] = $this->availability($report, 'repeatable_contacts', 'course:customfield_contacts');
        $this->assertSame($expected, $core, 'Core decides as expected');
        $this->assertSame($core, $ours, 'The sets follow core');

        // Control: the course entity of the same report is available to everyone.
        $this->assertTrue(manager::get_report_from_persistent($report)->get_column('course:fullname')->get_is_available());
    }

    /**
     * The column and filter identifiers saved reports persist do not change.
     *
     * Core stores a report's columns and filters by "<entity name>:<column name>", so a renamed entity
     * or column empties every saved report that used it. The entity is "repeatable_<field shortname>";
     * the names are the engine's ({@see \local_repeatable\local\schema::report_identifiers()}): a free,
     * a dictionary, an any-code dictionary and, with customfield_pattern, a pattern column.
     */
    public function test_column_and_filter_identifiers_are_stable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pattern = \local_repeatable\local\bridge\pattern::available();
        $columns = [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
            ['key' => 'level', 'label' => 'Level', 'mode' => 'dictionary_open', 'domain' => 'langs'],
        ];
        if ($pattern) {
            $columns[] = ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf']];
        }
        $this->area_field('core_course', 'course', 'langs', $columns);

        $expected = [
            'repeatable_langs:lang_code',
            'repeatable_langs:lang_name',
            'repeatable_langs:level_code',
            'repeatable_langs:level_name',
            'repeatable_langs:name',
            'repeatable_langs:setnumber',
        ];
        if ($pattern) {
            array_unshift($expected, 'repeatable_langs:cpf');
        }
        [$columnids, $filterids] = $this->identifiers(course_sets::class, 'repeatable_langs');
        $this->assertSame($expected, $columnids, 'Column identifiers');
        $this->assertSame($expected, $filterids, 'Filter identifiers');
    }

    /**
     * Every sets column runs and sorts on this database family.
     */
    public function test_every_column_runs_sorted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_course', 'course', 'langs', [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary_open', 'domain' => 'langs'],
        ]);
        $course = $this->getDataGenerator()->create_course();
        $this->save_sets('core_course', 'course', (int)$course->id, 'langs', [['name' => 'A', 'lang' => 'pt']]);

        $this->assertSame(4, $this->run_every_column(course_sets::class, 'repeatable_langs'));
    }

    /**
     * Stored text and a dictionary label holding markup are escaped in the columns and the filter options.
     */
    public function test_values_and_dictionary_labels_are_escaped(): void {
        if (!class_exists('\\local_dictionaries\\resolver')) {
            $this->markTestSkipped('local_dictionaries is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        repeatable_set::reset_label_memo();

        /** @var \local_dictionaries_generator $dictionaries */
        $dictionaries = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $dictionaries->create_domain(['shortname' => 'roles', 'name' => 'Roles']);
        $dictionaries->create_item(['domain' => 'roles', 'code' => '7', 'label' => '<script>alert(1)</script>']);

        $this->area_field('core_course', 'course', 'contacts', [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'role', 'label' => 'Role', 'mode' => 'dictionary', 'domain' => 'roles'],
        ]);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'C1']);
        $this->save_sets('core_course', 'course', (int)$course->id, 'contacts', [
            ['name' => '<script>alert(2)</script>', 'role' => '7'],
        ]);

        $report = $this->sets_report(course_sets::class, [
            'course:fullname',
            'repeatable_contacts:name',
            'repeatable_contacts:role_name',
            'repeatable_contacts:role_code',
        ]);
        $this->assertSame([[
            'C1',
            '&lt;script&gt;alert(2)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '7',
        ]], $this->rows_of($report, ['C1']));

        manager::reset_caches();
        $filter = manager::get_report_from_persistent($report)->get_filter('repeatable_contacts:role_name');
        $this->assertSame(['7' => '&lt;script&gt;alert(1)&lt;/script&gt;'], $filter->get_options());
    }

    /**
     * The dictionary name filter lists only the codes of records that are there, in the course area.
     *
     * The options are read from the projection by field id; the entity's scope keeps them to rows
     * whose {customfield_data} record exists in the course area (its field's category), so the code
     * of a record deleted behind the type is not offered.
     */
    public function test_filter_options_skip_the_rows_of_a_deleted_record(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $field = $this->area_field('core_course', 'course', 'contacts', [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'role', 'label' => 'Role', 'mode' => 'dictionary_open', 'domain' => 'roles'],
        ]);
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $this->save_sets('core_course', 'course', (int)$course1->id, 'contacts', [['name' => 'Kept', 'role' => 'A']]);
        $this->save_sets('core_course', 'course', (int)$course2->id, 'contacts', [['name' => 'Gone', 'role' => 'B']]);
        $DB->delete_records('customfield_data', ['fieldid' => $field->get('id'), 'instanceid' => $course2->id]);
        $this->assertSame(1, $DB->count_records('customfield_repeatable_set', ['instanceid' => $course2->id]));

        $report = $this->sets_report(course_sets::class, ['course:fullname', 'repeatable_contacts:role_name']);
        manager::reset_caches();
        $filter = manager::get_report_from_persistent($report)->get_filter('repeatable_contacts:role_name');
        $options = array_keys($filter->get_options());
        $this->assertContains('A', $options);
        $this->assertNotContains('B', $options, 'The code of a deleted record is not offered');
    }
}
