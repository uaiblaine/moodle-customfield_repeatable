# area_sets::entities(): the component and area are written into the join SQL unchecked.
s~if \(!preg_match\('/\^\[a-z\]\[a-z0-9_\]\*\$/', \$component\) \|\| !preg_match\('/\^\[a-z\]\[a-z0-9_\]\*\$/', \$area\)\) \{~if (false) {~s;
