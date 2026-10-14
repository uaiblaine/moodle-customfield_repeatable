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

namespace customfield_repeatable\local;

use local_repeatable\local\bridge\pattern;
use local_repeatable\local\payload;
use local_repeatable\local\schema;

/**
 * The compact summary of a payload that export_value() returns (owner decision D4).
 *
 * One line per set: the title column's value, then the subline columns' values in brackets.
 * Core prints export_value() unescaped in course listings (core_customfield/field_data), in its
 * Report Builder column and in web services, so every value is escaped here and nothing is run
 * through format_text(): the payload is JSON typed by the instance's editors, never markup.
 * Dictionary codes show their label and pattern values their mask, as the engine's renderer
 * shows them ({@see \local_repeatable\output\sets_renderer}).
 *
 * @package    customfield_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class summary {
    /** @var array Label of each resolved code, keyed "<domain>\n<code>"; null when it has none. */
    private static array $labels = [];

    /**
     * The summary HTML of a raw payload.
     *
     * @param schema $schema The field's column schema.
     * @param string $rawpayload The stored payload.
     * @return string|null Lines joined by <br>, every value escaped; null when the payload holds no set.
     */
    public static function html(schema $schema, string $rawpayload): ?string {
        $sets = payload::normalise($rawpayload, $schema);
        if (empty($sets)) {
            return null;
        }
        self::prefetch_labels($schema, $sets);

        $titlekey = $schema->title_key() ?? ($schema->keys()[0] ?? '');
        $sublinekeys = array_values(array_diff($schema->subline_keys(), [$titlekey]));
        $separator = get_string('listsep', 'langconfig') . ' ';
        $lines = [];
        $setnumber = 0;
        foreach ($sets as $set) {
            $setnumber++;
            $title = self::shown($schema, $titlekey, (string)($set[$titlekey] ?? ''));
            $sublines = [];
            foreach ($sublinekeys as $key) {
                $value = self::shown($schema, $key, (string)($set[$key] ?? ''));
                if ($value !== '') {
                    $sublines[] = s($value);
                }
            }
            if ($title === '' && empty($sublines)) {
                $lines[] = s(get_string('repeatableset', 'local_repeatable', $setnumber));
            } else if ($title === '') {
                $lines[] = implode($separator, $sublines);
            } else if (empty($sublines)) {
                $lines[] = s($title);
            } else {
                $lines[] = get_string('summaryline', 'customfield_repeatable', (object)[
                    'title' => s($title),
                    'subline' => implode($separator, $sublines),
                ]);
            }
        }
        return implode('<br>', $lines);
    }

    /**
     * Forget the labels resolved so far, for tests that change a dictionary within one request.
     *
     * @return void
     */
    public static function reset_label_memo(): void {
        self::$labels = [];
    }

    /**
     * One value as it is shown, as plain text: a code's label, a pattern value with its mask.
     *
     * @param schema $schema
     * @param string $key Column key.
     * @param string $value Stored value.
     * @return string Trimmed plain text; '' for a blank value.
     */
    private static function shown(schema $schema, string $key, string $value): string {
        $value = trim($value);
        if ($value === '' || $key === '') {
            return '';
        }
        $patternconfig = $schema->pattern_config($key);
        if ($patternconfig !== null) {
            return trim(pattern::format($patternconfig, $value));
        }
        $domain = $schema->domain_map()[$key] ?? null;
        if ($domain === null) {
            return $value;
        }
        $label = self::$labels[$domain . "\n" . $value] ?? null;
        return $label === null ? $value : trim($label);
    }

    /**
     * Resolve the codes of the title and subline columns, one bulk call per domain not yet memoised.
     *
     * Core calls export_value() once per row of a report or listing; the memo keeps that at one
     * query per new code instead of one per row. The companion's resolver is a soft dependency: without
     * it codes show as stored.
     *
     * @param schema $schema
     * @param array $sets Normalised sets.
     * @return void
     */
    private static function prefetch_labels(schema $schema, array $sets): void {
        if (!class_exists('\local_dictionaries\resolver')) {
            return;
        }
        $domains = $schema->domain_map();
        $codes = [];
        foreach ($sets as $set) {
            foreach ($domains as $key => $domain) {
                $code = trim((string)($set[$key] ?? ''));
                if ($code !== '' && !array_key_exists($domain . "\n" . $code, self::$labels)) {
                    $codes[$domain][$code] = $code;
                }
            }
        }
        foreach ($codes as $domain => $list) {
            try {
                $resolved = \local_dictionaries\resolver::resolve_bulk($domain, array_values($list));
            } catch (\Throwable $e) {
                $resolved = [];
            }
            foreach ($list as $code) {
                $label = $resolved[$code] ?? null;
                self::$labels[$domain . "\n" . $code] = (is_string($label) && trim($label) !== '') ? $label : null;
            }
        }
    }
}
