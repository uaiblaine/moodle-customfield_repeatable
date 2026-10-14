# instance_form_save(): the submission is stored as typed.
s~\$submitted->\{\$elementname\} = empty\(\$payload\) \? '' : payload::encode\(\$payload\);~\$submitted->{\$elementname} = (string)\$datanew->{\$elementname};~s;
