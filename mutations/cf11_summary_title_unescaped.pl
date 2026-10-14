# summary::html(): the title value goes out unescaped.
s~'title' => s\(\$title\),~'title' => \$title,~s;
