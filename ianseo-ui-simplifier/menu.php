<?php

global $CFG;

// 1) Chemin absolu vers le dossier de settings.php
$fullDir = realpath(__DIR__);

// 2) On fragmente après “Modules/Custom/”
$parts = preg_split(
    '#'.preg_quote(DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Custom'.DIRECTORY_SEPARATOR, '#').'#',
    $fullDir,
    2
);

// 3) Reconstruit la partie “ianseo-ui-simplifier-main/ianseo-ui-simplifier/App”
if (count($parts) === 2) {
    $modulePath = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($parts[1], '/'));
} else {
    $modulePath = '';
}

// 4) Monte l’URL complète *avec* le slash manquant
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$rootDir = rtrim($CFG->ROOT_DIR, '/').'/';

$url = sprintf(
    '%s://%s%sModules/Custom/%s/App/settings.php',
    $scheme,
    $host,
    $rootDir,
    $modulePath
);

// 5) Injection dans le menu
$ret['MODS']['ui-simplifier'] = 'Affichages 🔒|'.$url;


// on suppose que session_start() est déjà fait dans config.php




// Lecture cookie au lieu de $_SESSION
$ultraBasique = (isset($_COOKIE['ultra_basique']) && $_COOKIE['ultra_basique'] === '1');
if ($ultraBasique) {
require_once __DIR__ . '/App/ultra-basique.css.php';
$css = getUltraBasiqueCSS();
require_once __DIR__ . '/App/ultra-basique.js.php';
$js = getUltraBasiqueJS();
if ($css !== '') {
    echo "\n<!-- Ultra basique CSS injecté -->\n";
    echo "<style>\n{$css}</style>\n";
} else {
    echo "<!-- Ultra basique CSS généré, mais vide pour cette page -->\n";
};
if ($js !== '') {
    echo "\n<!-- Ultra basique JS injecté -->\n";
   echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
  const nav = document.querySelector('#navigation > ul');
  if (!nav) {
    console.warn('[ui-simplifier] Navigation non trouvée, rien à faire.');
    return;
  }
  if (!document.querySelector('#ui-simplifier-active-2')) {
    nav.insertAdjacentHTML(
      'beforeend',
      '<li class="MenuTitle" id="ui-simplifier-active-2">' +
      '<a style="font-size: 0.8em;font-weight: normal;pointer-events: none;"><i>{$js}</i></a>' +
      '</li>'
    );
  }
});
</script>
HTML;


};}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const stored = localStorage.getItem('hidden_menus');
  if (!stored) return;
  stored.split(',').forEach(function(href) {
    if (!document.querySelector('#ui-simplifier-active-1')) {
        document.querySelector('#navigation > ul')
        .insertAdjacentHTML(
          'beforeend',
          '<li class="MenuTitle" id="ui-simplifier-active-1"><a style="font-size: 0.8em;font-weight: normal;pointer-events: none;"><i>Menus 🔒</i></a></li>'
        );
    }
    document.querySelectorAll('li > a[href="' + href + '"]').forEach(function(el) {
      // supprimer les <hr> consécutifs
      let sib = el.parentNode.nextElementSibling;
      while (sib && sib.tagName === 'HR') {
        const toRem = sib;
        sib = sib.nextElementSibling;
        toRem.remove();
      }
      // puis le <li>
      el.parentNode.remove();
    });
  });
  // nettoyage des <ul> vides
  document.querySelectorAll('ul').forEach(function(ul) {
    if (!ul.querySelector('li')) ul.remove();
  });
  // suppression des <li> ne contenant que <a href="#url">
  document.querySelectorAll('li').forEach(function(li) {
    const a = li.querySelector(':scope > a[href="#url"]');
    if (a && li.children.length === 1) li.remove();
  });
  // suppression des <hr> isolés
  document.querySelectorAll('hr').forEach(function(hr) {
    const prev = hr.previousElementSibling;
    const next = hr.nextElementSibling;
    if (!prev || prev.tagName !== 'LI' || !next || next.tagName !== 'LI') {
      hr.remove();
    }
  });
  // nettoyage des <ul> vides
  document.querySelectorAll('ul').forEach(function(ul) {
    if (!ul.querySelector('li')) ul.remove();
  });
  // suppression des <li> ne contenant que <a href="#url">
  document.querySelectorAll('li').forEach(function(li) {
    const a = li.querySelector(':scope > a[href="#url"]');
    if (a && li.children.length === 1) li.remove();
  });
});





/*


if (!document.querySelector('#ui-simplifier-active')) {
    document.querySelector('#navigation > ul').insertAdjacentHTML('beforeend',
        '<li class=\"MenuTitle\" id=\"ui-simplifier-active\">' +
        '<a style=\"font-size: 0.8em;font-weight: normal;pointer-events: none;\"><i>des éléments sont cachés</i></a>' +
        '</li>'
    );
}*/


</script>