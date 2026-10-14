# instance_form_save(): the save path does not run the form's checks (web services, group and cohort APIs store any code).
s~if \(\$this->submitted_error\(\$datanew->\{\$elementname\}, \$payload\) !== null\) \{~\$payload = payload::normalise(\$datanew->{\$elementname}, \$this->get_schema());\n        if (false) {~s;
