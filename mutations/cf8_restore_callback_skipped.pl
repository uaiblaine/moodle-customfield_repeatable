# restore_define_structure(): the restored record is not rebuilt.
s~(public function restore_define_structure\(.*?\): void \{\n)~$1        return;\n~s;
