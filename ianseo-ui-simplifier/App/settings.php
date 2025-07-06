<?php
// 1) Bootstrap ianseo
require_once dirname(__FILE__, 5) . '/config.php';
include 'Common/Templates/head.php';

// 2) Session & POST
$hidden = $_SESSION['hidden_menus'] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['hidden_menus'] = $_POST['hidden'] ?? [];
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
    /* layout 2 colonnes */
    #sm_container {
        display:flex;
        gap:1em;
    }
    
    /* gauche */
    #sm_left { flex:2; border: solid; border-color: #117;border-width: 1px; padding : 5px;margin:5px}
    #sm_left button{
        padding:6px;
        margin:6px;
        text-align:center;
    }
    #sm_left h2{
        text-align:center;
    }
    .Chck_all {
        padding:6px;width:40%;text-align:center;
    }
    #savedMsg{
        margin: 0 5%;padding:5px;text-align:center;font-weight:900;color:white; background-color:#117; opacity:1; transition:opacity 0.5s ease;
    }
    /* droite */
    #sm_right { flex:3; border: solid; border-color: #117;border-width: 1px; padding : 5px;margin:5px}
    #sm_right h2{
        text-align:center;
    }
    #bloc_rapide {
        display:flex;align-items:center;justify-content: space-evenly;
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
</style>
<?php if (isset($_GET['saved'])): ?>
        <p id="savedMsg">
            ✅ <?= htmlspecialchars(multi_get_text('SignatureSaved')) ?> ✅
        </p>
        <script>
            document.addEventListener('DOMContentLoaded', function(){
            setTimeout(function(){
                const msg = document.getElementById('savedMsg');
                if (msg) msg.style.opacity = '0';
            }, 3000);
            });
        </script>
    <?php endif; ?>
<div id="sm_container">

  <!-- GAUCHE -->
  <div id="sm_left">
    
    <h2><?= htmlspecialchars(multi_get_text('TVPresetChains'), ENT_QUOTES, 'UTF-8') ?></h2>
    <button id="btnSimple"><?= htmlspecialchars(multi_get_text('Base'), ENT_QUOTES, 'UTF-8') ?></button>
    <b>Pour les novices :</b> limité à un tir de classement.
    <p><i>C'est pas faux!🗡️</i></p>
    <br><br>
    <button id="btnAvance"><?= htmlspecialchars(multi_get_text('AdvancedMode'), ENT_QUOTES, 'UTF-8') ?></button>
    <b>Pour les initiés :</b> matchs, téléphones, affichages en direct, accréditations/dossards, mode présentateur.
    <p><i>Un grand pouvoir implique de grandes responsabilités.🕷️</i></p>
    <br><br>
    <button id="btnExpert">Mode Expert</button>
    <b>Pour les audacieux :</b> tout IANSEO sans limitation.
    <p><i>La route ? Là où on va, on n'a pas besoin de route...🛹</i></p>
    <br><br>
    <h3>À quoi sert ce module ?</h3>
    <p>
        Ce module vous permet de personnaliser quels menus apparaissent dans l’interface en fonction de vos besoins (simplifié, avancé, expert, ou personnalisé).
    </p>
    <p><i>
        La sauvegarde se fait automatiquement après chaque modification dans un delais de 1,5s.
    </i></p>
    <p><i>
        Attention, si plusieurs menus mènent au même lien dans ianseo. Il faut tous les décocher pour que ce soit sauvegardé.
    </i></p>
  </div>

  <!-- DROITE -->
<div id="sm_right">
    <h2><?= multi_get_text('SetupManually') ?></h2>
        <div id="bloc_rapide">
            <button id="checkAll" class="Chck_all">☑️ <?= htmlspecialchars(multi_get_text('SelectAll'), ENT_QUOTES, 'UTF-8') ?> 🔒</button>
            <button id="uncheckAll" class="Chck_all">🔲 <?= htmlspecialchars(multi_get_text('NoRowSelected'), ENT_QUOTES, 'UTF-8') ?> 👁️</button>
        </div>
    

    <?php $idx1 = 0;?>
    <form method="post" id="menuForm" target="hidden_iframe">
    
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
                >Cacher/afficher tout le menu]</span>
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
</div>

</div>
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


// iframe → reload en GET?saved=1
document.querySelector('iframe[name="hidden_iframe"]').addEventListener('load',()=>{
  const u=new URL(window.location.href);
  u.searchParams.set('saved','1');
  window.location.href=u;
});



// tout cocher / décocher
document.getElementById('checkAll').onclick = ()=> {
  document.querySelectorAll('#menuForm input[type=checkbox]').forEach(cb=>cb.checked=true);
  document.getElementById('menuForm').submit();
};
document.getElementById('uncheckAll').onclick = ()=> {
  document.querySelectorAll('#menuForm input[type=checkbox]').forEach(cb=>cb.checked=false);
  document.getElementById('menuForm').submit();
};
document.querySelectorAll('#menuForm input[type=checkbox]').forEach(function(cb){
  cb.addEventListener('change', function(){
    // on annule un éventuel timeout existant
    if (window._menuSaveTimeout) clearTimeout(window._menuSaveTimeout);
    // on planifie le submit dans 1.5 secondes
    window._menuSaveTimeout = setTimeout(function(){
      document.getElementById('menuForm').submit();
    }, 1500);
  });
});

// boutons modes simplifié/avancé/expert à adapter
// fonction utilitaire pour appliquer un preset
function applyPreset(list) {
  document.querySelectorAll('#menuForm input[type=checkbox]').forEach(cb => {
    cb.checked = list.includes(cb.value);
    //console.log("list coché = ",list);
    //console.log("cb.value = ",cb.value);
  });
  // on soumet via iframe (reload automatique)
  document.getElementById('menuForm').submit();
}

// bouton “Mode simplifié”
document.getElementById('btnSimple').onclick = () => {
    //console.log("mode simplifié = ",presetSimple);
  applyPreset(presetSimple);
  
};

// bouton “Mode avancé”
document.getElementById('btnAvance').onclick = () => {
  applyPreset(presetAvance);
};

// bouton “Mode expert” : décoche tout
document.getElementById('btnExpert').onclick = () => {
  document.querySelectorAll('#menuForm input[type=checkbox]').forEach(cb => cb.checked = false);
  document.getElementById('menuForm').submit();
};
</script>
