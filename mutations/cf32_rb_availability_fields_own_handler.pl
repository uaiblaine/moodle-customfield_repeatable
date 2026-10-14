# repeatable_set::is_available(): the field's own handler decides (core_customfield's for a shared field), not the area's.
s~return \$this->handler->can_view\(\$this->field, 0\);~return \$this->field->get_handler()->can_view(\$this->field, 0);~s;
