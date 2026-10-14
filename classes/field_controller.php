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
use customfield_repeatable\local\stored_values;
use local_repeatable\form\column_editor;
use local_repeatable\local\schema;

/**
 * Field controller of the repeatable custom field: its column schema and its cleanup.
 *
 * The schema is a version 2 document edited through the engine's column editor
 * ({@see column_editor}) and kept as a JSON string under configdata['schema']
 * ({@see field_schema}). Keys holding values in the STORED field are locked: the editor shows them
 * locked and {@see self::config_form_validation()} rejects a schema that drops or re-keys one,
 * whatever the submission says.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {
    /** @var string The name of the schema element in the configuration form. */
    public const SCHEMA_ELEMENT = 'configdata[schema]';

    /**
     * Add the column editor to the configuration form.
     *
     * The textarea carries everything the editor needs in data attributes
     * ({@see column_editor::attributes()}): the keys holding values in this field's stored rows
     * (never the submission), the companion's domains and the pattern presets. It starts the
     * editor's module when rendered ({@see form\schema_textarea}).
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        $mform->addElement('header', 'header_specificsettings', get_string('specificsettings', 'customfield_repeatable'));
        $mform->setExpanded('header_specificsettings', true);

        $fieldid = (int)$this->get('id');
        $usage = stored_values::usage($fieldid, field_schema::stored($fieldid) ?? schema::from_columns([]));
        $element = new form\schema_textarea(
            self::SCHEMA_ELEMENT,
            get_string('columns', 'customfield_repeatable'),
            column_editor::element_attributes() + column_editor::attributes($usage, $this->pattern_available())
        );
        $mform->addElement($element);
        $mform->setType(self::SCHEMA_ELEMENT, PARAM_RAW);
        $mform->addHelpButton(self::SCHEMA_ELEMENT, 'columns', 'customfield_repeatable');
    }

    /**
     * Pretty-print the stored schema for the configuration form, so it stays editable without JavaScript.
     *
     * @param \stdClass $formdata
     * @return void
     */
    public function prepare_for_config_form(\stdClass $formdata) {
        $configdata = is_array($formdata->configdata ?? null) ? $formdata->configdata : [];
        $stored = $configdata[field_schema::CONFIG_KEY] ?? null;
        if (is_string($stored)) {
            $configdata[field_schema::CONFIG_KEY] = column_editor::pretty($stored) ?? $stored;
            $formdata->configdata = $configdata;
        }
    }

    /**
     * Validate the submitted column schema.
     *
     * Every broken rule is listed under the schema element. The keys holding values and the
     * pattern columns are read from THIS field as stored (its id is the one core loaded the
     * controller with), never from the submitted data: a schema that drops or re-keys a key in
     * use is rejected, and without customfield_pattern only the stored pattern columns may be
     * submitted, unchanged.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function config_form_validation(array $data, $files = []): array {
        $configdata = is_array($data['configdata'] ?? null) ? $data['configdata'] : [];
        $document = schema::decode((string)($configdata[field_schema::CONFIG_KEY] ?? ''));

        $fieldid = (int)$this->get('id');
        $lockedlabels = [];
        $storedpatterns = [];
        $stored = field_schema::stored($fieldid);
        if ($stored !== null) {
            $lockedlabels = stored_values::locked_labels($fieldid);
            $storedpatterns = $stored->pattern_objects();
        }

        $errors = column_editor::errors($document, $lockedlabels, $storedpatterns, $this->pattern_available());
        if (empty($errors)) {
            return [];
        }
        return [self::SCHEMA_ELEMENT => implode('<br>', $errors)];
    }

    /**
     * Save the field with its schema in canonical form.
     *
     * @return void
     */
    public function save() {
        $configdata = $this->get('configdata');
        $submitted = $configdata[field_schema::CONFIG_KEY] ?? null;
        if (is_string($submitted)) {
            $canonical = column_editor::canonical($submitted);
            if ($canonical !== null) {
                $configdata[field_schema::CONFIG_KEY] = $canonical;
                $this->set('configdata', json_encode($configdata));
            }
        }
        parent::save();
    }

    /**
     * Delete the field, its data and its projection rows.
     *
     * Core deletes {customfield_data} of the field directly here and never calls
     * data_controller::delete() (core_customfield\field_controller::delete()), so the projection
     * rows of the field go first, by field id.
     *
     * @return bool
     */
    public function delete(): bool {
        global $DB;

        $DB->delete_records(source::TABLE, ['fieldid' => (int)$this->get('id')]);
        return parent::delete();
    }

    /**
     * Whether pattern columns can be configured: customfield_pattern offers its API.
     *
     * The one seam between the definition and {@see \local_repeatable\local\bridge\pattern::available()},
     * so tests can run it as if the plugin were absent.
     *
     * @return bool
     */
    protected function pattern_available(): bool {
        return \local_repeatable\local\bridge\pattern::available();
    }
}
