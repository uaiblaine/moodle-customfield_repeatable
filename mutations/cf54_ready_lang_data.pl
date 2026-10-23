# Build a string with code: a language file must be data only.
s/\$string\['pluginname'\] = 'Repeatable sets';/\$string['pluginname'] = 'Repeatable ' . 'sets';/;
