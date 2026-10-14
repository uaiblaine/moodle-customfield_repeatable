# course_sets::is_available(): the database family is not checked.
s~if \(!area_sets::db_supported\(\)\) \{\s*return false;\s*\}~~s;
