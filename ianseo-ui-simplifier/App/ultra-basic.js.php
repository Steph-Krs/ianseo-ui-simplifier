<?php
/**
 * getUltraBasicJS.php
 *
 * Returns the JS indicator string for Ultra Basic mode based on CSV rules in elements.csv.
 *
 * PHP version 7.4+
 *
 * @return string Indicator text or empty string if none
 */
function getUltraBasicJS(): string
{
    $csvFile = __DIR__ . '/elements.csv';
    if (!is_readable($csvFile)) {
        error_log("getUltraBasicJS: cannot read {$csvFile}");
        return '';
    }

    // Normalize request URI and path
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);    // e.g. /dir/page.php
    $relativePath = ltrim($requestPath, '/');                            // e.g. dir/page.php
    $fullUri = ltrim($_SERVER['REQUEST_URI'], '/');                      // e.g. dir/page.php?param=...

    $indicator = '';

    if (($handle = fopen($csvFile, 'r')) !== false) {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            [$pageRule, $selectors, $hideFlag, $highlightFlag] = array_map('trim', $row + ['', '', '', '']);
            if ($pageRule === '') {
                continue;
            }

            // Determine rule type and match
            $rawRule = trim($pageRule, '/');
            $isDirRule = str_ends_with($pageRule, '/');
            $isWildcard = str_ends_with($pageRule, '*');
            $matches = false;

            if ($isDirRule) {
                $dir = rtrim($rawRule, '/');
                $matches = str_starts_with($relativePath, $dir);
            } elseif ($isWildcard) {
                $prefix = rtrim($rawRule, '*');
                $matches = str_starts_with($fullUri, $prefix);
            } else {
                $matches = ($relativePath === $rawRule);
            }

            if (! $matches) {
                continue;
            }

            // If hide flag is set, show indicator
            if ($hideFlag !== '') {
                $indicator = 'Elements 🔒';
                break;
            }
        }
        fclose($handle);
    }

    return $indicator;
}