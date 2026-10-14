# data_controller::delete(): the projection rows of the deleted value stay.
s~\$DB->delete_records\(source::TABLE, \['dataid' => \$dataid\]\);~// Rows kept.~s;
