<?php
// 1) Determine URL scheme (http vs. https)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

// 2) Build host and app root path
$host = $_SERVER['HTTP_HOST'];
$root = rtrim($GLOBALS['CFG']->ROOT_DIR, '/') . '/';

// 3) Compute this module’s path under Modules/Custom
$current = str_replace('\\', '/', __DIR__);
$relative = preg_replace('#^.*?/Modules/Custom/#', '', $current);

// 4) Assemble URL to App/settings.php
$settingsUrl = "{$scheme}://{$host}{$root}Modules/Custom/" . ltrim($relative, '/') . "/App/settings.php";

// 5) Inject into the menu config
$View = "Affichage";
$ret['MODS']['uiSimplifier'] = "{$View} 🔒|{$settingsUrl}";

// -------------------------------------------------
// Ultra Basic mode: read flag from cookie
if (!empty($_COOKIE['ultra_basic']) && $_COOKIE['ultra_basic'] === '1') {
    // Load and inject Ultra Basic CSS
    require_once __DIR__ . '/App/ultra-basic.css.php';
    $css = getUltraBasicCSS() ?? '';
    if ($css !== '') {
        echo "\n<!-- Ultra Basic CSS injected -->\n<style>\n{$css}\n</style>\n";
    } else {
        echo "<!-- Ultra Basic CSS generated but empty -->\n";
    }

    // Load and inject Ultra Basic JS indicator
    require_once __DIR__ . '/App/ultra-basic.js.php';
    $icon = getUltraBasicJS() ?? '';
    if ($icon !== '') {
        echo "\n<!-- Ultra Basic JS injected -->\n";
        echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nav = document.querySelector('#navigation > ul');
    if (!nav) return console.warn('[ui-simplifier] Navigation not found');
    if (!document.getElementById('ui-simplifier-active-2')) {
        nav.insertAdjacentHTML('beforeend',
            '<li class="MenuTitle" id="ui-simplifier-active-2">' +
            '<a style="font-size:0.8em; font-weight:normal; pointer-events:none;">' +
            '<i>{$icon}</i>' +
            '</a></li>'
        );
    }
});
</script>
JS;
    }
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nav = document.querySelector('#navigation > ul');
    const hidden = localStorage.getItem('hidden_menus');
    if (nav && hidden) {
        // Insert lock indicator once
        if (!document.getElementById('ui-simplifier-active-1')) {
            nav.insertAdjacentHTML('beforeend',
                '<li class="MenuTitle" id="ui-simplifier-active-1">' +
                '<a style="font-size:0.8em;font-weight:normal;pointer-events:none;">' +
                '<i>Menus 🔒</i></a></li>'
            );
        }
        // Remove hidden menu items and any <hr> siblings
        hidden.split(',').forEach(href => {
            document.querySelectorAll(`li > a[href="${href}"]`).forEach(link => {
                let sib = link.parentNode.nextElementSibling;
                while (sib && sib.tagName === 'HR') {
                    const toRemove = sib;
                    sib = sib.nextElementSibling;
                    toRemove.remove();
                }
                link.parentNode.remove();
            });
        });
    }

    // Cleanup function: remove empty lists, placeholder-only items, and stray <hr>
    (function cleanup() {
        document.querySelectorAll('ul').forEach(ul => {
            if (!ul.querySelector('li')) ul.remove();
        });
        document.querySelectorAll('li').forEach(li => {
            const placeholder = li.querySelector(':scope > a[href="#url"]');
            if (placeholder && li.children.length === 1) li.remove();
        });
        document.querySelectorAll('hr').forEach(hr => {
            const prev = hr.previousElementSibling;
            const next = hr.nextElementSibling;
            if (!prev || prev.tagName !== 'LI' || !next || next.tagName !== 'LI') {
                hr.remove();
            }
        });
    })();
});
</script>