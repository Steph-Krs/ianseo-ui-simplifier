<?php
/**
 * Generate CSS for Ultra Basic mode based on rules defined in elements.csv
 *
 * @return string Generated CSS rules
 */
function getUltraBasicCSS(): string
{
    $csvFile = __DIR__ . '/elements.csv';
    if (!is_readable($csvFile)) {
        error_log("getUltraBasicCSS: cannot read {$csvFile}");
        return '';
    }

    // Base animation for highlighting
    $cssOutput  .= "@keyframes blink {\n  25%, 75% { opacity: 1; }\n  50% { opacity: 0.2; }\n}\n";
    

    // Determine current request path and full URI (including query string)
    $requestPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $relativePath  = ltrim($requestPath, '/');             // e.g. "dir/page.php"
    $fullRequest   = ltrim($_SERVER['REQUEST_URI'], '/');  // e.g. "dir/page.php?param=..."

    // Open CSV and process each rule
    if (($handle = fopen($csvFile, 'r')) !== false) {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            // Extract fields, defaulting to empty strings
            [$pageRule, $selectorsCsv, $hideFlag, $highlightFlag] = array_map('trim', $row + ['', '', '', '']);
            if ($pageRule === '' || $selectorsCsv === '') {
                continue;
            }

            // Normalize rule and detect type
            $ruleRaw       = trim($pageRule, '/');
            $isDirectory   = substr($pageRule, -1) === '/';
            $isWildcard    = substr($pageRule, -1) === '*';
            $matches       = false;

            if ($isDirectory) {
                // Directory rule: match start of path
                $dir = rtrim($ruleRaw, '/');
                if (strpos($relativePath, $dir) === 0) {
                    $matches = true;
                }
            } elseif ($isWildcard) {
                // Wildcard rule: match start of full URI
                $prefix = rtrim($ruleRaw, '*');
                if (strpos($fullRequest, $prefix) === 0) {
                    $matches = true;
                }
            } else {
                // Exact file rule
                if ($relativePath === $ruleRaw) {
                    $matches = true;
                }
            }

            if (!$matches) {
                continue;
            }

            // Build selector list
            $selectors    = array_filter(array_map('trim', explode(',', $selectorsCsv)));
            $selectorList = implode(', ', $selectors);

            // Apply hide rule
            if ($hideFlag !== '') {
                $cssOutput .= "{$selectorList} { display: none !important; }\n";
            }
            // Apply highlight rule
            if ($highlightFlag !== '') {
                $cssOutput .= "{$selectorList} { background: limegreen !important; animation: blink 1.5s linear infinite; }\n";
            }
        }
        fclose($handle);
    }

    return $cssOutput;
}