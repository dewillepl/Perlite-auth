<?php
/*!
 * Perlite v1.6 (https://github.com/secure-77/Perlite)
 * Author: sec77 (https://secure77.de)
 * Licensed under MIT (https://github.com/secure-77/Perlite/blob/main/LICENSE)
 */

include('helper.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['file']) || !isset($data['content'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing file or content']);
    exit;
}

$filePath = $data['file'];
$content = $data['content'];

// Security: Basic path sanitization
if (strpos($filePath, '..') !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file path']);
    exit;
}

// Call menu() to populate the list of available/allowed files
menu($rootDir);
global $avFiles;

// The file path from the client might not have the leading slash, let's add it.
if (substr($filePath, 0, 1) !== '/') {
    $filePath = '/' . $filePath;
}

if (!in_array($filePath, $avFiles, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: File not in allowed list.']);
    exit;
}

$fullPath = $rootDir . $filePath . '.md';

if (file_put_contents($fullPath, $content) !== false) {
    echo json_encode(['status' => 'success', 'message' => 'File saved successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file.']);
}

?>