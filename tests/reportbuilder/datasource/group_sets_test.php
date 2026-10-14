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

use customfield_repeatable\tests\sets_report_testcase;

/**
 * Tests for the group_sets Report Builder datasource (group and grouping fields).
 *
 * Metadata stays in docblock annotations: the plugin will ship on Moodle 4.5 (owner decision D6),
 * whose moodle-cs cannot read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @category   test
 * @covers     \customfield_repeatable\reportbuilder\datasource\group_sets
 * @covers     \customfield_repeatable\reportbuilder\local\entities\repeatable_set
 * @covers     \customfield_repeatable\reportbuilder\local\helpers\area_sets
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class group_sets_test extends sets_report_testcase {
    /**
     * The datasource is offered once the group or the grouping area lists a repeatable field.
     */
    public function test_is_available_reflects_a_group_or_grouping_area_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertFalse(group_sets::is_available());
        $this->area_field('core_course', 'course', 'coursecontacts');
        $this->assertFalse(group_sets::is_available(), 'A course field does not count');

        $grouping = $this->area_field('core_group', 'grouping', 'plans');
        $this->assertTrue(group_sets::is_available(), 'A grouping field');

        $grouping->delete();
        $this->reset_area_handlers();
        $this->assertFalse(group_sets::is_available());

        $this->area_field('core_group', 'group', 'teams');
        $this->assertTrue(group_sets::is_available(), 'A group field');
    }

    /**
     * The datasource is hidden on databases the engine's sub-item SQL has no form for.
     */
    public function test_is_available_only_on_supported_db_families(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_group', 'group', 'teams');
        $this->assert_db_allowlist(group_sets::class);
    }

    /**
     * One row per set, each under its own group or grouping: never another's, another field's or the site course's.
     */
    public function test_one_row_per_set_of_the_right_group_and_grouping(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_group', 'group', 'teams');
        $this->area_field('core_group', 'group', 'others');
        $this->area_field('core_group', 'grouping', 'plans');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'G1']);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'G2']);
        $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'G3']);
        $grouping1 = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'P1']);
        $grouping2 = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'P2']);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping1->id, 'groupid' => $group1->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping2->id, 'groupid' => $group2->id]);
        $sitegroup = $this->getDataGenerator()->create_group(['courseid' => SITEID, 'name' => 'GS']);

        $this->save_sets('core_group', 'group', (int)$group1->id, 'teams', [
            ['name' => 'Ann', 'role' => 'Head'],
            ['name' => 'Bob', 'role' => 'Clerk'],
        ]);
        $this->save_sets('core_group', 'group', (int)$group2->id, 'teams', [['name' => 'Cid', 'role' => 'Dean']]);
        $this->save_sets('core_group', 'group', (int)$group1->id, 'others', [['name' => 'Other', 'role' => 'X']]);
        $this->save_sets('core_group', 'group', (int)$sitegroup->id, 'teams', [['name' => 'Site', 'role' => 'Front']]);
        $this->save_sets('core_group', 'grouping', (int)$grouping1->id, 'plans', [['name' => 'Plan', 'role' => 'P']]);

        $report = $this->sets_report(group_sets::class, [
            'group:name',
            'repeatable_group_teams:setnumber',
            'repeatable_group_teams:name',
            'repeatable_group_teams:role',
        ]);
        $this->assertSame([
            ['G1', '1', 'Ann', 'Head'],
            ['G1', '2', 'Bob', 'Clerk'],
            ['G2', '1', 'Cid', 'Dean'],
            ['G3', '', '', ''],
        ], $this->rows_of($report, ['G1', 'G2', 'G3']));
        $this->assertSame([], $this->rows_of($report, ['GS']), 'The site course is excluded');

        $report = $this->sets_report(group_sets::class, ['grouping:name', 'repeatable_grouping_plans:name']);
        $this->assertSame([['P1', 'Plan'], ['P2', '']], $this->rows_of($report, ['P1', 'P2']));
    }

    /**
     * Visibility cases: the viewer, whether the global course is the teacher's course, and the expected availability.
     *
     * @return array
     */
    public static function visibility_provider(): array {
        return [
            'admin' => ['admin', false, true],
            'plain user' => ['user', false, false],
            'editing teacher, site as the global course' => ['teacher', false, false],
            'editing teacher, own course as the global course' => ['teacher', true, true],
        ];
    }

    /**
     * Every column and filter of a group and a grouping field is available exactly when core's own column is.
     *
     * Core decides with group_handler::can_view($field, 0) and grouping_handler::can_view($field, 0):
     * moodle/course:managegroups or moodle/course:view in the context of the global course ($COURSE),
     * the site course on a report page.
     *
     * @dataProvider visibility_provider
     * @param string $viewer Who views the report: admin, user or teacher.
     * @param bool $owncourse Whether the global course is the teacher's course.
     * @param bool $expected Whether the columns and filters are available.
     */
    public function test_availability_follows_core(string $viewer, bool $owncourse, bool $expected): void {
        global $COURSE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_group', 'group', 'teams');
        $this->area_field('core_group', 'grouping', 'plans');
        $report = $this->sets_report(group_sets::class, []);

        $course = $this->getDataGenerator()->create_course();
        if ($viewer === 'teacher') {
            $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        } else if ($viewer === 'user') {
            $this->setUser($this->getDataGenerator()->create_user());
        }
        if ($owncourse) {
            $COURSE = $course;
        }

        [$ours, $core] = $this->availability($report, 'repeatable_group_teams', 'group:customfield_teams');
        $this->assertSame($expected, $core, 'Core decides as expected for the group field');
        $this->assertSame($core, $ours, 'The group sets follow core');

        [$ours, $core] = $this->availability($report, 'repeatable_grouping_plans', 'grouping:customfield_plans');
        $this->assertSame($expected, $core, 'Core decides as expected for the grouping field');
        $this->assertSame($core, $ours, 'The grouping sets follow core');
    }

    /**
     * The column and filter identifiers saved reports persist do not change.
     *
     * Group fields are "repeatable_group_<shortname>", grouping fields "repeatable_grouping_<shortname>":
     * the two areas keep their shortnames apart, so a group and a grouping field may share one.
     */
    public function test_column_and_filter_identifiers_are_stable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $columns = [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
        ];
        $this->area_field('core_group', 'group', 'langs', $columns);
        $this->area_field('core_group', 'grouping', 'langs', $columns);

        foreach (['repeatable_group_langs', 'repeatable_grouping_langs'] as $entity) {
            $expected = [
                "{$entity}:lang_code",
                "{$entity}:lang_name",
                "{$entity}:name",
                "{$entity}:setnumber",
            ];
            [$columnids, $filterids] = $this->identifiers(group_sets::class, $entity);
            $this->assertSame($expected, $columnids, "Column identifiers of {$entity}");
            $this->assertSame($expected, $filterids, "Filter identifiers of {$entity}");
        }
    }

    /**
     * Every sets column of both areas runs and sorts on this database family.
     */
    public function test_every_column_runs_sorted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_group', 'group', 'teams');
        $this->area_field('core_group', 'grouping', 'plans');
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $this->save_sets('core_group', 'group', (int)$group->id, 'teams', [['name' => 'A', 'role' => 'x']]);
        $this->save_sets('core_group', 'grouping', (int)$grouping->id, 'plans', [['name' => 'B', 'role' => 'y']]);

        $this->assertSame(3, $this->run_every_column(group_sets::class, 'repeatable_group_teams'));
        $this->assertSame(3, $this->run_every_column(group_sets::class, 'repeatable_grouping_plans'));
    }
}
