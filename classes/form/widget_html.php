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
 * The edit widget's HTML in an instance form.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_repeatable\form;

use local_repeatable\form\widget;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pear/HTML/QuickForm/html.php');

/**
 * The engine widget's table, which starts the widget's module when it is rendered.
 *
 * An instance form can be a dynamic form in a modal (the report area's form on Moodle 5.1+):
 * core_form\external\dynamic_form::execute() defines it, may run definition_after_data() while
 * validating, and renders it inside $PAGE->start_collecting_javascript_requirements(). Only the
 * rendering is sure to reach the requirements manager whose code is sent to the page, so the
 * js_call_amd() happens when the renderer visits the element, on a plain page as in a modal.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widget_html extends \HTML_QuickForm_html {
    /** @var widget The engine widget of the field. */
    private widget $widget;

    /** @var array The ids the widget's module needs ('wrapperid', 'hiddenid'). */
    private array $ids;

    /**
     * Constructor.
     *
     * @param string $html The widget's HTML ({@see widget::render()}).
     * @param widget $widget The engine widget that rendered it.
     * @param array $ids The ids handed to {@see widget::render()}.
     */
    public function __construct(string $html, widget $widget, array $ids) {
        parent::__construct($html);
        $this->widget = $widget;
        $this->ids = $ids;
    }

    /**
     * Hand the widget's HTML to the form renderer, starting its module on the page that receives it.
     *
     * Every renderer prints an html element through accept() (HTML_QuickForm_Renderer::renderHtml()),
     * once per rendering of the form. The value is never read-only here: core's handlers add only
     * the fields the user may edit (core_customfield\handler::get_editable_fields()).
     *
     * @param \HTML_QuickForm_Renderer $renderer
     * @param bool $required
     * @param string|null $error
     * @return void
     */
    public function accept(&$renderer, $required = false, $error = null) {
        $this->widget->init_js($this->ids, false);
        parent::accept($renderer, $required, $error);
    }
}
