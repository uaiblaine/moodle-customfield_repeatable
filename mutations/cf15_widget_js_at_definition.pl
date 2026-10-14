# widget_html: the module is required while the form is defined, not when it is rendered.
s~(parent::__construct\(\$html\);\n)~$1        \$widget->init_js(\$ids, false);\n~s;
s~(public function accept\(&\$renderer, \$required = false, \$error = null\) \{\n)\s*\$this->widget->init_js\(\$this->ids, false\);\n~$1~s;
