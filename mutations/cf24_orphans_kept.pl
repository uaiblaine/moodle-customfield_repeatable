# purge_orphans(): no row counts as orphaned.
s~WHERE f\.id IS NULL~WHERE 1 = 0~s;
