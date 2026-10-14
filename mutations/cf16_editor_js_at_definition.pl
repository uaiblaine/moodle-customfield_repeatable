# schema_textarea: the column editor is required while the form is defined, not when it is rendered.
s~(class schema_textarea extends \\MoodleQuickForm_textarea \{\n)~$1    /**\n     * Mutated constructor.\n     *\n     * \@param mixed ...\$args\n     */\n    public function __construct(...\$args) {\n        \\local_repeatable\\form\\column_editor::init_js();\n        parent::__construct(...\$args);\n    }\n\n~s;
s~(public function export_for_template\(renderer_base \$output\) \{\n)\s*\\local_repeatable\\form\\column_editor::init_js\(\);\n~$1~s;
