# repeatable_set::filter_options_scope(): the options query is not narrowed to the entity's area.
s~return \[\n\s+"EXISTS \(SELECT 1.*?\],\n\s+\];~return ['', []];~s;
