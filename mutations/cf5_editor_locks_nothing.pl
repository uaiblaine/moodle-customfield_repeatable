# config_form_definition(): the editor is told that no key holds values.
s~\$usage = stored_values::usage\(\$fieldid, field_schema::stored\(\$fieldid\) \?\? schema::from_columns\(\[\]\)\);~\$usage = ['users' => 0, 'keys' => []];~s;
