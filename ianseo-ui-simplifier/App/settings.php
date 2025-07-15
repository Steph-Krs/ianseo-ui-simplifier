<?php
// 1) Si on vient du formulaire Settings…
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form_type'] ?? '') === 'settings'
) {
    // checkbox cochée ou non
    $enabled = isset($_POST['ultra_basique']) && $_POST['ultra_basique'] === '1';
    // écriture du cookie 30 jours
    setcookie(
        'ultra_basique',
        $enabled ? '1' : '0',
        time() + 30*24*3600,
        '/',    // path
        '',     // domaine courant
        false,  // secure (mettre true en prod HTTPS)
        true    // httponly
    );
    // PRG pour ne pas repost et pour vider $_POST
    header('Location:' . $_SERVER['REQUEST_URI']);
    exit;
}

// 2) Ensuite seulement, on charge la conf et le head //cookie 2 avant...

// 3) Lecture du cookie pour l’affichage
$ultraBasique = (isset($_COOKIE['ultra_basique']) && $_COOKIE['ultra_basique'] === '1');

// 4) Ton debug (optionnel, à retirer en prod)
echo '<!-- DEBUG SETTINGS | COOKIE_ultra_basique=' 
     . ($_COOKIE['ultra_basique'] ?? 'none') 
     . ' -->';

$hidden = []; // sera géré en JS via localStorage

// 1) Inclusion de la conf et du head
require_once dirname(__FILE__, 5) . '/config.php';
include 'Common/Templates/head.php';

if (isset($_POST['update_module'])) {
    $res = updateUIsimplifierModule();
    echo '<div style="margin:1em 0; padding:0.5em; background:#eef; border:1px solid #99c;">'
       . htmlspecialchars($res)
       . '</div>';
}
/**
 * Télécharge, extrait et remplace le dossier du module.
 * @return string Message de succès ou d’erreur.
 */
function updateUIsimplifierModule() {
    $zipUrl     = 'https://github.com/Steph-Krs/ianseo-ui-simplifier/archive/refs/heads/main.zip';
    $tmpZip     = sys_get_temp_dir().'/ianseo-ui-simplifier.zip';
    $tmpExtract = sys_get_temp_dir().'/ianseo-ui-simplifier';

    // 1. Télécharger le ZIP
    if (false === file_put_contents($tmpZip, fopen($zipUrl, 'r'))) {
        return '❌ Échec du téléchargement du ZIP.';
    }

    // 2. Ouvrir et extraire
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        return '❌ Impossible d’ouvrir l’archive ZIP.';
    }
    // vider l’ancien extrait s’il existe
    if (is_dir($tmpExtract)) {
        rrmdir($tmpExtract);
    }
    $zip->extractTo($tmpExtract);
    $zip->close();

    // 3. Copier les fichiers
    $srcDir = $tmpExtract . '/ianseo-ui-simplifier-main'; // nom du dossier dans le ZIP
    $dstDir = dirname(__DIR__,2);

    if (!is_dir($srcDir)) {
        return '❌ Structure ZIP inattendue (pas de dossier ianseo-ui-simplifier-main).';
    }
    recurse_copy($srcDir, $dstDir);

    // 4. Nettoyer
    unlink($tmpZip);
    rrmdir($tmpExtract);

    return '✅ Module à jour ! N’oublie pas de recharger la page si nécessaire.';
}

/** Recopie récursive de $src vers $dst (écrase tout). */
function recurse_copy(string $src, string $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";
        if (is_dir($srcPath)) {
            recurse_copy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

/** Supprime un dossier et tout son contenu. */
function rrmdir(string $dir) {
    if (!is_dir($dir)) return;
    $objects = scandir($dir);
    foreach ($objects as $obj) {
        if ($obj === '.' || $obj === '..') continue;
        $path = "$dir/$obj";
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}


// 3) Lecture CSV
$csvFile = 'correspondance.csv';
$lines = array_map(fn($l)=>str_getcsv($l, ';'), file($csvFile, FILE_SKIP_EMPTY_LINES));
array_shift($lines);

// 4) Nettoyage et structuration
function clean_key(string $s): string {
    return preg_replace('/;.*$/','', trim($s));
}

// Fonction pour modifier les liens pour le css (ex: & = &amp; = \26)
function css_escape(string $s): string {
    $out = '';
    // On parcourt chaque caractère UTF-8
    $len = mb_strlen($s, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($s, $i, 1, 'UTF-8');
        // Alphanumériques et quelques signes sûrs
        if (preg_match('/^[0-9A-Za-z_\-]$/', $char)) {
            $out .= $char;
        } else {
            // on prend le code point Unicode
            $cp = mb_ord($char, 'UTF-8');
            // on l’écrit en hexadécimal (minimum 1 digit), suivi d’un espace
            $hex = dechex($cp);
            $out .= '\\' . $hex . ' ';
        }
    }
    return $out;
}

/**
 * Si l'URL contient ".php?Tour=" ou ".php?TourId=", 
 * on y ajoute en fin le TourCode stocké en session.
 */
function appendTourCode(string $ur): string {
    // on démarre la session si nécessaire
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $tourCode = $_SESSION['TourCode'] ?? '';

    // ne rien faire si pas défini
    if ($tourCode === '') {
        return $ur;
    }
    if ($ur === '') {
        return $ur;
    }

    // condition de détection
    if (strpos($ur, '.php?Tour=') !== false || strpos($ur, '.php?TourId=') !== false) {
        // ici on ajoute un paramètre TourCode, tu peux modifier "TourCode" si besoin
        return '/' . $ur . $tourCode;
    }

    return '/' .$ur;
}

$data          = [];
$presetSimple  = [];
$presetAvance  = [];

foreach ($lines as $row) {
    // on extrait TOUTES les colonnes en 1 seul passage
    [$l1, $l2, $l3, $rawUrl, $flag1, $flag2] = array_map('trim', $row);

    // 1) clés de traduction
    $k1 = clean_key($l1);
    $k2 = clean_key($l2);
    $k3 = clean_key($l3);

    // si pas de menu principal, on ignore cette ligne
    if ($k1 === '') {
        continue;
    }

    // 2) préparer l'URL
    // on ajoute le TourCode, on échapper pour CSS si besoin
    $urlWithCode = appendTourCode($rawUrl);
    $path        = $urlWithCode;
    $cssPath     = css_escape($path);
    $htmlPath    = htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8');

    // 3) peupler $data pour la construction de l'arborescence
    if (! isset($data[$k1])) {
        $data[$k1] = ['children' => []];
    }
    if ($k2 !== '') {
        if (! isset($data[$k1]['children'][$k2])) {
            $data[$k1]['children'][$k2] = [
                'url'      => null,
                'children' => []
            ];
        }
        if ($k3 !== '') {
            // niveau 3
            $data[$k1]['children'][$k2]['children'][$k3] = $path;
        } else {
            // lien de niveau 2
            $data[$k1]['children'][$k2]['url'] = $path;
        }
    }

    // 4) gérer les presets d'après les colonnes 5 & 6
    if (strcasecmp($flag1, 'X') === 0) {
        $presetSimple[] = $path;
    }
    if (strcasecmp($flag2, 'X') === 0) {
        $presetAvance[] = $path;
    }
}

// dé-dupliquer les listes de presets
$presetSimple = array_values(array_unique($presetSimple));
$presetAvance = array_values(array_unique($presetAvance));

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}
/**
 * Tente get_text dans plusieurs domaines et fait un fallback
 * si get_text renvoie la clé brute ou un placeholder du type [clé]@[lang]@[domaine].
 */
function multi_get_text(string $key): string {
    // 1) Essai dans Common
    $text = get_text($key);
    // cas où get_text renvoie "[clé]@[lang]@[Common]" => 2 occurrences de "]@["
    $isPlaceholder = substr_count($text,']@[') >= 2;

    if ($text !== $key && !$isPlaceholder && $text !== '') {
        return $text;
    }

    // 2) domaines à tester dans l’ordre
    $domains = [
        'Api',
        'Awards',
        'BackNumbers',
        'Boinx',
        'Common',
        'DateTime',
        'Errors',
        'Help',
        'HTT',
        'Ianseo',
        'InfoSystem',
        'Install',
        'IOC_Codes',
        'ISK-App',
        'ISK',
        'Languages',
        'ODF',
        'Records',
        'RoundRobin',
        'RunArchery',
        'ServiceErrors',
        'Tournament',
        // ajoute ici tous les autres modules/domaines que tu utilises
    ];

    foreach ($domains as $domain) {
        $t = get_text($key, $domain);
        $isPlaceholderDomain = substr_count($t,']@[') >= 2;
        if ($t !== $key && !$isPlaceholderDomain && $t !== '') {
            return $t;
        }
    }

    // 3) Aucun trouvé : on renvoie la clé brute
    return $key;
}
?>
<style>
    p{
        margin-top:0;
    }
    .Chck_all {
        padding:6px;width:40%;text-align:center;
    }
    /* typographie */
  details > summary.lvl1 { font-size:1.2em; cursor:pointer; }
  .lvl2-item, details > summary.lvl2{  margin-left:20px;font-size:1.1em; cursor:pointer; }
  details > div.lvl3 {  margin-left:50px;font-size:1.1em; cursor:pointer; }
  .lvl3 { font-size:1em; }

  /* indentations */
  /*summary.lvl2,*/
  .lvl2-item { margin-left:20px; display:flex; align-items:center; }
  .lvl2-item label { display:flex; align-items:center; }
  .lvl3 { margin-left:40px; display:flex; align-items:center; }
  input[type=checkbox] { margin-right:0.5em; }

/* conteneur gris discret */
#save-progress-container {
  width: 100%;
  height: 12px;
  background: #e0e0e0;
  overflow: hidden;
}

/* barre verte qui va se remplir */
#save-progress-bar {
  width: 0;
  height: 100%;
  background: #4caf50;
  transition: width linear;
}
#saved {
  transition: opacity 0.5s ease;
}
.hidden {
  opacity: 0;
}


</style>


<table class="Tabella">
    <tbody>
        <tr>
            <th>Status
            </th>
            <td id="update">
                <form method="post">
                    <button type="submit" name="update_module">
                    🔄 Mettre à jour le module
                    </button>
                </form>
            </td>
        </tr>
        <tr>
            <td id="ProgressBar">
            </td>
            <td id="update">
            </td>
        </tr>
        <tr>
            <td id="saved" style="text-align:center;">
            </td>
            <td>msg new version available
            </td>
        </tr>
        <tr>
            <td>uploaded
            </td>
            <td>Upload CSV perso
            </td>
        </tr>
        <tr>
            <!-- GAUCHE -->
            <td style="width: 35%;vertical-align: top">
                <table class="Tabella">
                    <tbody>
                        <tr>
                            <th class="Title" colspan="3"><?= htmlspecialchars(multi_get_text('TVPresetChains'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                        <tr>
                            <th>
                                <button id="btnSimple" style="background: limegreen !important; animation: blink 1.5s linear infinite;"><?= htmlspecialchars(multi_get_text('Base'), ENT_QUOTES, 'UTF-8') ?></button>
                            </th>
                            <td>
                                <table  style="width: 100%">
                                    <tbody>
                                        <tr>
                                            <th style="width: 100%">
                                                <b>Pour les novices</b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Limité à un tir de classement.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <p><i>C'est pas faux!</i></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                            <td style="vertical-align: top;text-align: center;">
                                <table  style="width: 100%">
                                    <tbody>
                                        <tr>
                                            <th style="width: 100%">
                                                            mode tuto
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <form method="post" action="">
                                                    <input type="hidden" name="form_type" value="settings">
                                                    <div class="form-group">
                                                        <label style="background: limegreen !important; animation: blink 1.5s linear infinite;">
                                                            <input
                                                                type="checkbox"
                                                                name="ultra_basique"
                                                                value="1"
                                                                <?= $ultraBasique ? 'checked' : '' ?>
                                                                onchange="this.form.querySelector('button[type=submit]').click()"
                                                            >
                                                        </label>
                                                        <button type="submit" class="btn btn-primary" style="display:none;">save</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                    
                                    
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <button id="btnAvance"><?= htmlspecialchars(multi_get_text('AdvancedMode'), ENT_QUOTES, 'UTF-8') ?></button>
                            </th>
                            <td  colspan="2">
                                <table  style="width: 100%">
                                    <tbody>
                                        <tr>
                                            <th style="width: 100%">
                                                <b>Pour les initiés</b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Matchs, téléphones, affichages en direct, accréditations/dossards, mode présentateur.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <p><i>Un grand pouvoir implique de grandes responsabilités.</i></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <button id="btnExpert">Mode Expert</button>
                            </th>
                            <td  colspan="2">
                                <table  style="width: 100%">
                                    <tbody>
                                        <tr>
                                            <th style="width: 100%">
                                                <b>Pour les audacieux</b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Tout IANSEO, sans limitation.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <p><i>La route ? Là où on va, on n'a pas besoin de route...</i></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <h3>À quoi sert ce module ?</h3>
                                <p>
                                    Ce module vous permet de personnaliser l'affichage des menus dans l’interface en fonction de vos besoins (simplifié, avancé, expert, ou personnalisé).
                                </p>
                                <p><i>
                                    La sauvegarde se fait automatiquement après chaque modification dans un delais de 1,5s.
                                </i></p>
                                <p><i>
                                    Attention, si plusieurs menus mènent au même lien dans ianseo (ex: les impressions). Il faut tous les décocher pour que ce soit sauvegardé.
                                </i></p>
                            </td>
                        </tr>
                        
                    </tbody>
                </table>
            </td>
            <!-- DROITE -->
            <td style="width: 65%;vertical-align: top">
                <table class="Tabella">
                    <tbody>
                        <tr>
                            <th class="Title" colspan="2">
                                <?= multi_get_text('SetupManually') ?>
                            </th>
                        </th>
                        <tr>
                            <td style="text-align:center;justify-content: space-evenly">
                                    <button id="checkAll" class="Chck_all">☑️ <?= htmlspecialchars(multi_get_text('SelectAll'), ENT_QUOTES, 'UTF-8') ?> 🔒</button>
                                    <button id="uncheckAll" class="Chck_all">🔲 <?= htmlspecialchars(multi_get_text('NoRowSelected'), ENT_QUOTES, 'UTF-8') ?> 👁️</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php $idx1 = 0;?>
                                <form method="post" id="menuForm" target="hidden_iframe">
                                    <!-- nouveau champ pour indiquer « formulaire de menus » -->
                                    <input type="hidden" name="form_type" value="menu">

                                    <iframe name="hidden_iframe" src="about:blank" style="display:none;"></iframe>
                                    <button type="submit" style="display:none;">💾 <?= htmlspecialchars(multi_get_text('SettingsSaveConfig'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <?php foreach ($data as $lvl1 => $sub1): ?>
                                        <?php $idx1++; ?>
                                        <details id="menu1-<?= $idx1 ?>">
                                        <summary class="lvl1">
                                            <?= htmlspecialchars(multi_get_text($lvl1)) ?>
                                            <!-- checkbox de toggle du menu principal -->
                                            <span style="font-size:0.8em;">[ <input 
                                            type="checkbox"
                                                            name="hidden[]"
                                            class="toggle-all"
                                            data-target="menu1-<?= $idx1 ?>"
                                            style="margin-left:auto;"
                                            >Cacher/afficher tout]</span>
                                        </summary>

                                        <?php
                                            $idx2 = 0;
                                            foreach ($sub1['children'] as $lvl2 => $info2):
                                            $hasKids = !empty($info2['children']);
                                            $url2    = $info2['url'] ?? '';
                                            $path2   = '/'.ltrim($url2,'/');
                                            $chk2    = $url2 && in_array($path2, $hidden) ? 'checked':''; 
                                        ?>
                                            <?php if ($hasKids): ?>
                                            <?php $idx2++; ?>
                                            <details id="menu2-<?= $idx1 ?>-<?= $idx2 ?>">
                                                <summary class="lvl2">
                                                <?php if ($url2): ?>
                                                    <label>
                                                    <input type="checkbox"
                                                            name="hidden[]"
                                                            value="<?= htmlspecialchars($path2, ENT_QUOTES) ?>"
                                                            <?= $chk2 ?>>
                                                    </label>
                                                <?php endif; ?>
                                                <?= htmlspecialchars(multi_get_text($lvl2)) ?>
                                                <!-- checkbox de toggle du sous-menu -->
                                                <span style="font-size:0.8em;">[ <input
                                                    type="checkbox"
                                                            name="hidden[]"
                                                    class="toggle-all"
                                                    data-target="menu2-<?= $idx1 ?>-<?= $idx2 ?>"
                                                    style="margin-left:auto;"
                                                >Cacher/afficher tout le sous-menu]</span>
                                                
                                                </summary>

                                                <?php foreach ($info2['children'] as $lvl3 => $url3): ?>
                                                <?php $path3 = '/'.ltrim($url3,'/'); ?>
                                                <div class="lvl3">
                                                    <label>
                                                        
                                                        <input type="checkbox" name="hidden[]"
                                                            value="<?= htmlspecialchars($path3, ENT_QUOTES) ?>"
                                                            <?= in_array($path3, $hidden)?'checked':'' ?>
                                                        >
                                                        <?= htmlspecialchars(multi_get_text($lvl3)) ?>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </details>
                                            <?php else: ?>
                                            <div class="lvl2-item">
                                                <?php if ($url2): ?>
                                                <label>
                                                    
                                                    <input type="checkbox" name="hidden[]"
                                                        value="<?= htmlspecialchars($path2, ENT_QUOTES) ?>"
                                                        <?= $chk2 ?> >
                                                    <?= htmlspecialchars(multi_get_text($lvl2)) ?>
                                                </label>
                                                <?php else: ?>
                                                <?= htmlspecialchars(multi_get_text($lvl2)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        </details>
                                    <?php endforeach; ?>
                                
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>

<script>
  // Liste des URL cochées pour chaque preset
  const presetSimple = <?= json_encode(array_values(array_unique($presetSimple)), JSON_HEX_TAG) ?>;
  //console.log("presetSimple = ",presetSimple);
  const presetAvance = <?= json_encode(array_values(array_unique($presetAvance)), JSON_HEX_TAG) ?>;
    
document.addEventListener('DOMContentLoaded', function(){

  // coche / décoche tous les enfants d'un container
  function toggleAllItems(targetId, checked) {
    const container = document.getElementById(targetId);
    if (!container) return;
    container.querySelectorAll('input[type=checkbox]:not(.toggle-all)')
             .forEach(cb => cb.checked = checked);
  }

  // met à jour la coche du toggle en fonction de ses enfants
  function updateToggle(toggle) {
    const container = document.getElementById(toggle.dataset.target);
    if (!container) return;
    const children = Array.from(
      container.querySelectorAll('input[type=checkbox]:not(.toggle-all)')
    );
    // si il n'y a pas d'enfants actionnables, on décoche le toggle
    if (children.length === 0) {
      toggle.checked = false;
      toggle.indeterminate = false;
      return;
    }
    const allChecked = children.every(cb => cb.checked);
    const noneChecked = children.every(cb => !cb.checked);
    toggle.checked = allChecked;
    // état indéterminé si ni tout coché ni tout décoché
    toggle.indeterminate = !allChecked && !noneChecked;
  }

  // met à jour tous les toggles
  function refreshAllToggles() {
    document.querySelectorAll('.toggle-all')
            .forEach(toggle => updateToggle(toggle));
  }
// → 1) Restaure les cases enfants d'après localStorage

var stored = localStorage.getItem('hidden_menus');

if (stored) {
    stored.split(',').forEach(function(href){
        var cb = document.querySelector(
            'input[name="hidden[]"][value="'+href+'"]'
            );
            if (cb) cb.checked = true;
            });
        }
  // on initialise l'état des toggles
  refreshAllToggles();

  // quand une case enfant change, on rafraîchit les toggles
  document.getElementById('menuForm')
          .addEventListener('change', function(e){
    if (e.target.matches('input[type=checkbox]:not(.toggle-all)')) {
      refreshAllToggles();
    }
  });

  // on branche les toggles eux-mêmes
  document.querySelectorAll('.toggle-all').forEach(cb => {
    // clic sur un toggle => coche/décoche tous ses items
    cb.addEventListener('change', function(){
      toggleAllItems(cb.dataset.target, cb.checked);
      // si tu veux enregistrer tout de suite, décommente :
      // document.getElementById('menuForm').submit();
      refreshAllToggles();
    });
  });
  

}); 

</script>

<script>
(function(){
  function initMenuForm() {
    const form = document.getElementById('menuForm');
    if (!form) return;

    // 1) Restauration des cases enfant
    const stored = localStorage.getItem('hidden_menus');
    if (stored) {
      stored.split(',').forEach(href => {
        const cb = form.querySelector('input[name="hidden[]"][value="' + href + '"]');
        if (cb) cb.checked = true;
      });
      // Recalcule des parent‐toggles
      if (typeof refreshAllToggles === 'function') {
        refreshAllToggles();
      }
    }

/**
 * Affiche et anime la barre de progression dans le <td id="ProgressBar">
 * pendant `delay` ms
 */
function showProgressBar(delay) {
  const parent = document.getElementById('ProgressBar');
  if (!parent) {
    console.warn('[ProgressBar] Élément #ProgressBar non trouvé');
    return;
  }
  // Vider le parent (supprime toute barre précédente)
  parent.innerHTML = '';

  // Crée conteneur et barre
  const container = document.createElement('div');
  container.id = 'save-progress-container';
  const bar = document.createElement('div');
  bar.id = 'save-progress-bar';
  container.appendChild(bar);

  // Insère dans le <td>
  parent.appendChild(container);

  // Lance la transition (après une frame)
  requestAnimationFrame(() => {
    bar.style.transition = `width ${delay}ms linear`;
    bar.style.width = '100%';
  });
}

/**
 * Sauvegarde + reload avec feedback visuel dans #ProgressBar
 */
function saveAndReload(delay = 1500) {
  const checked = Array.from(
    form.querySelectorAll('input[name="hidden[]"]:checked')
  ).map(cb => cb.value);

  localStorage.setItem('hidden_menus', checked.join(','));

  // on stocke qu’on vient de sauver
  sessionStorage.setItem('saved_flag', '1');
  

  clearTimeout(window._menuSaveTimeout);
  showProgressBar(delay);
  window._menuSaveTimeout = setTimeout(() => {
    window.location.reload();
  }, delay);
}

    // 2) Ne déclencher que sur vrai clic utilisateur
    form.addEventListener('change', function(e) {
      if (!e.isTrusted || !e.target.matches('input[name="hidden[]"]')) return;
      saveAndReload();
    });

    // 3) Boutons Tout cocher / Tout décocher
    document.getElementById('checkAll')?.addEventListener('click', () => {
      form.querySelectorAll('input[name="hidden[]"]').forEach(cb => cb.checked = true);
      saveAndReload(0);
    });
    document.getElementById('uncheckAll')?.addEventListener('click', () => {
      form.querySelectorAll('input[name="hidden[]"]').forEach(cb => cb.checked = false);
      saveAndReload(0);
    });


    // 4) Presets
      // 4) Presets (utilise vos variables presetSimple / presetAvance existantes)
  function applyPreset(list) {
    form.querySelectorAll('input[name="hidden[]"]').forEach(cb => {
      cb.checked = list.includes(cb.value);
    });
    saveAndReload(0);
  }
    document.getElementById('btnSimple')?.addEventListener('click', () => {
      applyPreset(presetSimple);
      saveAndReload(0);
    });
    document.getElementById('btnAvance')?.addEventListener('click', () => {
      applyPreset(presetAvance);
      saveAndReload(0);
    });
    document.getElementById('btnExpert')?.addEventListener('click', () => {
      form.querySelectorAll('input[name="hidden[]"]').forEach(cb => cb.checked = false);
      saveAndReload(0);
    });
  }

  // Si le document n'est pas encore prêt, on attend DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMenuForm);
  } else {
    // Sinon on peut init tout de suite, MAIS potentiellement avant que certains composants
    // (venant d'includes) soient injectés : on s'assure aussi via window.load
    initMenuForm();
  }

  // Enfin, on réessaie à la load du window pour les cas “lien direct”
  window.addEventListener('load', initMenuForm);
})();
document.addEventListener('DOMContentLoaded', () => {
  const flag = sessionStorage.getItem('saved_flag');

  const td = document.getElementById('saved');

  if (flag && td) {
    td.innerHTML = '✅ <strong><?= multi_get_text('SignatureSaved') ?>!</strong> 💾';
    // on efface immédiatement pour ne pas réafficher au prochain reload
    sessionStorage.removeItem('saved_flag');
    // ✔️ Après 2 s, on efface le message
    setTimeout(() => {
  td.classList.add('hidden');
  // ou pour tout enlever au bout d’1 s
  setTimeout(() => td.innerHTML = '', 500);
}, 2000);
  }
});
</script>

