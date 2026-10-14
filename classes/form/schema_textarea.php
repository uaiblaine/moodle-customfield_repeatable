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

/**
 * The schema textarea of the field configuration form.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_repeatable\form;

use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/textarea.php');

/**
 * The schema textarea, which asks for the engine's column editor when it is rendered.
 *
 * The configuration form is a dynamic form shown in a modal: core_form\external\dynamic_form::execute()
 * builds (defines) it first and renders it afterwards inside
 * $PAGE->start_collecting_javascript_requirements(), which swaps in a fresh requirements manager.
 * A js_call_amd() made in config_form_definition() goes to the discarded manager and the column
 * editor never starts. Rendering is the one moment both the modal and a plain page share.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema_textarea extends \MoodleQuickForm_textarea {
    /**
     * Export for the form templates, asking for the column editor's module first.
     *
     * @param renderer_base $output Renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output) {
        \local_repeatable\form\column_editor::init_js();
        return parent::export_for_template($output);
    }
}
