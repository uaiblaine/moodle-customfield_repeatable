# repeatable_set::is_available(): every field is available to everyone, whatever core decides.
s~return \$this->handler->can_view\(\$this->field, 0\);~return true;~s;
