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
 * Tests for the cohort_sets Report Builder datasource.
 *
 * Metadata stays in docblock annotations: the plugin will ship on Moodle 4.5 (owner decision D6),
 * whose moodle-cs cannot read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @category   test
 * @covers     \customfield_repeatable\reportbuilder\datasource\cohort_sets
 * @covers     \customfield_repeatable\reportbuilder\local\entities\repeatable_set
 * @covers     \customfield_repeatable\reportbuilder\local\helpers\area_sets
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_sets_test extends sets_report_testcase {
    /**
     * The datasource is offered once the cohort area lists a repeatable field, and only then.
     */
    public function test_is_available_reflects_a_cohort_area_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertFalse(cohort_sets::is_available());
        $this->area_field('core_course', 'course', 'coursecontacts');
        $this->assertFalse(cohort_sets::is_available(), 'A course field does not count');

        $this->area_field('core_cohort', 'cohort', 'contacts');
        $this->assertTrue(cohort_sets::is_available());
    }

    /**
     * The datasource is hidden on databases the engine's sub-item SQL has no form for.
     */
    public function test_is_available_only_on_supported_db_families(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_cohort', 'cohort', 'contacts');
        $this->assert_db_allowlist(cohort_sets::class);
    }

    /**
     * One row per set, each under its own cohort: never another cohort's or another field's sets.
     */
    public function test_one_row_per_set_of_the_right_cohort_and_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_cohort', 'cohort', 'contacts');
        $this->area_field('core_cohort', 'cohort', 'others');
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'K1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'K2']);
        $this->getDataGenerator()->create_cohort(['name' => 'K3']);

        $this->save_sets('core_cohort', 'cohort', (int)$cohort1->id, 'contacts', [
            ['name' => 'Ann', 'role' => 'Head'],
            ['name' => 'Bob', 'role' => 'Clerk'],
        ]);
        $this->save_sets('core_cohort', 'cohort', (int)$cohort2->id, 'contacts', [['name' => 'Cid', 'role' => 'Dean']]);
        $this->save_sets('core_cohort', 'cohort', (int)$cohort1->id, 'others', [['name' => 'Other', 'role' => 'X']]);

        $report = $this->sets_report(cohort_sets::class, [
            'cohort:name',
            'repeatable_contacts:setnumber',
            'repeatable_contacts:name',
            'repeatable_contacts:role',
        ]);

        $this->assertSame([
            ['K1', '1', 'Ann', 'Head'],
            ['K1', '2', 'Bob', 'Clerk'],
            ['K2', '1', 'Cid', 'Dean'],
            ['K3', '', '', ''],
        ], $this->rows_of($report, ['K1', 'K2', 'K3']));
        $this->assertCount(4, $this->rows($report));
    }

    /**
     * A shared field (Moodle 5.1+) lists only the values saved in the cohort area, never a course's of the same id.
     */
    public function test_shared_field_lists_only_cohort_area_values(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->shared_field('sharedcontacts', [['core_course', 'course'], ['core_cohort', 'cohort']]);
        $course = $this->getDataGenerator()->create_course();
        $cohort = $this->cohort_with_id((int)$course->id, 'K1');

        $this->save_sets('core_cohort', 'cohort', (int)$cohort->id, 'sharedcontacts', [['name' => 'Cohort', 'role' => 'A']]);
        $this->save_sets('core_course', 'course', (int)$course->id, 'sharedcontacts', [
            ['name' => 'Course1', 'role' => 'B'],
            ['name' => 'Course2', 'role' => 'C'],
        ]);
        $this->assertSame(3, $DB->count_records('customfield_repeatable_set', ['instanceid' => $cohort->id]));

        $report = $this->sets_report(cohort_sets::class, ['cohort:name', 'repeatable_sharedcontacts:name']);
        $this->assertSame([[$cohort->name, 'Cohort']], $this->rows_of($report, [$cohort->name]));
    }

    /**
     * Visibility cases: the viewer, and the expected availability.
     *
     * @return array
     */
    public static function visibility_provider(): array {
        return [
            'admin' => ['admin', true],
            'plain user' => ['user', false],
            'cohort:view in the system context' => ['systemview', true],
            'cohort:manage in the system context' => ['systemmanage', true],
            'cohort:view in a category only' => ['categoryview', false],
        ];
    }

    /**
     * Every column and filter of a field is available exactly when core's own column of the same field is.
     *
     * Core decides with cohort_handler::can_view($field, 0): moodle/cohort:manage or moodle/cohort:view
     * in the system context, so a category-level cohort viewer does not see the field.
     *
     * @dataProvider visibility_provider
     * @param string $viewer Who views the report.
     * @param bool $expected Whether the columns and filters are available.
     */
    public function test_availability_follows_core(string $viewer, bool $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_cohort', 'cohort', 'contacts');
        $report = $this->sets_report(cohort_sets::class, []);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $capability = $viewer === 'systemmanage' ? 'moodle/cohort:manage' : 'moodle/cohort:view';
        if ($viewer === 'categoryview') {
            $context = \core\context\coursecat::instance($this->getDataGenerator()->create_category()->id);
        } else {
            $context = \core\context\system::instance();
        }
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        if (in_array($viewer, ['systemview', 'systemmanage', 'categoryview'], true)) {
            role_assign($roleid, $user->id, $context->id);
        }
        if ($viewer !== 'admin') {
            $this->setUser($user);
        }

        [$ours, $core] = $this->availability($report, 'repeatable_contacts', 'cohort:customfield_contacts');
        $this->assertSame($expected, $core, 'Core decides as expected');
        $this->assertSame($core, $ours, 'The sets follow core');
    }

    /**
     * A shared field (Moodle 5.1+) is available as the cohort handler decides, as core's own column is.
     *
     * Core asks the handler of the area it lists, not the field's own: core_customfield's shared
     * handler shows every shared field to everyone, the cohort handler only to cohort viewers.
     */
    public function test_shared_field_availability_is_the_area_handlers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->shared_field('sharedcontacts', [['core_cohort', 'cohort']]);
        $report = $this->sets_report(cohort_sets::class, []);

        $this->setUser($this->getDataGenerator()->create_user());
        [$ours, $core] = $this->availability($report, 'repeatable_sharedcontacts', 'cohort:customfield_sharedcontacts');
        $this->assertFalse($core, 'Core hides it from a user who cannot view cohorts');
        $this->assertFalse($ours, 'The sets follow core');
    }

    /**
     * The column and filter identifiers saved reports persist do not change: "repeatable_<shortname>:<name>".
     */
    public function test_column_and_filter_identifiers_are_stable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_cohort', 'cohort', 'langs', [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
        ]);

        $expected = [
            'repeatable_langs:lang_code',
            'repeatable_langs:lang_name',
            'repeatable_langs:name',
            'repeatable_langs:setnumber',
        ];
        [$columnids, $filterids] = $this->identifiers(cohort_sets::class, 'repeatable_langs');
        $this->assertSame($expected, $columnids, 'Column identifiers');
        $this->assertSame($expected, $filterids, 'Filter identifiers');
    }

    /**
     * Every sets column runs and sorts on this database family, and a value is escaped.
     */
    public function test_every_column_runs_sorted_and_escapes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->area_field('core_cohort', 'cohort', 'contacts');
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'K1']);
        $this->save_sets('core_cohort', 'cohort', (int)$cohort->id, 'contacts', [['name' => '<b>A</b>', 'role' => 'x']]);

        $this->assertSame(3, $this->run_every_column(cohort_sets::class, 'repeatable_contacts'));

        $report = $this->sets_report(cohort_sets::class, ['cohort:name', 'repeatable_contacts:name']);
        $this->assertSame([['K1', '&lt;b&gt;A&lt;/b&gt;']], $this->rows_of($report, ['K1']));
    }
}
