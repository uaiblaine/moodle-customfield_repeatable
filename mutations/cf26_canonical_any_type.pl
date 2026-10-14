# source::canonical(): a record of any field type is read as a repeatable payload.
s~(WHERE d\.id = :dataid\n\s*)AND f\.type = :type~$1AND :type <> ''~s;
