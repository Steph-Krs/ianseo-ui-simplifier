<?php
// download_csv.php
// Ne pas mettre d’espace avant, ni echo

// Liste des fichiers autorisés
$allowed = ['menus.csv', 'elements.csv'];

// Récupère le paramètre ?file=
$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (!in_array($file, $allowed, true)) {
    http_response_code(400);
    exit('Fichier non autorisé.');
}

$path = __DIR__ . '/' . $file;
if (!file_exists($path)) {
    
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Envoie les bons headers pour forcer le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>