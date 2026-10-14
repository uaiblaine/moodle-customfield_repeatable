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

namespace customfield_repeatable\privacy;

use core_course\customfield\course_handler;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use customfield_repeatable\local\source;

/**
 * Privacy provider of the repeatable custom field.
 *
 * Plugin CI never runs core's compliance sweep, so the compliance is asserted here. Metadata stays
 * in docblocks: the plugin will also ship for Moodle 4.5, whose moodle-cs cannot read PHPUnit
 * attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\privacy\provider
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \customfield_repeatable\field_controller The test field. */
    private \customfield_repeatable\field_controller $field;

    /**
     * Create the test field as the admin.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \customfield_repeatable_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('customfield_repeatable');
        $this->field = $generator->create_field(
            [['key' => 'name', 'label' => 'Full <b>name</b>'], 'Role'],
            ['shortname' => 'people']
        );
    }

    /**
     * The plugin counts as compliant with the privacy API, and declares its projection table.
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('customfield_repeatable'));

        $items = provider::get_metadata(new collection('customfield_repeatable'))->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame(source::TABLE, $items[0]->get_name());
        $this->assertArrayNotHasKey('userid', $items[0]->get_privacy_fields());
    }

    /**
     * Core's export of a value writes its sets as plain text, under the column labels.
     */
    public function test_export_writes_the_sets(): void {
        $course = $this->getDataGenerator()->create_course([
            'customfield_people' => '{"@@ID#1":{"name":"<script>x</script>","role":"**Tutor**"}}',
        ]);
        $data = course_handler::create()->get_instance_data((int)$course->id, true)[$this->field->get('id')];

        \core_customfield\privacy\provider::export_customfield_data($data, ['Custom fields']);

        $exported = writer::with_context(\context_course::instance($course->id))->get_data(['Custom fields']);
        $this->assertSame('people', $exported->fieldshortname);
        $this->assertSame(['Set 1' => ['Full name' => '<script>x</script>', 'Role' => '**Tutor**']], $exported->value);
    }

    /**
     * Core's deletion of a context's data deletes their projection rows; another course keeps its own.
     */
    public function test_deleting_a_contexts_data_deletes_its_rows(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['customfield_people' => '{"@@ID#1":{"name":"Ana"}}']);
        $other = $this->getDataGenerator()->create_course(['customfield_people' => '{"@@ID#1":{"name":"Bia"}}']);

        \core_customfield\privacy\provider::delete_customfields_data_for_context(
            'core_course',
            'course',
            \context_course::instance($course->id)
        );

        $this->assertSame(0, $DB->count_records(source::TABLE, ['instanceid' => $course->id]));
        $this->assertSame(1, $DB->count_records(source::TABLE, ['instanceid' => $other->id]));
    }

    /**
     * Core's deletion of the fields configuration deletes their projection rows.
     */
    public function test_deleting_the_configuration_deletes_the_rows(): void {
        global $DB;

        $this->getDataGenerator()->create_course(['customfield_people' => '{"@@ID#1":{"name":"Ana"}}']);
        $this->assertSame(1, $DB->count_records(source::TABLE, ['fieldid' => $this->field->get('id')]));

        \core_customfield\privacy\provider::delete_customfields_configuration_for_context(
            'core_course',
            'course',
            \context_system::instance()
        );

        $this->assertSame(0, $DB->count_records(source::TABLE));
        $this->assertFalse($DB->record_exists('customfield_field', ['id' => $this->field->get('id')]));
    }
}
