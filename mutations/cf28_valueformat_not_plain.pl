# instance_form_save(): the value keeps the default format, which core's fallbacks would format as Moodle text.
s~\$this->set\('valueformat', FORMAT_PLAIN\);\n\s*~~s;
