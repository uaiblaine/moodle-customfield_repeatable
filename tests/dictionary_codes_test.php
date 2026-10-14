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
use customfield_repeatable\local\summary;
use local_repeatable\local\bridge\dictionaries;

/**
 * Dictionary columns of a course field: the codes a teacher may submit, and the labels they see.
 *
 * The engine's validator decides with the payload STORED for the course and the editing user's
 * right to read each dictionary; these tests hold the adapter to handing it those two facts.
 * Every test needs the companion's lookup API and skips without it. Metadata stays in docblocks:
 * the plugin will also ship for Moodle 4.5, whose moodle-cs cannot read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\data_controller
 * @covers     \customfield_repeatable\local\summary
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dictionary_codes_test extends \advanced_testcase {
    /** @var field_controller The test field. */
    private field_controller $field;

    /** @var string The field's form element name. */
    private string $elementname = 'customfield_places';

    /**
     * Skip without the companion's lookup API, then create the dictionaries and the field.
     *
     * 'langs' (open to logged-in users): pt, es, and xx (inactive). 'uf' (open): SP, RJ.
     * 'cities' (restricted to local/dictionaries:view, which a teacher lacks), whose items have
     * their state in 'uf' as parent: 3550308 (SP), 3304557 (RJ).
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
        $generator->create_domain(['shortname' => 'langs', 'visibility' => 1]);
        $generator->create_item(['domain' => 'langs', 'code' => 'pt', 'label' => 'Portuguese']);
        $generator->create_item(['domain' => 'langs', 'code' => 'es', 'label' => 'Spanish']);
        $generator->create_item(['domain' => 'langs', 'code' => 'xx', 'label' => 'Retired', 'active' => 0]);
        $generator->create_domain(['shortname' => 'uf', 'visibility' => 1]);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'São Paulo']);
        $generator->create_item(['domain' => 'uf', 'code' => 'RJ', 'label' => 'Rio de Janeiro']);
        $generator->create_domain(['shortname' => 'cities', 'visibility' => 0]);
        $generator->create_item(['domain' => 'cities', 'code' => '3550308', 'label' => 'São Paulo', 'parentcode' => 'SP',
            'parentdomain' => 'uf']);
        $generator->create_item(['domain' => 'cities', 'code' => '3304557', 'label' => 'Rio', 'parentcode' => 'RJ',
            'parentdomain' => 'uf']);

        $this->setAdminUser();
        /** @var \customfield_repeatable_generator $fieldgenerator */
        $fieldgenerator = $this->getDataGenerator()->get_plugin_generator('customfield_repeatable');
        $this->field = $fieldgenerator->create_field([
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs', 'title' => true],
            ['key' => 'state', 'label' => 'State', 'mode' => 'dictionary', 'domain' => 'uf', 'subline' => true],
            ['key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'domain' => 'cities', 'parent' => 'state',
                'subline' => true],
        ], ['shortname' => 'places']);
    }

    /**
     * A course with a stored payload of one set (written without the save checks), and an editing teacher in it.
     *
     * @param array $set key => code.
     * @return array [course id, teacher]
     */
    private function course_with(array $set): array {
        $course = $this->getDataGenerator()->create_course();
        // Seeded below the type, as an older record: the save path now refuses what the form would
        // (an inactive code, a column whose parent is empty), and these tests need such stored values.
        $data = course_handler::create()->get_instance_data((int)$course->id, true)[(int)$this->field->get('id')];
        $data->set('value', json_encode(['@@ID#1' => $set]));
        $data->set('contextid', \context_course::instance($course->id)->id);
        $data->save();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        return [(int)$course->id, $teacher];
    }

    /**
     * Validate sets for a course, as the course form's handler does for this field.
     *
     * @param int $courseid
     * @param array $sets List of sets: key => code.
     * @return array Errors.
     */
    private function validate(int $courseid, array $sets): array {
        $payload = [];
        foreach (array_values($sets) as $index => $set) {
            $payload['@@ID#' . ($index + 1)] = $set;
        }
        // The field's own controller, as core_customfield\handler::instance_form_validation() calls it
        // for an editable field: other plugins on the site may add course fields this submission does not name.
        $fieldid = (int)$this->field->get('id');
        $editable = course_handler::create()->get_editable_fields($courseid);
        $this->assertArrayHasKey($fieldid, $editable);
        $data = course_handler::create()->get_instance_fields_data([$fieldid => $editable[$fieldid]], $courseid)[$fieldid];
        return $data->instance_form_validation(['id' => $courseid, $this->elementname => json_encode($payload)], []);
    }

    /**
     * The expected error of one code.
     *
     * @param string $identifier
     * @param int $set
     * @param string $column
     * @param string $code
     * @return array
     */
    private function code_error(string $identifier, int $set, string $column, string $code): array {
        return [$this->elementname => get_string($identifier, 'local_repeatable', (object)[
            'set' => $set,
            'column' => $column,
            'code' => $code,
        ])];
    }

    /**
     * A deactivated code the course already has stored stays accepted; only there.
     */
    public function test_stored_inactive_code_is_kept(): void {
        [$courseid, $teacher] = $this->course_with(['lang' => 'XX']);
        [$othercourseid] = $this->course_with(['lang' => 'pt']);
        $this->setUser($teacher);

        $this->assertSame([], $this->validate($courseid, [['lang' => 'xx'], ['lang' => 'pt']]));
        // Control: the stored code does not open the column to other inactive or unknown codes.
        $this->assertSame($this->code_error('errorcodeunknown', 2, 'Language', 'zz'), $this->validate($courseid, [
            ['lang' => 'xx'],
            ['lang' => 'zz'],
        ]));
        // Control: another course, which does not hold it, is refused the same code.
        $this->setAdminUser();
        $this->assertSame($this->code_error('errorcodeinactive', 1, 'Language', 'xx'), $this->validate($othercourseid, [
            ['lang' => 'xx'],
        ]));
    }

    /**
     * A teacher who may not read a dictionary cannot tell its codes apart: every code not stored gets one message.
     */
    public function test_restricted_domain_refuses_every_unstored_code_alike(): void {
        [$courseid, $teacher] = $this->course_with(['state' => 'SP', 'city' => '3550308']);
        $this->setUser($teacher);

        $expected = $this->code_error('errorcoderestricted', 1, 'City', '');
        $this->assertSame($expected, $this->validate($courseid, [['state' => 'RJ', 'city' => '3304557']]), 'An existing code');
        $this->assertSame($expected, $this->validate($courseid, [['state' => 'RJ', 'city' => 'nope']]), 'An unknown code');
        // The stored code stays, and no parent check reaches the teacher: RJ is not its state.
        $this->assertSame([], $this->validate($courseid, [['state' => 'RJ', 'city' => '3550308']]));

        // Control: the admin gets the dictionary's answers.
        $this->setAdminUser();
        $this->assertSame(
            $this->code_error('errorcodeunknown', 1, 'City', 'nope'),
            $this->validate($courseid, [['state' => 'RJ', 'city' => 'nope']])
        );
    }

    /**
     * The stored payload is the one recorded for THIS field and instance, never one a caller pointed at.
     *
     * The course upload tool builds a data controller with a made-up id: a controller whose id
     * names another course's record must not borrow that record's codes.
     */
    public function test_stored_payload_is_this_instances(): void {
        [$holderid] = $this->course_with(['city' => '3304557']);
        [$courseid, $teacher] = $this->course_with(['city' => '3550308']);
        $this->setUser($teacher);

        $holder = course_handler::create()->get_instance_data($holderid, true)[$this->field->get('id')];
        $controller = \core_customfield\data_controller::create(0, (object)['instanceid' => $courseid], $this->field);
        $controller->set('id', (int)$holder->get('id'));

        $this->assertSame(
            $this->code_error('errorcoderestricted', 1, 'City', ''),
            $controller->instance_form_validation([$this->elementname => json_encode(['@@ID#1' => ['city' => '3304557']])], [])
        );
        // Control: the course's own record exempts its own code.
        $this->assertSame([], $this->validate($courseid, [['city' => '3550308']]));
    }

    /**
     * The form preloads labels only for codes the teacher may read, or that the course has stored.
     */
    public function test_form_labels_only_readable_or_stored_codes(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        [$courseid, $teacher] = $this->course_with(['state' => 'SP', 'city' => '3550308']);
        $this->setUser($teacher);

        $submitted = json_encode([
            '@@ID#1' => ['state' => 'SP', 'city' => '3550308'],
            '@@ID#2' => ['state' => 'RJ', 'city' => '3304557'],
        ]);
        $this->assertSame([
            'uf' => ['SP' => 'São Paulo', 'RJ' => 'Rio de Janeiro'],
            'cities' => ['3550308' => 'São Paulo'],
        ], $this->form_labels($courseid, $submitted));

        // Control: the admin, who may read the dictionary, gets every label.
        $this->setAdminUser();
        $this->assertSame(['3550308' => 'São Paulo', '3304557' => 'Rio'], $this->form_labels($courseid, $submitted)['cities']);
    }

    /**
     * The summary shows each code's label.
     */
    public function test_export_value_shows_labels(): void {
        [$courseid] = $this->course_with(['lang' => 'pt', 'state' => 'SP', 'city' => 'unknown']);
        $data = course_handler::create()->get_instance_data($courseid, true)[$this->field->get('id')];
        $this->assertSame('Portuguese (São Paulo, unknown)', $data->export_value());
    }

    /**
     * The data-labels of the hidden input after the course form is submitted with a payload.
     *
     * @param int $courseid
     * @param string $submitted The submitted payload.
     * @return array
     */
    private function form_labels(int $courseid, string $submitted): array {
        global $PAGE;
        $PAGE->set_url('/course/edit.php', ['id' => $courseid]);
        $form = new class (null, ['courseid' => $courseid, 'submitted' => $submitted]) extends \moodleform {
            /**
             * The course's custom fields, with the payload as submitted.
             *
             * @return void
             */
            protected function definition() {
                course_handler::create()->instance_form_definition($this->_form, $this->_customdata['courseid']);
            }

            /**
             * The course form's after-data hook, after the submitted value is set.
             *
             * @return void
             */
            public function definition_after_data() {
                $this->_form->setDefaults(['customfield_places' => $this->_customdata['submitted']]);
                course_handler::create()->instance_form_definition_after_data($this->_form, $this->_customdata['courseid']);
            }
        };
        $html = $form->render();
        $this->assertSame(1, preg_match('~<input[^>]*name="' . $this->elementname . '"[^>]*>~', $html, $input));
        $this->assertSame(1, preg_match('~data-labels="([^"]*)"~', $input[0], $matches));
        return json_decode(html_entity_decode($matches[1]), true);
    }
}
