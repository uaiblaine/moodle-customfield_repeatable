# area_sets::entities(): the recorded area is not checked, so a shared field shows another area's sets of the same id.
s~AND EXISTS \(.*?\n\s*\)"~"~s;
