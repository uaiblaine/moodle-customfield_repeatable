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

use customfield_repeatable\local\field_schema;
use customfield_repeatable\local\source;
use customfield_repeatable\local\summary;
use local_repeatable\local\payload;
use local_repeatable\local\projection\projector;
use local_repeatable\local\schema;

/**
 * Data controller of the repeatable custom field: one instance's sets.
 *
 * The canonical value is one JSON object in {customfield_data}.value, keyed by column key
 * ({@see schema}), e.g. {"@@ID#1": {"language": "pt", "level": "B2"}}; the per-set rows in
 * {customfield_repeatable_set} are a projection for Report Builder
 * ({@see \local_repeatable\local\projection\projector}). Every read decodes the value: a course
 * restore writes it without calling this class.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_controller extends \core_customfield\data_controller {
    /** @var schema|null Column schema, read once from the field. */
    protected ?schema $schema = null;

    /**
     * The {customfield_data} column holding the payload: 'value', the text column with no length limit.
     *
     * @return string
     */
    public function datafield(): string {
        return 'value';
    }

    /**
     * No default: an instance without data has no set.
     *
     * Null also keeps core's Report Builder filter from wrapping the column in COALESCE with a
     * default (core_reportbuilder\local\helpers\custom_fields::get_filters()).
     *
     * @return null
     */
    public function get_default_value() {
        return null;
    }

    /**
     * Add the engine widget and the hidden input carrying the value to the instance form.
     *
     * The value travels in a hidden input that the AMD module reads on load and rewrites from the
     * widget on submit. QuickForm prints no error for a hidden element, so it sits in a group named
     * after the element: a validation error keyed by that name renders under the widget, which
     * points at it with aria-describedby.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {
        $elementname = $this->get_form_element_name();
        $widget = $this->get_widget();
        $ids = $this->widget_ids();

        $mform->addElement(new form\widget_html(
            $widget->render((string)$this->get_field()->get('name'), $ids),
            $widget,
            $ids
        ));
        $mform->addGroup(
            [$mform->createElement('hidden', $elementname, '', ['id' => $ids['hiddenid']])],
            $elementname,
            '',
            '',
            false
        );
        $mform->setType($elementname, PARAM_RAW_TRIMMED);
    }

    /**
     * Put the stored payload in the form, pattern values in their displayed (masked) shape.
     *
     * @param \stdClass $instance
     * @return void
     */
    public function instance_form_before_set_data(\stdClass $instance) {
        $payload = payload::normalise((string)($this->get_value() ?? ''), $this->get_schema());
        $instance->{$this->get_form_element_name()} = payload::encode($this->get_validator('')->for_editing($payload));
    }

    /**
     * Hand the widget the labels of the codes the form holds.
     *
     * The value is the stored one, or the submitted one after a failed validation; in a dictionary
     * the editing user cannot read, only the codes STORED for this instance get a label
     * ({@see \local_repeatable\form\widget::labels()}). The labels ride on the hidden input,
     * because js_call_amd() warns past 1024 characters.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function instance_form_definition_after_data(\MoodleQuickForm $mform) {
        $elementname = $this->get_form_element_name();
        if (!$mform->elementExists($elementname)) {
            return;
        }
        $group = $mform->getElement($elementname);
        if (!$group instanceof \MoodleQuickForm_group) {
            return;
        }
        $stored = $this->get_validator($this->stored_payload())->stored_codes();
        foreach ($group->getElements() as $element) {
            if ($element->getName() === $elementname) {
                $labels = $this->get_widget()->labels((string)$element->getValue(), $stored);
                $element->updateAttributes([
                    'data-labels' => json_encode((object)$labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ]);
            }
        }
    }

    /**
     * Validate the submitted payload.
     *
     * The engine's validator decides with the payload STORED for this field and instance
     * ({@see self::stored_payload()}), never the submission: a codes-only column accepts a code
     * the dictionary no longer offers only when that code is already stored, and in a dictionary
     * the editing user cannot read every other code gets one uniform error. Core calls this only
     * for the fields the user may edit (core_customfield\handler::instance_form_validation()).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        $elementname = $this->get_form_element_name();
        if (!array_key_exists($elementname, $data)) {
            return [];
        }
        $payload = [];
        $error = $this->submitted_error($data[$elementname], $payload);
        if ($error !== null) {
            return [$elementname => $error];
        }

        // The parent's uniqueness check compares this value with the stored ones: give it the JSON
        // as it would be stored, or '' so that an empty value is not checked.
        $payload = $this->get_validator('')->for_storage($payload);
        $data[$elementname] = empty($payload) ? '' : payload::encode($payload);
        return parent::instance_form_validation($data, $files);
    }

    /**
     * Save the submitted payload and its projection rows in one delegated transaction.
     *
     * Every save path (the form, web services, the course upload tool, the group and cohort APIs)
     * runs the form's checks first ({@see self::submitted_error()}), with the stored payload and the
     * current user's dictionary rights; a submission that fails them is refused with one uniform
     * message that names the field and nothing the user submitted. Then the payload is normalised
     * and its pattern values put in their stored shape. An empty payload is stored as '', so core's
     * "is empty" filter and export see no value. Core's restore writes without this class.
     *
     * @param \stdClass $datanew
     * @return void
     * @throws \moodle_exception When the submitted value fails the field's checks.
     */
    public function instance_form_save(\stdClass $datanew) {
        global $DB;

        $elementname = $this->get_form_element_name();
        if (!property_exists($datanew, $elementname)) {
            return;
        }
        // Core's web services and group and cohort APIs save without validating the form: the same
        // checks run here, so no path stores a code the editing user could not have submitted.
        $payload = [];
        if ($this->submitted_error($datanew->{$elementname}, $payload) !== null) {
            throw new \moodle_exception('errorsaverejected', 'customfield_repeatable', '', format_string(
                (string)$this->get_field()->get('name'),
                true,
                ['context' => \context_system::instance()]
            ));
        }
        $payload = $this->get_validator('')->for_storage($payload);

        $submitted = clone $datanew;
        $submitted->{$elementname} = empty($payload) ? '' : payload::encode($payload);

        $transaction = $DB->start_delegated_transaction();
        $this->set('valueformat', FORMAT_PLAIN);
        $this->set('valuetrust', 0);
        parent::instance_form_save($submitted);
        projector::sync(
            new source(),
            (int)$this->get('id'),
            (int)$this->get_field()->get('id'),
            (int)$this->get('instanceid'),
            $payload
        );
        $transaction->allow_commit();
    }

    /**
     * Delete the instance's value and its projection rows.
     *
     * Core calls this from core_customfield\handler::delete_instance() (course and cohort deletion,
     * the question bank's observer).
     *
     * @return bool
     */
    public function delete() {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $dataid = (int)$this->get('id');
        if ($dataid > 0) {
            $DB->delete_records(source::TABLE, ['dataid' => $dataid]);
        }
        $result = parent::delete();
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Whether a value holds no set.
     *
     * Core's own test only compares the text with '', which would count '{}' as filled.
     *
     * @param mixed $value
     * @return bool
     */
    protected function is_empty($value): bool {
        return empty(payload::normalise($value, $this->get_schema()));
    }

    /**
     * The value as core shows it: the compact escaped summary, one line per set (owner decision D4).
     *
     * Core prints it unescaped in course listings, its Report Builder column and web services, so
     * it is built by {@see summary::html()}, never by format_text() over the raw JSON.
     *
     * @return string|null Null when the instance has no set.
     */
    public function export_value() {
        $value = $this->get_value();
        if ($this->is_empty($value)) {
            return null;
        }
        return summary::html($this->get_schema(), (string)$value);
    }

    /**
     * The sets in the engine's plain list.
     *
     * @return string HTML; '' when the instance has no set.
     */
    public function display(): string {
        $dataid = (int)$this->get('id');
        $renderer = new \local_repeatable\output\sets_renderer(
            $this->get_schema(),
            (string)($this->get_value() ?? ''),
            (string)$this->get_field()->get('name'),
            'customfield-repeatable-display-' . (int)$this->get_field()->get('id') . '-' . (int)$this->get('instanceid'),
            fn(): array => (new source())->projection_rows_of_record($dataid)
        );
        return $renderer->render(\local_repeatable\output\sets_renderer::LAYOUT_PLAIN);
    }

    /**
     * Rebuild this record's projection after a course restore wrote its value.
     *
     * core_course\customfield\course_handler::restore_instance_data_from_backup() writes the value
     * directly; core then calls this on every data controller of the instance the restoring user
     * may see (core_customfield\handler::restore_define_structure()), so only the record restored
     * now is rebuilt. Fields the user may not see, and groups and groupings, are rebuilt by the
     * course_restored observer ({@see observer::course_restored()}).
     *
     * @param \restore_structure_step $step
     * @param int $newid The restored {customfield_data} id.
     * @param int $oldid The id in the backup.
     * @return void
     */
    public function restore_define_structure(\restore_structure_step $step, int $newid, int $oldid): void {
        if ((int)$this->get('id') !== $newid) {
            return;
        }
        $schema = field_schema::parse($this->get_field()->get('configdata'));
        if ($schema !== null) {
            projector::reproject_record(new source(), $newid, $schema);
        }
    }

    /**
     * The first error of a submitted value, as the form validation reports it.
     *
     * The one place the engine validator is built for a submission, shared by the form
     * ({@see self::instance_form_validation()}) and every other save path
     * ({@see self::instance_form_save()}), so they cannot drift: the payload STORED for this field and
     * instance, the current user's dictionary rights, and the field's required flag.
     *
     * @param mixed $raw The submitted value.
     * @param array $payload Set to the normalised payload.
     * @return string|null The error text, null when the value passes.
     */
    private function submitted_error($raw, array &$payload): ?string {
        $validator = $this->get_validator($this->stored_payload());
        if (is_string($raw) || $raw === null) {
            $rawerror = $validator->raw_error((string)$raw);
            if ($rawerror !== null) {
                return $rawerror;
            }
        }
        $payload = payload::normalise($raw, $this->get_schema());
        return $validator->error($payload, (bool)$this->get_field()->get_configdata_property('required'));
    }

    /**
     * The payload stored for this field and instance, as recorded.
     *
     * Read from {customfield_data} by this controller's id, and only when that row belongs to this
     * field and this instance: the course upload tool builds a controller with a made-up id, and
     * nothing a caller set on the controller counts as stored.
     *
     * @return string Raw JSON; '' when nothing is stored.
     */
    protected function stored_payload(): string {
        global $DB;

        $dataid = (int)$this->get('id');
        if ($dataid <= 0) {
            return '';
        }
        $value = $DB->get_field('customfield_data', 'value', [
            'id' => $dataid,
            'fieldid' => (int)$this->get_field()->get('id'),
            'instanceid' => (int)$this->get('instanceid'),
        ]);
        return $value === false ? '' : (string)$value;
    }

    /**
     * Whether customfield_pattern offers the API pattern columns need.
     *
     * The one seam between this field and {@see \local_repeatable\local\bridge\pattern::available()},
     * so tests can run the field as if the plugin were absent.
     *
     * @return bool
     */
    protected function pattern_available(): bool {
        return \local_repeatable\local\bridge\pattern::available();
    }

    /**
     * Whether the companion offers the API dictionary columns need on the edit form.
     *
     * The one seam between this field and {@see \local_repeatable\local\bridge\dictionaries::lookup_available()},
     * so tests can run the field as if the companion were absent.
     *
     * @return bool
     */
    protected function companion_available(): bool {
        return \local_repeatable\local\bridge\dictionaries::lookup_available();
    }

    /**
     * Whether the current (editing) user may search a domain, as the companion's search service decides.
     *
     * Every code check and label that could tell a user something about a dictionary they cannot
     * read is decided on this: the engine's validator and widget get it as their "can read" callable.
     *
     * @param string $domain
     * @return bool
     */
    protected function can_read_domain(string $domain): bool {
        return $this->companion_available() && \local_dictionaries\api::can_read($domain);
    }

    /**
     * The field's column schema.
     *
     * @return schema
     */
    protected function get_schema(): schema {
        if ($this->schema === null) {
            $this->schema = field_schema::from_field($this->get_field());
        }
        return $this->schema;
    }

    /**
     * The engine validator of this field, deciding with what the custom field adapter knows.
     *
     * @param string $storedpayload The payload stored for this instance ({@see self::stored_payload()}),
     *                              '' where only the pattern shaping is used.
     * @return \local_repeatable\local\validator
     */
    protected function get_validator(string $storedpayload): \local_repeatable\local\validator {
        return new \local_repeatable\local\validator(
            $this->get_schema(),
            $storedpayload,
            fn(string $domain): bool => $this->can_read_domain($domain),
            $this->companion_available(),
            $this->pattern_available()
        );
    }

    /**
     * The engine widget of this field, deciding with what the custom field adapter knows.
     *
     * @return \local_repeatable\form\widget
     */
    protected function get_widget(): \local_repeatable\form\widget {
        return new \local_repeatable\form\widget(
            $this->get_schema(),
            fn(string $domain): bool => $this->can_read_domain($domain),
            $this->companion_available(),
            $this->pattern_available()
        );
    }

    /**
     * The ids the widget needs: its wrapper, the hidden input and the error region of its group.
     *
     * The wrapper id carries the field and instance ids, so several repeatable fields share one
     * form. The error id mirrors core's: a group's region is "fgroup_id_error_<name>"
     * ({@see \templatable_form_element}).
     *
     * @return array
     */
    protected function widget_ids(): array {
        $elementname = $this->get_form_element_name();
        return [
            'wrapperid' => 'customfield-repeatable-' . (int)$this->get_field()->get('id') . '-' . (int)$this->get('instanceid'),
            'hiddenid' => 'id_' . $elementname,
            'errorid' => 'fgroup_id_error_' . $elementname,
        ];
    }
}
