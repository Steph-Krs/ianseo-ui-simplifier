<?php
// No output before headers

// Allowed CSV files for download
$allowedFiles = ['menus.csv', 'elements.csv'];

// Retrieve and sanitize the “file” query parameter 
$requestedFile = basename($_GET['file'] ?? '');
if (!in_array($requestedFile, $allowedFiles, true)) {
    http_response_code(400);
    exit('File not allowed.');
}

$filePath = __DIR__ . '/' . $requestedFile;
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

// Send headers to prompt download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
header('Content-Length: ' . (string) filesize($filePath));

readfile($filePath);
exit;
?>