# instance_form_save(): an empty value is stored as '{}', which core reads as filled.
s~\$submitted->\{\$elementname\} = empty\(\$payload\) \? '' : payload::encode\(\$payload\);~\$submitted->{\$elementname} = payload::encode(\$payload);~s;
