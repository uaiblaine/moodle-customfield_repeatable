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

namespace customfield_repeatable\task;

use customfield_repeatable\local\source;

/**
 * The daily task rebuilds stale projections and deletes the rows whose record is gone.
 *
 * Metadata stays in docblocks: the plugin will also ship for Moodle 4.5, whose moodle-cs cannot
 * read PHPUnit attributes.
 *
 * @package    customfield_repeatable
 * @covers     \customfield_repeatable\task\reproject_sets
 * @covers     \customfield_repeatable\local\projection
 * @covers     \customfield_repeatable\local\source
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reproject_sets_test extends \advanced_testcase {
    /**
     * A value written behind the type is rebuilt; a current one is left alone; orphan rows go.
     */
    public function test_execute_rebuilds_stale_projections_and_purges_orphans(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \customfield_repeatable_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('customfield_repeatable');
        $field = $generator->create_field(['Name'], ['shortname' => 'people']);
        $stale = $this->getDataGenerator()->create_course(['customfield_people' => '{"@@ID#1":{"name":"Ana"}}']);
        $current = $this->getDataGenerator()->create_course(['customfield_people' => '{"@@ID#1":{"name":"Bia"}}']);
        $DB->set_field('customfield_data', 'value', '{"@@ID#1":{"name":"Ana"},"@@ID#2":{"name":"Caio"}}', [
            'fieldid' => $field->get('id'),
            'instanceid' => $stale->id,
        ]);
        $currentrow = $DB->get_record(source::TABLE, ['instanceid' => $current->id]);
        $DB->set_field(source::TABLE, 'timemodified', 1000, ['id' => $currentrow->id]);

        // Rows of a record core deleted behind the type, and of a record of another type.
        $text = $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_field([
            'categoryid' => $field->get('categoryid'),
            'shortname' => 'plain',
            'type' => 'text',
        ]);
        $textdata = $this->getDataGenerator()->get_plugin_generator('core_customfield')->add_instance_data(
            $text,
            (int)$current->id,
            'plain'
        );
        $orphan = ['fieldid' => $field->get('id'), 'instanceid' => 999, 'set_id' => 1, 'data' => '{"name":"Gone"}',
            'timecreated' => 1, 'timemodified' => 1];
        $DB->insert_record(source::TABLE, $orphan + ['dataid' => 123456789]);
        $DB->insert_record(source::TABLE, $orphan + ['dataid' => (int)$textdata->get('id')]);

        ob_start();
        (new reproject_sets())->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('rebuilt 1 projection(s) of field people', $output);
        $this->assertStringContainsString('deleted 2 orphaned projection row(s)', $output);
        $names = $DB->get_fieldset_select(source::TABLE, 'data', 'instanceid = ? ORDER BY set_id', [$stale->id]);
        $this->assertSame(['{"name":"Ana"}', '{"name":"Caio"}'], $names);
        $this->assertEquals(1000, $DB->get_field(source::TABLE, 'timemodified', ['id' => $currentrow->id]));
        $this->assertSame(0, $DB->count_records(source::TABLE, ['instanceid' => 999]));
        $this->assertSame(3, $DB->count_records(source::TABLE));

        // The engine reads only records of repeatable fields through the source.
        $this->assertNull((new source())->canonical((int)$textdata->get('id')));
        $record = (new source())->canonical((int)$DB->get_field('customfield_data', 'id', ['instanceid' => $current->id,
            'fieldid' => $field->get('id')]));
        $this->assertSame([(int)$field->get('id'), (int)$current->id], [$record->fieldid, $record->ownerid]);
    }
}
