<?php
/**
 * Retourne la feuille de style CSS à injecter pour le mode Ultra Basique,
 * en fonction des règles de fichiers, de dossiers et de wildcard définies dans elements.csv.
 *
 * @return string CSS généré
 */
function getUltraBasiqueJS(): string {
    $js = '';
    $csvPath = __DIR__ . '/elements.csv';
    if (!is_readable($csvPath)) {
        error_log("ultra-basique.css.php : impossible de lire $csvPath");
        return '';
    }

    // URI et chemins normalisés
    $uriPath         = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // /dir/page.php
    $currentRelPath  = trim($uriPath, '/');                              // dir/page.php
    $fullRequestUri  = ltrim($_SERVER['REQUEST_URI'], '/');              // dir/page.php?param=...

    if (($handle = fopen($csvPath, 'r')) !== false) {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            list($pageRule, $selectorsCsv, $hideFlag, $hlFlag) = array_map('trim', $row + ['', '', '', '']);
            if ($pageRule === '') {
                continue;
            }

            // Détermination du type de règle
            $rawRule           = trim($pageRule, '/');
            $isDirectoryRule   = substr($pageRule, -1) === '/';
            $isWildcardRule    = substr($pageRule, -1) === '*';

            $match = false;

            if ($isDirectoryRule) {
                // Règle dossier : correspond à tout URI commençant par ce dossier
                $dirRule = rtrim($rawRule, '/');
                if (strpos($currentRelPath, $dirRule) === 0) {
                    $match = true;
                }
            } elseif ($isWildcardRule) {
                // Règle wildcard : match sur le début de REQUEST_URI (incluant query)
                $wildRule = rtrim($rawRule, '*');
                if (strpos($fullRequestUri, $wildRule) === 0) {
                    $match = true;
                }
            } else {
                // Règle fichier : match exact sur le chemin relatif
                if ($currentRelPath === $rawRule) {
                    $match = true;
                }
            }

            if (! $match) {
                continue;
            }

            // Traitement des sélecteurs CSS
            $selectors = array_filter(array_map('trim', explode(',', $selectorsCsv)));
            if (empty($selectors)) {
                continue;
            }
            $selList = implode(', ', $selectors);

            // Génération des règles CSS
            if ($hideFlag) {
                $js = "Eléments 🔒";
            }
        }
        fclose($handle);
    }

    return $js;
}
