# area_sets::entities(): only the component is checked, so a group and a grouping of the same id mix
# (on Moodle 4.5 the field id still keeps them apart).
s~\n\s*AND \{\$categoryalias\}\.area = '\{\$area\}'~~s;
