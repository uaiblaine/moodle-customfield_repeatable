# purge_orphans(): a row of a record of another field type is kept.
s~LEFT JOIN \{customfield_field\} f ON f\.id = d\.fieldid AND f\.type = :type~LEFT JOIN {customfield_field} f ON f.id = d.fieldid AND :type <> ''~s;
