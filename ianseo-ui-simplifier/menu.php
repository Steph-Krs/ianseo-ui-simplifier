<?php

// 1. Calcule le chemin relatif au répertoire web (DOCUMENT_ROOT)
$relativePath = str_replace(
    realpath($_SERVER['DOCUMENT_ROOT']),
    '',
    realpath(__DIR__)
);

// 2. Construit le schéma (http ou https) + host
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$baseUrl  = "{$scheme}://{$host}";

// 3. Monte l’URL finale vers App/settings.php
$ret['MODS']['ui-simplifier'] = 'UI Simplifier 👁️🔒|' 
    . $baseUrl 
    . $relativePath 
    . '/App/settings.php';


// on suppose que session_start() est déjà fait dans config.php

// Lecture cookie au lieu de $_SESSION
$ultraBasique = (isset($_COOKIE['ultra_basique']) && $_COOKIE['ultra_basique'] === '1');

if ($ultraBasique) {
    require_once __DIR__ . '/App/ultra-basique.css.php';
    $css = getUltraBasiqueCSS();
    if ($css !== '') {
        echo "<!-- Ultra basique CSS injecté -->\n";
        echo "<style>\n{$css}</style>\n";
    } else {
        echo "<!-- Ultra basique CSS généré, mais vide pour cette page -->\n";
    }
}

?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const stored = localStorage.getItem('hidden_menus');
  if (!stored) return;
  stored.split(',').forEach(function(href) {
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
</script>