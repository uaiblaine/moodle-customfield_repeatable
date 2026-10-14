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

$string['columns'] = 'Colunas';
$string['columns_help'] =
    'As colunas de cada conjunto, como um esquema JSON: {"version": 2, "columns": [{"key": "language", "label": "Idioma", "mode": "dictionary", "domain": "iso639", "title": true}, ...]}. Com JavaScript, o editor de colunas o escreve. Modos: free (livre), dictionary (somente códigos de um domínio do local_dictionaries), dictionary_open (qualquer código), pattern (máscara e/ou expressão regular do customfield_pattern; um objeto "pattern" com preset, mask, regex, regexerror e storeformat). Exatamente uma coluna é o título; até 3 aparecem na linha secundária. Uma coluna cuja chave guarda valores não pode ser removida nem ter a chave trocada; renomeie o rótulo.';
$string['pluginname'] = 'Conjuntos repetíveis';
$string['privacy:metadata:customfield_repeatable_set'] =
    'Uma cópia de cada conjunto do valor de um campo personalizado repetível, mantida para relatórios. O valor em si é guardado pelo núcleo em customfield_data.';
$string['privacy:metadata:customfield_repeatable_set:data'] = 'Os valores de um conjunto, em JSON';
$string['privacy:metadata:customfield_repeatable_set:dataid'] = 'O registro de customfield_data ao qual o conjunto pertence';
$string['privacy:metadata:customfield_repeatable_set:fieldid'] = 'O campo personalizado';
$string['privacy:metadata:customfield_repeatable_set:instanceid'] = 'O curso, coorte, grupo ou outra instância à qual o valor pertence';
$string['privacy:metadata:customfield_repeatable_set:set_id'] = 'O número do conjunto';
$string['privacy:metadata:customfield_repeatable_set:timecreated'] = 'A data de criação da linha do conjunto';
$string['privacy:metadata:customfield_repeatable_set:timemodified'] = 'A data da última alteração da linha do conjunto';
$string['specificsettings'] = 'Configurações do campo de conjuntos repetíveis';
$string['summaryline'] = '{$a->title} ({$a->subline})';
$string['taskreprojectsets'] = 'Reconstruir os conjuntos de campos personalizados repetíveis para relatórios';
