# export_customfield_data(): the export keeps core's summary HTML instead of the sets.
s~\$exportdata->value = set_exporter::export\(\$schema, \(string\)\(\$data->get_value\(\) \?\? ''\)\);~// Summary kept.~s;
