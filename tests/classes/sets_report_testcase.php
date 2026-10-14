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

namespace customfield_repeatable\tests;

use core_customfield\field_controller;
use core_customfield\handler;
use core_customfield\shared;
use core_reportbuilder\local\helpers\report;
use core_reportbuilder\local\models\report as report_model;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;

/**
 * Shared fixtures of the one-row-per-set datasource tests: fields per area, saved values, report content.
 *
 * @package    customfield_repeatable
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class sets_report_testcase extends core_reportbuilder_testcase {
    /**
     * The plugin's generator.
     *
     * @return \customfield_repeatable_generator
     */
    protected function generator(): \customfield_repeatable_generator {
        return $this->getDataGenerator()->get_plugin_generator('customfield_repeatable');
    }

    /**
     * A repeatable field in a new category of one area.
     *
     * @param string $component The area's component (core_course, core_cohort, core_group).
     * @param string $area The area (course, cohort, group, grouping).
     * @param string $shortname The field's shortname.
     * @param array $columns The field's columns ({@see \customfield_repeatable_generator::schema_json()}).
     * @param array $configdata Other configdata (visibility...).
     * @return field_controller
     */
    protected function area_field(
        string $component,
        string $area,
        string $shortname,
        array $columns = ['Name', 'Role'],
        array $configdata = []
    ): field_controller {
        $category = $this->getDataGenerator()->create_custom_field_category(['component' => $component, 'area' => $area]);
        return $this->generator()->create_field($columns, [
            'categoryid' => $category->get('id'),
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
            'configdata' => $configdata,
        ]);
    }

    /**
     * A repeatable field in a shared category (Moodle 5.1+) enabled for some areas.
     *
     * @param string $shortname The field's shortname.
     * @param array $areas Pairs [component, area] the category is enabled for.
     * @return field_controller
     */
    protected function shared_field(string $shortname, array $areas): field_controller {
        $category = $this->getDataGenerator()->create_custom_field_category([
            'component' => 'core_customfield',
            'area' => 'shared',
        ]);
        $field = $this->generator()->create_field(['Name', 'Role'], [
            'categoryid' => $category->get('id'),
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
        ]);
        foreach ($areas as [$component, $area]) {
            (new shared(0, (object)[
                'categoryid' => $category->get('id'),
                'component' => $component,
                'area' => $area,
                'itemid' => 0,
            ]))->create();
        }
        handler::reset_caches();
        return $field;
    }

    /**
     * A cohort with a given id, inserted as core's own shared-field test does, so two areas share an instance id.
     *
     * @param int $id The id the cohort takes (another area's instance id).
     * @param string $name
     * @return \stdClass The cohort record.
     */
    protected function cohort_with_id(int $id, string $name): \stdClass {
        global $DB;

        $DB->insert_record_raw('cohort', [
            'id' => $id,
            'contextid' => \core\context\system::instance()->id,
            'name' => $name,
            'idnumber' => '',
            'descriptionformat' => FORMAT_HTML,
            'visible' => 1,
            'component' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ], false, false, true);
        return $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Save sets for one instance through the area handler, as core's forms and APIs save them.
     *
     * @param string $component The area's component.
     * @param string $area The area.
     * @param int $instanceid The course, cohort, group or grouping id.
     * @param string $shortname The field's shortname.
     * @param array $sets Each set as [key => value], numbered from 1.
     */
    protected function save_sets(string $component, string $area, int $instanceid, string $shortname, array $sets): void {
        $payload = [];
        foreach (array_values($sets) as $index => $set) {
            $payload['@@ID#' . ($index + 1)] = $set;
        }
        handler::get_handler($component, $area)->instance_form_save((object)[
            'id' => $instanceid,
            'customfield_' . $shortname => json_encode($payload),
        ]);
    }

    /**
     * A custom report of a datasource with some columns, without the default setup.
     *
     * @param string $source The datasource class.
     * @param string[] $columns Column unique identifiers, in order; the first ones are sorted ascending.
     * @param int $sorted How many of the first columns are sorted.
     * @return report_model
     */
    protected function sets_report(string $source, array $columns, int $sorted = 0): report_model {
        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Sets', 'source' => $source, 'default' => 0]);
        foreach ($columns as $index => $identifier) {
            $generator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $identifier,
                'sortenabled' => (int)($index < $sorted),
            ]);
        }
        return $report;
    }

    /**
     * The rows of a report, each as a list of its column values.
     *
     * @param report_model $report
     * @return array[]
     */
    protected function rows(report_model $report): array {
        return array_map('array_values', $this->get_custom_report_content($report->get('id'), 0));
    }

    /**
     * The rows of a report whose first column is one of some values, each as a list of its other column values.
     *
     * @param report_model $report
     * @param string[] $first The first-column values to keep.
     * @return array[]
     */
    protected function rows_of(report_model $report, array $first): array {
        $rows = array_filter($this->rows($report), static fn(array $row): bool => in_array($row[0], $first, true));
        $rows = array_map(static fn(array $row): array => array_map('strval', $row), $rows);
        usort($rows, static fn(array $a, array $b): int => strcmp(implode("\n", $a), implode("\n", $b)));
        return $rows;
    }

    /**
     * The availability of an entity's columns and filters, and of core's own column and filter of the same field.
     *
     * Read element by element: a report lists only its available columns and filters.
     *
     * @param report_model $report
     * @param string $entityname Our entity of the field.
     * @param string $coreidentifier Core's custom field column and filter of the same field ("course:customfield_x").
     * @param string[] $names The entity's column and filter names (the default field's).
     * @return bool[] [ours, core's]
     */
    protected function availability(
        report_model $report,
        string $entityname,
        string $coreidentifier,
        array $names = ['setnumber', 'name', 'role']
    ): array {
        manager::reset_caches();
        $instance = manager::get_report_from_persistent($report);

        $ours = null;
        foreach ($names as $name) {
            $column = $instance->get_column("{$entityname}:{$name}");
            $filter = $instance->get_filter("{$entityname}:{$name}");
            $this->assertNotNull($column, "Column {$entityname}:{$name}");
            $this->assertNotNull($filter, "Filter {$entityname}:{$name}");
            $ours ??= $column->get_is_available();
            $this->assertSame($ours, $column->get_is_available(), "Column {$entityname}:{$name}");
            $this->assertSame($ours, $filter->get_is_available(), "Filter {$entityname}:{$name}");
        }

        $corecolumn = $instance->get_column($coreidentifier);
        $corefilter = $instance->get_filter($coreidentifier);
        $this->assertNotNull($corecolumn, $coreidentifier);
        $this->assertNotNull($corefilter, $coreidentifier);
        $this->assertSame($corecolumn->get_is_available(), $corefilter->get_is_available());

        return [$ours, $corecolumn->get_is_available()];
    }

    /**
     * The sorted column and filter identifiers of one entity of a datasource.
     *
     * @param string $source The datasource class.
     * @param string $entityname
     * @return array [column identifiers, filter identifiers]
     */
    protected function identifiers(string $source, string $entityname): array {
        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Identifiers', 'source' => $source, 'default' => 0]);
        manager::reset_caches();
        $instance = manager::get_report_from_persistent($report);

        $own = static fn(array $identifiers): array => array_values(array_filter(
            $identifiers,
            static fn(string $identifier): bool => str_starts_with($identifier, $entityname . ':')
        ));
        $columns = $own(array_keys($instance->get_columns()));
        $filters = $own(array_keys($instance->get_filters()));
        sort($columns);
        sort($filters);
        return [$columns, $filters];
    }

    /**
     * Every column of one entity runs, sorted, on this database: each alone in a report.
     *
     * @param string $source The datasource class.
     * @param string $entityname
     * @return int The number of columns run.
     */
    protected function run_every_column(string $source, string $entityname): int {
        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Every column', 'source' => $source, 'default' => 0]);
        manager::reset_caches();
        $instance = manager::get_report_from_persistent($report);

        $count = 0;
        foreach ($instance->get_columns() as $identifier => $columninstance) {
            if (!str_starts_with($identifier, $entityname . ':')) {
                continue;
            }
            $column = report::add_report_column($report->get('id'), $identifier);
            report::toggle_report_column_sorting($report->get('id'), $column->get('id'), true, SORT_DESC);
            $this->assertNotEmpty($this->get_custom_report_content($report->get('id')), $identifier);
            report::delete_report_column($report->get('id'), $column->get('id'));
            $count++;
        }
        return $count;
    }

    /**
     * Assert a datasource is available on PostgreSQL and MySQL/MariaDB only.
     *
     * The area's handlers are warmed with the real database first: they cache their fields for the
     * request, so the mock only has to answer the family.
     *
     * @param string $source The datasource class.
     */
    protected function assert_db_allowlist(string $source): void {
        global $DB;

        $this->assertTrue($source::is_available(), 'Available on the test database');

        $realdb = $DB;
        try {
            $families = ['mssql' => false, 'oracle' => false, 'sqlite' => false, 'postgres' => true, 'mysql' => true];
            foreach ($families as $family => $expected) {
                $mock = $this->createMock(\moodle_database::class);
                $mock->method('get_dbfamily')->willReturn($family);
                $DB = $mock;
                $this->assertSame($expected, $source::is_available(), "Database family {$family}");
            }
        } finally {
            $DB = $realdb;
        }
    }
}
