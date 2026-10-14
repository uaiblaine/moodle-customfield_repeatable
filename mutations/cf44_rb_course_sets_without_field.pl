# course_sets::is_available(): offered without a repeatable course field.
s~return !empty\(area_sets::fields\(area_sets::handler\(self::COMPONENT, self::AREA\)\)\);~return true;~s;
