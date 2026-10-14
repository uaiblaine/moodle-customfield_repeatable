# export_value(): core's default, format_text() over the raw JSON.
s~return summary::html\(\$this->get_schema\(\), \(string\)\$value\);~return parent::export_value();~s;
