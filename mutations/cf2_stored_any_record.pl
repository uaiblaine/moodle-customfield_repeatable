# stored_payload(): the record is read by id alone, so a made-up id borrows another instance's codes.
s~\$value = \$DB->get_field\('customfield_data', 'value', \[\n\s*'id' => \$dataid,\n\s*'fieldid' => \(int\)\$this->get_field\(\)->get\('id'\),\n\s*'instanceid' => \(int\)\$this->get\('instanceid'\),\n\s*\]\);~\$value = \$DB->get_field('customfield_data', 'value', ['id' => \$dataid]);~s;
