# reproject_course(): the course's values are matched by area only, so every course is rebuilt.
s~AND d\.instanceid = :courseid\)~AND d.instanceid > 0 AND :courseid > 0)~s;
