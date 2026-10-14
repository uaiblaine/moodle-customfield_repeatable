# before_delete_data(): the projection rows of the data core deletes stay.
s~(public static function before_delete_data\(.*?\{\n\s*global \$DB;\n\n\s*)\$DB->delete_records_select\(source::TABLE, "dataid \$dataidstest", \$params\);~$1// Rows kept.~s;
