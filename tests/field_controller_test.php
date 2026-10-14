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

/**
 * Field configuration of the repeatable custom field: the column editor, the locked keys, deletion.
 *
 * Metadata stays in docblocks: the plugin will also ship for Moodle 4.5, whose moodle-cs cannot
 * read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\field_controller
 * @covers     \customfield_repeatable\local\stored_values
 * @covers     \customfield_repeatable\local\field_schema
 * @covers     \customfield_repeatable\form\schema_textarea
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field_controller_test extends \advanced_testcase {
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
     * Create a field with the columns Name (title) and Role, and give one course a role.
     *
     * @param string $shortname
     * @param int $categoryid 0 for a new category.
     * @return field_controller
     */
    private function field_with_a_role(string $shortname, int $categoryid = 0): field_controller {
        $field = $this->generator()->create_field(
            [['key' => 'name', 'label' => 'Name'], ['key' => 'role', 'label' => 'Role']],
            ['shortname' => $shortname, 'categoryid' => $categoryid]
        );
        $course = $this->getDataGenerator()->create_course();
        course_handler::create()->instance_form_save((object)[
            'id' => $course->id,
            'customfield_' . $shortname => '{"@@ID#1":{"role":"Tutor"}}',
        ]);
        return $field;
    }

    /**
     * Submitted configuration data holding a schema of some columns.
     *
     * @param array $columns
     * @param int $id The id the submission names.
     * @return array
     */
    private function submission(array $columns, int $id): array {
        return ['id' => $id, 'configdata' => ['schema' => $this->generator()->schema_json($columns)]];
    }

    /**
     * The configuration form of a field, as core's dynamic form builds it.
     *
     * @param array $ajaxdata
     * @return array [the form, its MoodleQuickForm]
     */
    private function config_form(array $ajaxdata): array {
        $form = new \core_customfield\field_config_form(null, null, 'post', '', null, true, $ajaxdata);
        $form->set_data_for_dynamic_submission();
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        return [$form, $mform];
    }

    /**
     * The editor gets the keys holding values in the STORED field, and its module starts when the form is rendered.
     *
     * The form is a dynamic form: core renders it inside start_collecting_javascript_requirements(),
     * which swaps the requirements manager, so a module required while defining would be lost.
     */
    public function test_config_form_locks_stored_keys_and_starts_the_editor(): void {
        global $PAGE;

        $field = $this->field_with_a_role('people');
        [$form, $mform] = $this->config_form(['id' => $field->get('id')]);

        $element = $mform->getElement(field_controller::SCHEMA_ELEMENT);
        $this->assertInstanceOf(form\schema_textarea::class, $element);
        $this->assertSame('["role"]', $element->getAttribute('data-lockedkeys'));
        $this->assertSame(1, $element->getAttribute('data-users'));
        $this->assertSame('local-repeatable-schema', $element->getAttribute('data-region'));
        // The stored schema is shown pretty-printed, so it stays editable without JavaScript.
        $this->assertStringContainsString("\n", $mform->getElementValue(field_controller::SCHEMA_ELEMENT));

        $PAGE->set_url('/');
        $PAGE->start_collecting_javascript_requirements();
        $form->render();
        $this->assertStringContainsString('local_repeatable/columneditor', $PAGE->requires->get_end_code());

        // Control: a new field has nothing to lock.
        [, $newform] = $this->config_form(['categoryid' => $field->get('categoryid'), 'type' => 'repeatable']);
        $this->assertSame('[]', $newform->getElement(field_controller::SCHEMA_ELEMENT)->getAttribute('data-lockedkeys'));
    }

    /**
     * A schema that drops a key holding stored values is rejected; keeping it and renaming its label is not.
     */
    public function test_config_form_validation_rejects_dropping_a_key_in_use(): void {
        $field = $this->field_with_a_role('people');

        $this->assertSame(
            [field_controller::SCHEMA_ELEMENT => get_string('errorkeylocked', 'local_repeatable', 'Role')],
            $field->config_form_validation($this->submission([['key' => 'name', 'label' => 'Name']], (int)$field->get('id')))
        );
        $this->assertSame([], $field->config_form_validation($this->submission([
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'role', 'label' => 'Function'],
        ], (int)$field->get('id'))));
        // A key that holds no value may go.
        $this->assertSame([], $field->config_form_validation($this->submission([
            ['key' => 'role', 'label' => 'Role'],
        ], (int)$field->get('id'))));
    }

    /**
     * The locked keys are this field's, whatever id the submission names.
     */
    public function test_config_form_validation_reads_the_stored_field_not_the_submitted_id(): void {
        $locked = $this->field_with_a_role('people');
        $free = $this->generator()->create_field(
            [['key' => 'name', 'label' => 'Name'], ['key' => 'role', 'label' => 'Role']],
            ['shortname' => 'other', 'categoryid' => $locked->get('categoryid')]
        );
        $dropsrole = [['key' => 'name', 'label' => 'Name']];

        // The free field may drop its role, even when the submission names the locked field.
        $this->assertSame([], $free->config_form_validation($this->submission($dropsrole, (int)$locked->get('id'))));
        // The locked field may not, even when the submission names no field or the free one.
        $expected = [field_controller::SCHEMA_ELEMENT => get_string('errorkeylocked', 'local_repeatable', 'Role')];
        $this->assertSame($expected, $locked->config_form_validation($this->submission($dropsrole, 0)));
        $this->assertSame($expected, $locked->config_form_validation($this->submission($dropsrole, (int)$free->get('id'))));
    }

    /**
     * Text that is not a version 2 schema is rejected under the schema element.
     */
    public function test_config_form_validation_rejects_text_that_is_not_a_schema(): void {
        $field = $this->generator()->create_field(['Name']);
        $this->assertSame(
            [field_controller::SCHEMA_ELEMENT => get_string('errorschemainvalid', 'local_repeatable')],
            $field->config_form_validation(['id' => $field->get('id'), 'configdata' => ['schema' => '[1, 2]']])
        );
    }

    /**
     * The configuration form saves the schema in canonical form, as a string inside configdata.
     */
    public function test_config_form_submission_saves_the_canonical_schema(): void {
        $field = $this->generator()->create_field(['Name']);

        $submitdata = (array)$field->to_record();
        $submitdata['configdata'] = $field->get('configdata');
        $submitdata['configdata']['schema'] = json_encode([
            'version' => 2,
            'columns' => [['label' => 'Name', 'key' => 'name', 'title' => true], ['label' => 'Role', 'key' => 'role']],
        ], JSON_PRETTY_PRINT);
        $submitdata = \core_customfield\field_config_form::mock_ajax_submit($submitdata);
        $form = new \core_customfield\field_config_form(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $saved = \core_customfield\field_controller::create((int)$field->get('id'));
        $this->assertSame(
            \local_repeatable\local\schema::from_columns([
                ['key' => 'name', 'label' => 'Name', 'title' => true],
                ['key' => 'role', 'label' => 'Role'],
            ])->to_json(),
            $saved->get_configdata_property('schema')
        );
    }

    /**
     * Deleting a field deletes its projection rows; another field keeps its own.
     *
     * Core deletes {customfield_data} of a field directly and never calls the data controller.
     */
    public function test_field_deletion_removes_its_rows(): void {
        global $DB;

        $deleted = $this->field_with_a_role('people');
        $kept = $this->field_with_a_role('others', (int)$deleted->get('categoryid'));
        $this->assertSame(1, $DB->count_records(source::TABLE, ['fieldid' => $deleted->get('id')]));

        course_handler::create()->delete_field_configuration($deleted);

        $this->assertSame(0, $DB->count_records(source::TABLE, ['fieldid' => $deleted->get('id')]));
        $this->assertSame(1, $DB->count_records(source::TABLE, ['fieldid' => $kept->get('id')]));
    }
}
