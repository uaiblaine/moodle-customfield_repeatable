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

/**
 * Instance data of the repeatable custom field, through the course handler.
 *
 * Metadata stays in docblocks: the plugin will also ship for Moodle 4.5, whose moodle-cs cannot
 * read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\data_controller
 * @covers     \customfield_repeatable\local\summary
 * @covers     \customfield_repeatable\form\widget_html
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class data_controller_test extends \advanced_testcase {
    /** @var field_controller The test field: name (title), role and city (subline), notes. */
    private field_controller $field;

    /** @var string The field's form element name. */
    private string $elementname = 'customfield_repeatable_test';

    /**
     * Create the test field as the admin.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        summary::reset_label_memo();
        $this->field = $this->generator()->create_field([
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'role', 'label' => 'Role', 'subline' => true],
            ['key' => 'city', 'label' => 'City', 'subline' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
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
     * A payload of sets, keyed as the widget keys them.
     *
     * @param array $sets List of sets: key => value.
     * @return string
     */
    private function payload(array $sets): string {
        $payload = [];
        foreach (array_values($sets) as $index => $set) {
            $payload['@@ID#' . ($index + 1)] = $set;
        }
        return json_encode($payload);
    }

    /**
     * Save a payload for a course through the course handler, as the course form does.
     *
     * @param int $courseid
     * @param string $payload
     * @return void
     */
    private function save(int $courseid, string $payload): void {
        course_handler::create()->instance_form_save((object)['id' => $courseid, $this->elementname => $payload]);
    }

    /**
     * The test field's data controller for a course, read fresh from the handler.
     *
     * @param int $courseid
     * @return data_controller
     */
    private function data(int $courseid): data_controller {
        $data = course_handler::create()->get_instance_data($courseid, true)[$this->field->get('id')];
        $this->assertInstanceOf(data_controller::class, $data);
        return $data;
    }

    /**
     * The projection rows of a course's value, in set order.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    private function rows(int $courseid): array {
        global $DB;
        return array_values($DB->get_records(
            source::TABLE,
            ['fieldid' => $this->field->get('id'), 'instanceid' => $courseid],
            'set_id ASC'
        ));
    }

    /**
     * Saving writes the canonical value and its projection rows; a later save drops vanished sets.
     */
    public function test_save_writes_the_value_and_its_projection(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->save((int)$course->id, $this->payload([
            ['name' => 'Ana', 'role' => 'Tutor', 'unknown' => 'dropped'],
            ['name' => 'Bia', 'city' => 'Rio'],
            ['name' => '  ', 'role' => ''],
        ]));

        $record = $DB->get_record('customfield_data', ['fieldid' => $this->field->get('id'), 'instanceid' => $course->id]);
        $this->assertSame(
            '{"@@ID#1":{"name":"Ana","role":"Tutor","city":"","notes":""},' .
                '"@@ID#2":{"name":"Bia","role":"","city":"Rio","notes":""}}',
            $record->value
        );
        $this->assertEquals(FORMAT_PLAIN, $record->valueformat);
        $this->assertEquals(0, $record->valuetrust);
        $rows = $this->rows((int)$course->id);
        $this->assertCount(2, $rows);
        $this->assertEquals($record->id, $rows[0]->dataid);
        $this->assertSame(['name' => 'Bia', 'role' => '', 'city' => 'Rio', 'notes' => ''], json_decode($rows[1]->data, true));

        $this->save((int)$course->id, $this->payload([['name' => 'Bia', 'city' => 'Rio']]));
        $rows = $this->rows((int)$course->id);
        $this->assertCount(1, $rows);
        $this->assertSame('Bia', json_decode($rows[0]->data, true)['name']);

        // An empty value is stored as '' and keeps no row, so core's "is empty" sees no value.
        $this->save((int)$course->id, '{}');
        $this->assertSame('', $DB->get_field('customfield_data', 'value', ['id' => $record->id]));
        $this->assertSame([], $this->rows((int)$course->id));
    }

    /**
     * A course created with a value (create_course(), the upload tool, web services) gets its projection.
     */
    public function test_create_course_projects_the_value(): void {
        $course = $this->getDataGenerator()->create_course([
            $this->elementname => $this->payload([['name' => 'Ana'], ['name' => 'Bia']]),
        ]);
        $this->assertCount(2, $this->rows((int)$course->id));
        $this->assertSame('Ana<br>Bia', $this->data((int)$course->id)->export_value());
    }

    /**
     * export_value() is the compact summary: one line per set, title then subline values, every value escaped.
     *
     * Core prints it unescaped in course listings and its Report Builder column: a script in a
     * value must come out as text, and Markdown must not be rendered (no format_text()).
     */
    public function test_export_value_is_an_escaped_summary(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->save((int)$course->id, $this->payload([
            ['name' => '<script>alert(1)</script>', 'role' => '**bold**', 'city' => 'A & B', 'notes' => 'not shown'],
            ['name' => 'Bia', 'notes' => 'not shown either'],
            ['role' => 'Only a role'],
            ['notes' => 'Only notes'],
        ]));

        $expected = '&lt;script&gt;alert(1)&lt;/script&gt; (**bold**, A &amp; B)<br>Bia<br>Only a role<br>Set 4';
        $this->assertSame($expected, $this->data((int)$course->id)->export_value());

        // The course listing shows the same text through core's field_data template.
        $listing = course_handler::create()->display_custom_fields_data([$this->data((int)$course->id)]);
        $this->assertStringContainsString($expected, $listing);
        $this->assertStringNotContainsString('<script>', $listing);
        $this->assertStringNotContainsString('<strong>', $listing);

        // An instance without sets exports nothing, also for a '{}' written behind the type.
        $empty = $this->getDataGenerator()->create_course();
        $this->assertNull($this->data((int)$empty->id)->export_value());
        $this->save((int)$empty->id, $this->payload([['name' => 'Ana']]));
        $DB->set_field('customfield_data', 'value', '{"@@ID#1":{"name":" "}}', [
            'fieldid' => $this->field->get('id'),
            'instanceid' => $empty->id,
        ]);
        $this->assertNull($this->data((int)$empty->id)->export_value());
    }

    /**
     * The type's display() is the engine's plain list, escaped.
     */
    public function test_display_is_the_plain_list(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->save((int)$course->id, $this->payload([['name' => '<b>Ana</b>', 'notes' => 'Shown here']]));

        $html = $this->data((int)$course->id)->display();
        $this->assertStringContainsString('&lt;b&gt;Ana&lt;/b&gt;', $html);
        $this->assertStringContainsString('Shown here', $html);
        $this->assertStringContainsString(get_string('repeatableset', 'local_repeatable', 1), $html);
    }

    /**
     * Core's Report Builder filter reads the bare column: a null default adds no COALESCE.
     */
    public function test_report_filter_reads_the_column_without_a_default(): void {
        $helper = new \core_reportbuilder\local\helpers\custom_fields('c.id', 'course', 'core_course', 'course');
        $filters = [];
        foreach ($helper->get_filters() as $filter) {
            $filters[$filter->get_name()] = $filter;
        }
        $filter = $filters['customfield_repeatable_test'];
        $this->assertStringNotContainsString('COALESCE', $filter->get_field_sql());
        $this->assertSame([], $filter->get_field_params());
        $this->assertNull($this->data(0)->get_default_value());
    }

    /**
     * Validate a submission for one field of a course, as core_customfield\handler::instance_form_validation() does per field.
     *
     * The handler itself is not called: other plugins on the site may add course fields of their
     * own, which would validate (and warn about) a submission that does not name them.
     *
     * @param field_controller $field
     * @param int $courseid
     * @param array $data The submitted form data.
     * @return array Errors.
     */
    private function validate(field_controller $field, int $courseid, array $data): array {
        $fieldid = (int)$field->get('id');
        $controller = course_handler::create()->get_instance_fields_data([$fieldid => $field], $courseid)[$fieldid];
        return $controller->instance_form_validation($data + ['id' => $courseid], []);
    }

    /**
     * Validation: the JSON object and the required setting, then the uniqueness check on the stored shape.
     */
    public function test_validation(): void {
        $required = $this->generator()->create_field(['Title'], [
            'shortname' => 'required_sets',
            'categoryid' => $this->field->get('categoryid'),
            'configdata' => ['required' => 1, 'uniquevalues' => 1],
        ]);
        $course = $this->getDataGenerator()->create_course();
        $element = 'customfield_required_sets';

        $this->assertSame(
            [$element => get_string('errorinvalidjson', 'local_repeatable')],
            $this->validate($required, (int)$course->id, [$element => '[1]'])
        );
        $this->assertSame(
            [$element => get_string('required')],
            $this->validate($required, (int)$course->id, [$element => '{}'])
        );
        $this->assertSame([], $this->validate($required, (int)$course->id, [$element => '{"@@ID#1":{"title":"x"}}']));

        // Another course holds the same set: written differently, it is still the same value.
        $other = $this->getDataGenerator()->create_course();
        course_handler::create()->instance_form_save((object)['id' => $other->id, $element => '{"@@ID#1":{"title":"x"}}']);
        $this->assertSame(
            [$element => get_string('erroruniquevalues', 'core_customfield')],
            $this->validate($required, (int)$course->id, [$element => '{"a":{"title":"x","extra":"y"}}'])
        );
        // Control: the other course may keep its own value.
        $this->assertSame([], $this->validate($required, (int)$other->id, [$element => '{"@@ID#1":{"title":"x"}}']));
    }

    /**
     * Deleting a course deletes its value's projection rows; another course keeps its own.
     */
    public function test_course_deletion_removes_its_rows(): void {
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $this->save((int)$course->id, $this->payload([['name' => 'Ana'], ['name' => 'Bia']]));
        $this->save((int)$other->id, $this->payload([['name' => 'Caio']]));

        delete_course($course, false);

        $this->assertSame([], $this->rows((int)$course->id));
        $this->assertCount(1, $this->rows((int)$other->id));
    }

    /**
     * The form shows the stored value, and the widget's module is required when the form is RENDERED.
     *
     * An instance form can be a dynamic form, defined first and rendered inside
     * start_collecting_javascript_requirements(), which swaps the requirements manager: a call
     * made while defining would be lost.
     */
    public function test_form_round_trip_and_widget_module(): void {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php');

        $course = $this->getDataGenerator()->create_course();
        $this->save((int)$course->id, $this->payload([['name' => 'Ana <b>', 'role' => 'Tutor']]));

        $form = new class (null, ['courseid' => (int)$course->id]) extends \moodleform {
            /**
             * The course's custom fields, as the course form adds them.
             *
             * @return void
             */
            protected function definition() {
                $handler = course_handler::create();
                $handler->instance_form_definition($this->_form, $this->_customdata['courseid']);
                $instance = (object)['id' => $this->_customdata['courseid']];
                $handler->instance_form_before_set_data($instance);
                $this->set_data($instance);
            }

            /**
             * The course form's after-data hook.
             *
             * @return void
             */
            public function definition_after_data() {
                course_handler::create()->instance_form_definition_after_data($this->_form, $this->_customdata['courseid']);
            }
        };

        $PAGE->set_url('/');
        $PAGE->start_collecting_javascript_requirements();
        $html = $form->render();
        $js = $PAGE->requires->get_end_code();

        $this->assertStringContainsString('local_repeatable/repeatable', $js);
        $this->assertStringContainsString('customfield-repeatable-' . $this->field->get('id') . '-' . $course->id, $js);
        $this->assertSame(1, preg_match('~<input[^>]*name="' . $this->elementname . '"[^>]*>~', $html, $input));
        $this->assertStringContainsString('id="id_' . $this->elementname . '"', $input[0]);
        $this->assertStringContainsString(s('{"@@ID#1":{"name":"Ana <b>","role":"Tutor","city":"","notes":""}}'), $input[0]);
        $this->assertStringContainsString('aria-describedby="fgroup_id_error_' . $this->elementname . '"', $html);
    }
}
