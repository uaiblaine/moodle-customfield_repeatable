# config_form_validation(): the stored field is the one the submission names, not this controller's.
s~(public function config_form_validation.*?)\$fieldid = \(int\)\$this->get\('id'\);~$1\$fieldid = (int)(\$data['id'] ?? 0);~s;
