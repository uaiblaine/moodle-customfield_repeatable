# get_default_value(): a non-null default, which core's filter COALESCEs the JSON with.
s~(public function get_default_value\(\) \{\n\s*)return null;~$1return '{}';~s;
