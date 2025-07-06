<?php

if(!empty($on) AND isset($ret['MODS'])) {
	$ret['MODS']['ui-simplifier'] = 'UI Simplifier 👁️🔒'.'|'.$CFG->ROOT_DIR.'Modules/Custom/ianseo-ui-simplifier/App/settings.php';
}



if (!empty($_SESSION['hidden_menus'])) {
    echo "<script>\n";
    echo "document.addEventListener('DOMContentLoaded', function(){\n";
    foreach ($_SESSION['hidden_menus'] as $href) {
        $safe = addslashes($href);
        printf(
            "  document.querySelectorAll('li > a[href=\"%s\"]').forEach(function(el){\n" .
            "    var li = el.parentNode;\n" .
            "    // supprimer tous les <hr> consécutifs\n" .
            "    var sib = li.nextElementSibling;\n" .
            "    while(sib && sib.tagName === 'HR'){\n" .
            "      var toRem = sib;\n" .
            "      sib = sib.nextElementSibling;\n" .
            "      toRem.remove();\n" .
            "    }\n" .
            "    // supprimer le <li>\n" .
            "    li.remove();\n" .
            "  });\n",
            $safe
        );
    }
    // suppression des <ul> vides
    echo "  document.querySelectorAll('ul').forEach(function(ul){\n";
    echo "    if (!ul.querySelector('li')) ul.remove();\n";
    echo "  });\n";

    // NOUVEAU : suppression des LI ne contenant que <a href=\"#url\">
    echo "  document.querySelectorAll('li').forEach(function(li){\n";
    echo "    var a = li.querySelector(':scope > a[href=\"#url\"]');\n";
    echo "    if (a && li.children.length === 1) {\n";
    echo "      li.remove();\n";
    echo "    }\n";
    echo "  });\n";

    // Suppression des <hr> qui n'ont pas un <li> juste avant ET juste après
    echo "  document.querySelectorAll('hr').forEach(function(hr){\n";
    echo "    var prev = hr.previousElementSibling;\n";
    echo "    var next = hr.nextElementSibling;\n";
    echo "    if (!prev || prev.tagName !== 'LI' || !next || next.tagName !== 'LI') {\n";
    echo "      hr.remove();\n";
    echo "    }\n";
    echo "  });\n";

    // suppression des <ul> vides
    echo "  document.querySelectorAll('ul').forEach(function(ul){\n";
    echo "    if (!ul.querySelector('li')) ul.remove();\n";
    echo "  });\n";

    // NOUVEAU : suppression des LI ne contenant que <a href=\"#url\">
    echo "  document.querySelectorAll('li').forEach(function(li){\n";
    echo "    var a = li.querySelector(':scope > a[href=\"#url\"]');\n";
    echo "    if (a && li.children.length === 1) {\n";
    echo "      li.remove();\n";
    echo "    }\n";
    echo "  });\n";

    echo "});\n";
    echo "</script>\n";
}
?>