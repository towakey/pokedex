<?php
foreach (['gen_gsc.php','gen_rse.php','gen_frlg.php','gen_dppt.php','gen_hgss.php','gen_oras.php','gen_bdsp.php'] as $f) {
    $c = file_get_contents($f);
    $c2 = preg_replace(
        '/\^Evolve \(\[A-Z\]\[[^\]]+\]\+\?\)\( or\.\*\)\?\$/',
        "^Evolve ([A-Z][^,]+?)([ ,].*)?$",
        $c, -1, $n
    );
    // add /u flag to that specific preg_match call
    $c2 = str_replace("'/^Evolve ([A-Z][^,]+?)([ ,].*)?$/'", "'/^Evolve ([A-Z][^,]+?)([ ,].*)?$/u'", $c2);
    file_put_contents($f, $c2);
    echo "$f: $n replaced\n";
}
