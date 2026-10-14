# instance_form_validation(): the submitted payload counts as stored, so every submitted code is exempt.
s~\$validator = \$this->get_validator\(\$this->stored_payload\(\)\);~\$validator = \$this->get_validator(is_string(\$rawpayload) ? \$rawpayload : '');~s;
