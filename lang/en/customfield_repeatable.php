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
 * Strings for component 'customfield_repeatable'.
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['columns'] = 'Columns';
$string['columns_help'] =
    'The columns each set has, as a JSON schema: {"version": 2, "columns": [{"key": "language", "label": "Language", "mode": "dictionary", "domain": "iso639", "title": true}, ...]}. With JavaScript the column editor writes it. Modes: free, dictionary (codes of a local_dictionaries domain only), dictionary_open (any code), pattern (a mask and/or regular expression from customfield_pattern; a "pattern" object with preset, mask, regex, regexerror and storeformat). Exactly one column is the title; up to 3 are shown in the subline. A column whose key holds stored values can be neither removed nor re-keyed; rename its label instead.';
$string['pluginname'] = 'Repeatable sets';
$string['privacy:metadata:customfield_repeatable_set'] =
    'A copy of each set of a repeatable custom field value, kept for reports. The value itself is stored by core in customfield_data.';
$string['privacy:metadata:customfield_repeatable_set:data'] = 'The values of one set, as JSON';
$string['privacy:metadata:customfield_repeatable_set:dataid'] = 'The customfield_data record the set belongs to';
$string['privacy:metadata:customfield_repeatable_set:fieldid'] = 'The custom field';
$string['privacy:metadata:customfield_repeatable_set:instanceid'] = 'The course, cohort, group or other instance the value belongs to';
$string['privacy:metadata:customfield_repeatable_set:set_id'] = 'The set number';
$string['privacy:metadata:customfield_repeatable_set:timecreated'] = 'The time when the set row was created';
$string['privacy:metadata:customfield_repeatable_set:timemodified'] = 'The time when the set row was last modified';
$string['specificsettings'] = 'Repeatable sets field settings';
$string['summaryline'] = '{$a->title} ({$a->subline})';
$string['taskreprojectsets'] = 'Rebuild repeatable custom field sets for reports';
