<?php
/**
 * settings.php
 *
 * Handle module settings form, load configuration, and initialize display flags.
 *
 * PHP version 7.4+
 */

declare(strict_types=1);

// 1) Process Settings form submission (PRG pattern)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form_type'] ?? '') === 'settings'
) {
    // Check if Ultra Basic mode is enabled
    $isUltraBasicEnabled = isset($_POST['ultra_basic']) && $_POST['ultra_basic'] === '1';

    // Set cookie valid for 30 days
    setcookie(
        'ultra_basic',
        $isUltraBasicEnabled ? '1' : '0',
        time() + 30 * 24 * 3600,  // 30 days
        '/',                      // Path
        '',                       // Domain (current)
        false,                    // Secure (true if HTTPS)
        true                      // HttpOnly
    );

    // Redirect to clear POST data
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// 2) Load core configuration and output HTML head
require_once dirname(__DIR__, 5) . '/config.php';
include 'Common/Templates/head.php';

// 3) Read Ultra Basic flag from cookie
$isUltraBasic = (isset($_COOKIE['ultra_basic']) && $_COOKIE['ultra_basic'] === '1');

// 4) Debug output (optional; remove in production)
echo '<!-- DEBUG SETTINGS | ultra_basic cookie=' . ($_COOKIE['ultra_basic'] ?? 'none') . ' -->';

// Initialize hidden menus list (managed via localStorage in JS)
$hiddenMenus = [];

// 5) Handle module update action
if (isset($_POST['update_module'])) {
    $updateResult = updateUiSimplifierModule();
    echo '<div style="margin:1em 0; padding:0.5em; background:#eef; border:1px solid #99c;">'
       . htmlspecialchars($updateResult)
       . '</div>';
}

/**
 * Download, extract, and replace the module directory.
 *
 * @return string Success or error message.
 */
function updateUiSimplifierModule(): string
{
    $zipUrl      = 'https://github.com/Steph-Krs/ianseo-ui-simplifier/archive/refs/heads/main.zip';
    $tmpZipPath  = sys_get_temp_dir() . '/ianseo-ui-simplifier.zip';
    $tmpExtract  = sys_get_temp_dir() . '/ianseo-ui-simplifier';

    $statusMessages = [];

    // 1) Download ZIP archive
    $downloadedContent = @file_get_contents($zipUrl);
    if ($downloadedContent === false) {
        return '❌ Failed to download the ZIP archive.';
    }
    if (@file_put_contents($tmpZipPath, $downloadedContent) === false) {
        return '❌ Unable to write the ZIP archive to the temporary folder.';
    }
    $statusMessages[] = '📥 Archive ZIP downloaded.';

    // 2) Opening and extracting the archive
    $zip = new ZipArchive();
    if (($res = $zip->open($tmpZipPath)) !== true) {
        return '❌ Unable to open the ZIP archive. Error : ' . $res;
    }
    
    // Deleting a previous extraction
    if (is_dir($tmpExtract)) {
        deleteDirectoryRecursively($tmpExtract);
        $statusMessages[] = '🧹 Old temporary folder deleted.';
    }

    if (!$zip->extractTo($tmpExtract)) {
        return '❌ Archive extraction failed.';
    }
    $zip->close();
    $statusMessages[] = '📂 Extracted archive.';

    // 3) Copy extracted files to the module directory
    $sourceDir      = $tmpExtract . '/ianseo-ui-simplifier-main';
    $destinationDir = dirname(__DIR__, 2);

    if (!is_dir($sourceDir)) {
        return '❌ Unexpected archive structure: source folder not found.';
    }
    
    recurseCopy($sourceDir, $destinationDir);
    $statusMessages[] = '♻️ Files copied successfully.';

    // 4) Cleaning up temporary files
    @unlink($tmpZipPath);
    deleteDirectoryRecursively($tmpExtract);
    $statusMessages[] = '🧹 Temporary files cleaned up.';

    return '✅Module successfully updated! <br>' . implode('<br>', $statusMessages) . '<br>🔄 Reload the page if necessary.';
}

/**
 * Recursively copy files and directories from source to destination.
 *
 * @param string $src Source directory
 * @param string $dst Destination directory
 * @return void
 */
function recurseCopy(string $src, string $dst): void
{
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $srcPath = "{$src}/{$file}";
        $dstPath = "{$dst}/{$file}";
        if (is_dir($srcPath)) {
            recurseCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

/**
 * Recursively delete a directory and all its contents.
 *
 * @param string $directory
 * @return void
 */
function deleteDirectoryRecursively(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursively($path);
        } else {
            unlink($path);
        }
    }
    rmdir($directory);
}

// -------------------------------------------------

// 3) Read CSV data
$csvFile = 'menus.csv';
$rows    = array_map(fn($line) => str_getcsv($line, ';'), file($csvFile, FILE_SKIP_EMPTY_LINES));
// Remove header row
array_shift($rows);

// -------------------------------------------------

/**
 * Remove any trailing semicolon content and trim whitespace.
 *
 * @param string $value
 * @return string
 */
function cleanKey(string $value): string
{
    return preg_replace('/;.*$/', '', trim($value));
}

/**
 * Escape a string for use in CSS selectors by converting non-alphanumeric characters
 * to their Unicode codepoint in hexadecimal.
 *
 * @param string $input
 * @return string
 */
function cssEscape(string $input): string
{
    $output = '';
    $length = mb_strlen($input, 'UTF-8');
    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($input, $i, 1, 'UTF-8');
        if (preg_match('/^[0-9A-Za-z_-]$/', $char)) {
            $output .= $char;
        } else {
            $codePoint = mb_ord($char, 'UTF-8');
            $hex       = dechex($codePoint);
            $output   .= '\\' . $hex . ' ';
        }
    }
    return $output;
}

/**
 * Append the TourCode session parameter to URLs containing ?Tour= or ?TourId=.
 *
 * @param string $url
 * @return string
 */
function appendTourCode(string $url): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $tourCode = $_SESSION['TourCode'] ?? '';
    if ($tourCode === '' || $url === '') {
        return $url;
    }
    if (strpos($url, '.php?Tour=') !== false || strpos($url, '.php?TourId=') !== false) {
        // Append TourCode at the end of the query string
        return '/' . $url . $tourCode;
    }
    return '/' . $url;
}

// Initialize data structures
$menuTree      = [];
$simplePresets = [];
$advancedPresets = [];

foreach ($rows as $row) {
    // Extract all columns at once
    [$level1, $level2, $level3, $rawUrl, $simpleFlag, $advancedFlag] = array_map('trim', $row);

    // 1) Translation keys
    $key1 = cleanKey($level1);
    $key2 = cleanKey($level2);
    $key3 = cleanKey($level3);

    // Skip if no top-level menu
    if ($key1 === '') {
        continue;
    }

    // 2) Prepare URL (append TourCode and escape for CSS/HTML)
    $urlWithCode = appendTourCode($rawUrl);
    $cssSelector = cssEscape($urlWithCode);
    $htmlSelector = htmlspecialchars($cssSelector, ENT_QUOTES, 'UTF-8');

    // 3) Populate the menu tree structure
    if (!isset($menuTree[$key1])) {
        $menuTree[$key1] = ['children' => []];
    }
    if ($key2 !== '') {
        if (!isset($menuTree[$key1]['children'][$key2])) {
            $menuTree[$key1]['children'][$key2] = [
                'url'      => null,
                'children' => []
            ];
        }
        if ($key3 !== '') {
            // Level 3 link
            $menuTree[$key1]['children'][$key2]['children'][$key3] = $urlWithCode;
        } else {
            // Level 2 link
            $menuTree[$key1]['children'][$key2]['url'] = $urlWithCode;
        }
    }

    // 4) Handle presets based on columns 5 & 6
    if (strcasecmp($simpleFlag, 'X') === 0) {
        $simplePresets[] = $urlWithCode;
    }
    if (strcasecmp($advancedFlag, 'X') === 0) {
        $advancedPresets[] = $urlWithCode;
    }
}

// Remove duplicates from preset lists
$simplePresets   = array_values(array_unique($simplePresets));
$advancedPresets = array_values(array_unique($advancedPresets));

/**
 * Log data to the browser console for debugging.
 *
 * @param mixed $menuTree
 */
function debugToConsole($menuTree): void
{
    $output = is_array($menuTree) ? implode(',', $menuTree) : $menuTree;
    echo "<script>console.log('Debug: " . addslashes($output) . "');</script>";
}


/**
 * Retrieve a translation string by key, supporting custom language files
 * and falling back to Ianseo’s get_text().
 *
 * @param string $key Translation key
 * @return string Translated text or the raw key if not found
 */
function multiGetText(string $key): string
{
    // 1) Determine language code: extract from get_text() placeholder
    $langCode = 'en';
    $placeholder = get_text($key);
        // strip any HTML tags (e.g. <b>…</b>)
        $clean = strip_tags($placeholder);
        // match "[[KEY]@xx@"
        if (preg_match('/\[\[[^\]]+\]@\[(?<lang>[a-z]{2})\]@/i', $clean, $m)) {
            $langCode = strtolower($m['lang']);
        }

    // 2) Try custom language file: App/Languages/{lang}.php should define $lang[...] arrays
    $moduleDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $langFile  = "{$moduleDir}/App/Languages/{$langCode}.php";
    if (is_readable($langFile)) {
        $lang = [];
        include $langFile;
        if (!empty($lang[$key])) {
            return $lang[$key];
        }
    }

    // 3) Fallback to Ianseo's default domain
    $text = get_text($key);
    $isPlaceholder = substr_count($text, ']@[') >= 2;
    if ($text !== $key && !$isPlaceholder && $text !== '') {
        return $text;
    }

    // 4) Try other Ianseo domains in order
    $domains = [
        'Api','Awards','BackNumbers','Boinx','Common','DateTime',
        'Errors','Help','HTT','Ianseo','InfoSystem','Install',
        'IOC_Codes','ISK-App','ISK','Languages','ODF','Records',
        'RoundRobin','RunArchery','ServiceErrors','Tournament',
    ];
    foreach ($domains as $domain) {
        $t = get_text($key, $domain);
        if ($t !== $key && substr_count($t, ']@[') < 2 && $t !== '') {
            return $t;
        }
    }

    // 5) Nothing found: return the raw key
    return get_text($key);
}
?>
<style>
    /* Reset paragraph top margin */
    p {
        margin-top: 0;
    }

    /* “Select All” button styling */
    .Chck_all {
        padding: 6px;
        width: 40%;
        text-align: center;
    }

    /* Typography for nested summaries */
    details > summary.lvl1 {
        font-size: 1.2em;
        cursor: pointer;
    }
    .lvl2-item,
    details > summary.lvl2 {
        margin-left: 20px;
        font-size: 1.1em;
        cursor: pointer;
    }
    details > div.lvl3 {
        margin-left: 50px;
        font-size: 1.1em;
        cursor: pointer;
    }
    .lvl3 {
        font-size: 1em;
    }

    /* Indentation and layout for list items */
    .lvl2-item {
        margin-left: 20px;
        display: flex;
        align-items: center;
    }
    .lvl2-item label {
        display: flex;
        align-items: center;
    }
    .lvl3 {
        margin-left: 40px;
        display: flex;
        align-items: center;
    }
    input[type="checkbox"] {
        margin-right: 0.5em;
    }

    /* Light gray progress bar container */
    #save-progress-container {
        width: 100%;
        height: 12px;
        background: #e0e0e0;
        overflow: hidden;
    }

    /* Green progress bar fill */
    #save-progress-bar {
        width: 0;
        height: 100%;
        background: #4caf50;
        transition: width linear;
    }

    /* Fade-out transition for “saved” indicator */
    #saved {
        transition: opacity 0.5s ease;
    }

    /* Utility class to hide elements */
    .hidden {
        opacity: 0;
    }

    /* Blinking highlight effect */
    .blinking {
        background: limegreen !important;
        animation: blink 1.5s linear infinite;
    }
</style>

<table class="Tabella">
  <tbody>
    <tr>
      <!-- LEFT COLUMN -->
      <td style="width: 35%; vertical-align: top">
        <table class="Tabella">
          <tbody>
            <tr>
              <th class="Title" colspan="3">
                <?= htmlspecialchars(multiGetText('TVPresetChains'), ENT_QUOTES, 'UTF-8') ?>
              </th>
            </tr>
            <tr>
              <th>
                <button id="btnSimple" class="blinking">
                  <?= htmlspecialchars(multiGetText('Base'), ENT_QUOTES, 'UTF-8') ?>
                </button>
              </th>
              <td>
                <table style="width: 100%">
                  <tbody>
                    <tr>
                      <th style="width: 100%">
                        <b><?= htmlspecialchars(multiGetText('UIS-BaseUser'), ENT_QUOTES, 'UTF-8') ?></b>
                      </th>
                    </tr>
                    <tr>
                      <td>
                        <?= htmlspecialchars(multiGetText('UIS-BaseLimits'), ENT_QUOTES, 'UTF-8') ?>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <p><i><?= htmlspecialchars(multiGetText('UIS-BaseQuote'), ENT_QUOTES, 'UTF-8') ?></i> – <?= htmlspecialchars(multiGetText('UIS-BaseQuoteFrom'), ENT_QUOTES, 'UTF-8') ?></p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
              <td style="vertical-align: top; text-align: center;">
                <table style="width: 100%">
                  <tbody>
                    <tr>
                      <th style="width: 100%">
                        <?= htmlspecialchars(multiGetText('UIS-TutoMode'), ENT_QUOTES, 'UTF-8') ?>
                      </th>
                    </tr>
                    <tr>
                      <td>
                        <form method="post" action="">
                          <input type="hidden" name="form_type" value="settings">
                          <div class="form-group">
                            <label class="blinking">
                              <input
                                type="checkbox"
                                name="ultra_basic"
                                value="1"
                                <?= $isUltraBasic ? 'checked' : '' ?>
                                onchange="this.form.querySelector('button[type=submit]').click()"
                              >
                            </label>
                            <button type="submit" class="btn btn-primary" style="display: none;">
                              save
                            </button>
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
                <button id="btnAvance">
                  <?= htmlspecialchars(multiGetText('AdvancedMode'), ENT_QUOTES, 'UTF-8') ?>
                </button>
              </th>
              <td colspan="2">
                <table style="width: 100%">
                  <tbody>
                    <tr>
                      <th style="width: 100%">
                        <b><?= htmlspecialchars(multiGetText('UIS-AdvencedUser'), ENT_QUOTES, 'UTF-8') ?></b>
                      </th>
                    </tr>
                    <tr>
                      <td>
                        <?= htmlspecialchars(multiGetText('UIS-AdvencedLimits'), ENT_QUOTES, 'UTF-8') ?>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <p><i><?= htmlspecialchars(multiGetText('UIS-AdvancedQuote'), ENT_QUOTES, 'UTF-8') ?></i> – <?= htmlspecialchars(multiGetText('UIS-AdvancedQuoteFrom'), ENT_QUOTES, 'UTF-8') ?></p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <th>
                <button id="btnExpert">
                  Expert Mode
                </button>
              </th>
              <td colspan="2">
                <table style="width: 100%">
                  <tbody>
                    <tr>
                      <th style="width: 100%">
                        <b><?= htmlspecialchars(multiGetText('UIS-ExpertUser'), ENT_QUOTES, 'UTF-8') ?></b>
                      </th>
                    </tr>
                    <tr>
                      <td>
                        <?= htmlspecialchars(multiGetText('UIS-ExpertLimits'), ENT_QUOTES, 'UTF-8') ?>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <p><i><?= htmlspecialchars(multiGetText('UIS-ExpertQuote'), ENT_QUOTES, 'UTF-8') ?></i> – <?= htmlspecialchars(multiGetText('UIS-ExpertQuoteFrom'), ENT_QUOTES, 'UTF-8') ?></p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td id="ProgressBar" colspan="3"></td>
            </tr>
            <tr>
              <td id="saved" style="text-align:center;" colspan="3"></td>
            </tr>
            <tr>
              <td colspan="3">
                <h3><?= htmlspecialchars(multiGetText('UIS-WhyTitle'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p>
                  <?= htmlspecialchars(multiGetText('UIS-WhyMenus'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p>
                  <?= htmlspecialchars(multiGetText('UIS-WhyTutorial'), ENT_QUOTES, 'UTF-8') ?>
                </p>
              </td>
            </tr>
            <tr class="Divider" style="height:5px;">
              <td colspan="3"></td>
            </tr>
            <tr>
              <th colspan="3">
                <?= htmlspecialchars(multiGetText('UIS-Update'), ENT_QUOTES, 'UTF-8') ?>
              </th>
            </tr>
            <tr>
              <td id="update" colspan="3">
                <form method="post" style="text-align:center;">
                  <button type="submit" name="update_module" class="blinking">
                    🔄 <?= htmlspecialchars(multiGetText('UIS-UpdateModeAndSets'), ENT_QUOTES, 'UTF-8') ?>
                  </button>
                </form>
              </td>
            </tr>
            <tr>
              <td id="updateCSV" style="text-align:center;" colspan="3">
                <?php
                  // Show upload form
                  echo '
                    <form method="post" enctype="multipart/form-data">
                      <label for="csv_file">Import a custom CSV (menus.csv or elements.csv, separator=";"):</label><br>
                      <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                      <button type="submit" name="upload_csv">Upload</button>
                    </form>
                  ';

                  // Handle upload
                  if (isset($_POST['upload_csv'])) {
                    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                      echo '<p style="color:red;">❌ No file received or upload error.</p>';
                    } else {
                      $tmpFile = $_FILES['csv_file']['tmp_name'];
                      $fileName = basename(strtolower($_FILES['csv_file']['name']));
                      $allowed = ['menus.csv', 'elements.csv'];
                      if (!in_array($fileName, $allowed, true)) {
                        echo '<p style="color:red;">❌ Invalid filename; must be menus.csv or elements.csv.</p>';
                      } else {
                        $minCols = $fileName === 'menus.csv' ? 6 : 4;
                        if (($h = fopen($tmpFile, 'r')) !== false) {
                          $cols = fgetcsv($h, 0, ';');
                          fclose($h);
                          $count = is_array($cols) ? count($cols) : 0;
                        } else {
                          $count = 0;
                        }
                        if ($count < $minCols) {
                          echo "<p style=\"color:red;\">❌ Invalid CSV: found {$count} columns, minimum {$minCols} required.</p>";
                        } else {
                          $destination = __DIR__ . '/' . $fileName;
                          if (move_uploaded_file($tmpFile, $destination)) {
                            echo '<p style="color:green;">✅ File '.htmlspecialchars($fileName).' uploaded successfully.</p>';
                          } else {
                            echo '<p style="color:red;">❌ Could not move file to '.$destination.'</p>';
                          }
                        }
                      }
                    }
                  }
                ?>
              </td>
            </tr>
            <tr class="Divider" style="height:5px;">
              <td colspan="3"></td>
            </tr>
            <tr>
              <td colspan="3">
                <table class="Tabella">
                  <tbody>
                    <tr>
                      <th id="download-menus" style="cursor:pointer;">
                        menus.csv
                      </th>
                      <td>
                        <table class="Tabella">
                          <tbody>
                            <tr>
                              <td style="font-size:0.7em; text-align:center;">Name Level1</td>
                              <td style="font-size:0.7em; text-align:center;">Name Level2</td>
                              <td style="font-size:0.7em; text-align:center;">Name Level3</td>
                              <td style="font-size:0.7em; text-align:center;">URL</td>
                              <td style="font-size:0.7em; text-align:center;">Base</td>
                              <td style="font-size:0.7em; text-align:center;">Advanced</td>
                            </tr>
                            <tr>
                              <td style="font-size:0.7em; text-align:center;" colspan="3">from ianseo Common/Menu.php</td>
                              <td style="font-size:0.7em; text-align:center;">menu link</td>
                              <td style="font-size:0.7em; text-align:center;" colspan="2">X or nothing</td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    <tr>
                  </tbody>
                </table>
                <table class="Tabella">
                  <tbody>
                    <tr>
                      <th id="download-elements" style="cursor:pointer;">
                        elements.csv
                      </th>
                      <td>
                        <table class="Tabella">
                          <tbody>
                            <tr>
                              <td style="font-size:0.7em; text-align:center;">Path</td>
                              <td style="font-size:0.7em; text-align:center;">querySelector</td>
                              <td style="font-size:0.7em; text-align:center;">Delete</td>
                              <td style="font-size:0.7em; text-align:center;">Mark</td>
                              <td style="font-size:0.7em; text-align:center;">Comment</td>
                            </tr>
                            <tr>
                              <td style="font-size:0.7em; text-align:center;" colspan="2"></td>
                              <td style="font-size:0.7em; text-align:center;" colspan="2">X or nothing</td>
                              <td style="font-size:0.7em; text-align:center;">not used</td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    <tr>
                  </tbody>
                </table>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
      <!-- RIGHT COLUMN -->
      <td style="width: 65%; vertical-align: top">
        <table class="Tabella">
          <tbody>
            <tr>
              <th class="Title" colspan="2">
                <?= multiGetText('SetupManually') ?>
              </th>
            </tr>
            <tr>
              <td style="text-align:center; justify-content: space-evenly">
                <button id="checkAll" class="Chck_all">
                  ☑️ <?= htmlspecialchars(multiGetText('SelectAll'), ENT_QUOTES, 'UTF-8') ?> 🔒
                </button>
                <button id="uncheckAll" class="Chck_all">
                  🔲 <?= htmlspecialchars(multiGetText('NoRowSelected'), ENT_QUOTES, 'UTF-8') ?> 👁️
                </button>
              </td>
            </tr>
            <tr>
              <td>
                <?php $idx1 = 0; ?>
                <form method="post" id="menuForm" target="hidden_iframe">
                  <!-- hidden field to indicate “menu” form -->
                  <input type="hidden" name="form_type" value="menu">
                  <iframe name="hidden_iframe" src="about:blank" style="display:none;"></iframe>
                  <button type="submit" style="display:none;">
                    💾 <?= htmlspecialchars(multiGetText('SettingsSaveConfig'), ENT_QUOTES, 'UTF-8') ?>
                  </button>
                  <?php foreach ($menuTree as $lvl1 => $sub1): ?>
                    <?php $idx1++; ?>
                    <details id="menu1-<?= $idx1 ?>">
                      <summary class="lvl1">
                        <?= htmlspecialchars(multiGetText($lvl1), ENT_QUOTES, 'UTF-8') ?>
                        <span style="font-size:0.8em;">
                          [ <input
                              type="checkbox"
                              name="hidden[]"
                              class="toggle-all"
                              data-target="menu1-<?= $idx1 ?>"
                              style="margin-left:auto;"
                            > Hide/show all ]
                        </span>
                      </summary>
                      <?php
                        $idx2 = 0;
                        foreach ($sub1['children'] as $lvl2 => $info2):
                          $hasKids = !empty($info2['children']);
                          $url2    = $info2['url'] ?? '';
                          $path2   = '/' . ltrim($url2, '/');
                          $chk2    = $url2 && in_array($path2, $hiddenMenus) ? 'checked' : '';
                      ?>
                        <?php if ($hasKids): ?>
                          <?php $idx2++; ?>
                          <details id="menu2-<?= $idx1 ?>-<?= $idx2 ?>">
                            <summary class="lvl2">
                              <?php if ($url2): ?>
                                <label>
                                  <input
                                    type="checkbox"
                                    name="hidden[]"
                                    value="<?= htmlspecialchars($path2, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $chk2 ?>
                                  >
                                </label>
                              <?php endif; ?>
                              <?= htmlspecialchars(multiGetText($lvl2), ENT_QUOTES, 'UTF-8') ?>
                              <span style="font-size:0.8em;">
                                [ <input
                                    type="checkbox"
                                    name="hidden[]"
                                    class="toggle-all"
                                    data-target="menu2-<?= $idx1 ?>-<?= $idx2 ?>"
                                    style="margin-left:auto;"
                                  > Hide/show submenu ]
                              </span>
                            </summary>
                            <?php foreach ($info2['children'] as $lvl3 => $url3): ?>
                              <?php $path3 = '/' . ltrim($url3, '/'); ?>
                              <div class="lvl3">
                                <label>
                                  <input
                                    type="checkbox"
                                    name="hidden[]"
                                    value="<?= htmlspecialchars($path3, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= in_array($path3, $hiddenMenus) ? 'checked' : '' ?>
                                  >
                                  <?= htmlspecialchars(multiGetText($lvl3), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                              </div>
                            <?php endforeach; ?>
                          </details>
                        <?php else: ?>
                          <div class="lvl2-item">
                            <?php if ($url2): ?>
                              <label>
                                <input
                                  type="checkbox"
                                  name="hidden[]"
                                  value="<?= htmlspecialchars($path2, ENT_QUOTES, 'UTF-8') ?>"
                                  <?= $chk2 ?>
                                >
                                <?= htmlspecialchars(multiGetText($lvl2), ENT_QUOTES, 'UTF-8') ?>
                              </label>
                            <?php else: ?>
                              <?= htmlspecialchars(multiGetText($lvl2), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </details>
                  <?php endforeach; ?>
                </form>
              </td>
            </tr>
            <tr class="Divider" style="height:20px;">
              <td></td>
            </tr>
            <tr>
              <td>
                <p><i><?= htmlspecialchars(multiGetText('UIS-AutoSave'), ENT_QUOTES, 'UTF-8') ?></i></p>
                <p><i><?= htmlspecialchars(multiGetText('UIS-MultipleChecks'), ENT_QUOTES, 'UTF-8') ?></i></p>
                <p><i><?= htmlspecialchars(multiGetText('UIS-Menus'), ENT_QUOTES, 'UTF-8') ?>🔒<?= htmlspecialchars(multiGetText('UIS-HidenMenus'), ENT_QUOTES, 'UTF-8') ?></i></p>
                <p><i><?= htmlspecialchars(multiGetText('UIS-Elements'), ENT_QUOTES, 'UTF-8') ?>🔒<?= htmlspecialchars(multiGetText('UIS-HidenElements'), ENT_QUOTES, 'UTF-8') ?></i></p>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>


<script>
  // Update inforations in #saved
  document.getElementById('saved').innerHTML = '<?= updateUiSimplifierModule(); ?>';

  // Download CSV buttons
  document.getElementById('download-menus').addEventListener('click', function() {
    // Redirect to download menus.csv
    window.location.href = 'download_csv.php?file=menus.csv';
  });
  document.getElementById('download-elements').addEventListener('click', function() {
    // Redirect to download elements.csv
    window.location.href = 'download_csv.php?file=elements.csv';
  });

  // Preset URL lists from PHP
  const simplePresets = <?= json_encode(array_values(array_unique($simplePresets)), JSON_HEX_TAG) ?>;
  const advancedPresets = <?= json_encode(array_values(array_unique($advancedPresets)), JSON_HEX_TAG) ?>;

  document.addEventListener('DOMContentLoaded', function() {
    // Utility: toggle all child checkboxes within a container
    function toggleAllItems(containerId, checked) {
      const container = document.getElementById(containerId);
      if (!container) return;
      container.querySelectorAll('input[type="checkbox"]:not(.toggle-all)')
               .forEach(checkbox => checkbox.checked = checked);
    }

    // Utility: update a master toggle checkbox based on its child checkboxes
    function updateToggle(masterCheckbox) {
      const targetId = masterCheckbox.dataset.target;
      const container = document.getElementById(targetId);
      if (!container) return;
      const children = Array.from(
        container.querySelectorAll('input[type="checkbox"]:not(.toggle-all)')
      );
      if (children.length === 0) {
        masterCheckbox.checked = false;
        masterCheckbox.indeterminate = false;
        return;
      }
      const allChecked = children.every(cb => cb.checked);
      const noneChecked = children.every(cb => !cb.checked);
      masterCheckbox.checked = allChecked;
      masterCheckbox.indeterminate = !allChecked && !noneChecked;
    }

    // Refresh all master toggles on the page
    function refreshAllToggles() {
      document.querySelectorAll('.toggle-all')
              .forEach(cb => updateToggle(cb));
    }

    // 1) Restore child checkbox states from localStorage
    const storedHidden = localStorage.getItem('hidden_menus');
    if (storedHidden) {
      storedHidden.split(',').forEach(function(href) {
        const checkbox = document.querySelector(
          'input[name="hidden[]"][value="' + href + '"]'
        );
        if (checkbox) checkbox.checked = true;
      });
    }

    // Initialize master toggles
    refreshAllToggles();

    // When any child checkbox changes, refresh master toggles
    document.getElementById('menuForm')
            .addEventListener('change', function(e) {
      if (e.target.matches('input[type="checkbox"]:not(.toggle-all)')) {
        refreshAllToggles();
      }
    });

    // Wire up master toggle checkboxes
    document.querySelectorAll('.toggle-all').forEach(function(masterCb) {
      // When master toggle changes, toggle all its items
      masterCb.addEventListener('change', function() {
        toggleAllItems(masterCb.dataset.target, masterCb.checked);
        // If you want to auto-submit the form, uncomment the next line:
        // document.getElementById('menuForm').submit();
        refreshAllToggles();
      });
    });
  });
</script>

<script>
(function() {
    /**
     * Initialize the menu form: restore hidden items, wire up event handlers.
     */
    function initMenuForm() {
        const form = document.getElementById('menuForm');
        if (!form) return;

        // 1) Restore child checkboxes from localStorage
        const storedHidden = localStorage.getItem('hidden_menus');
        if (storedHidden) {
            storedHidden.split(',').forEach(href => {
                const checkbox = form.querySelector('input[name="hidden[]"][value="' + href + '"]');
                if (checkbox) checkbox.checked = true;
            });
            if (typeof refreshAllToggles === 'function') {
                refreshAllToggles();
            }
        }

        /**
         * Show and animate the progress bar inside #ProgressBar for the given duration.
         * @param {number} delay Duration in ms
         */
        function showProgressBar(delay) {
            const containerCell = document.getElementById('ProgressBar');
            if (!containerCell) {
                console.warn('[ProgressBar] Element #ProgressBar not found');
                return;
            }
            containerCell.innerHTML = '';

            const progressContainer = document.createElement('div');
            progressContainer.id = 'save-progress-container';
            const progressBar = document.createElement('div');
            progressBar.id = 'save-progress-bar';
            progressContainer.appendChild(progressBar);
            containerCell.appendChild(progressContainer);

            requestAnimationFrame(() => {
                progressBar.style.transition = `width ${delay}ms linear`;
                progressBar.style.width = '100%';
            });
        }

        /**
         * Save the current hidden menu selections and reload with visual feedback.
         * @param {number} [delay=1500] Reload delay in ms
         */
        function saveAndReload(delay = 1500) {
            const hiddenValues = Array.from(
                form.querySelectorAll('input[name="hidden[]"]:checked')
            ).map(cb => cb.value);

            localStorage.setItem('hidden_menus', hiddenValues.join(','));
            sessionStorage.setItem('saved_flag', '1');

            clearTimeout(window.menuSaveTimeout);
            showProgressBar(delay);
            window.menuSaveTimeout = setTimeout(() => {
                window.location.reload();
            }, delay);
        }

        // 2) Trigger saveAndReload on user checkbox changes
        form.addEventListener('change', function(e) {
            if (!e.isTrusted || !e.target.matches('input[name="hidden[]"]')) return;
            saveAndReload();
        });

        // 3) “Check All” / “Uncheck All” buttons
        document.getElementById('checkAll')?.addEventListener('click', () => {
            form.querySelectorAll('input[name="hidden[]"]').forEach(cb => cb.checked = true);
            saveAndReload(0);
        });
        document.getElementById('uncheckAll')?.addEventListener('click', () => {
            form.querySelectorAll('input[name="hidden[]"]').forEach(cb => cb.checked = false);
            saveAndReload(0);
        });

        // 4) Preset buttons (use simplePresets / advancedPresets arrays)
        function applyPreset(list) {
            form.querySelectorAll('input[name="hidden[]"]').forEach(cb => {
                cb.checked = list.includes(cb.value);
            });
            saveAndReload(0);
        }
        document.getElementById('btnSimple')?.addEventListener('click', () => {
            applyPreset(simplePresets);
        });
        document.getElementById('btnAvance')?.addEventListener('click', () => {
            applyPreset(advancedPresets);
        });
        document.getElementById('btnExpert')?.addEventListener('click', () => {
            form.querySelectorAll('input[name="hidden[]"]').forEach(cb => cb.checked = false);
            saveAndReload(0);
        });
    }

    // Run initMenuForm on DOMContentLoaded or immediately if already ready, 
    // with a fallback on window.load for direct links
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenuForm);
    } else {
        initMenuForm();
    }
    window.addEventListener('load', initMenuForm);
})();

// 5) Show “Saved” confirmation on next load
document.addEventListener('DOMContentLoaded', () => {
    const savedFlag = sessionStorage.getItem('saved_flag');
    const savedElement = document.getElementById('saved');

    if (savedFlag && savedElement) {
        savedElement.innerHTML = '✅ <strong><?= multiGetText('SignatureSaved') ?>!</strong> 💾';
        sessionStorage.removeItem('saved_flag');

        setTimeout(() => {
            savedElement.classList.add('hidden');
            setTimeout(() => {
                savedElement.innerHTML = '';
            }, 500);
        }, 2000);
    }
});
</script>