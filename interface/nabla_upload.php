<?php
// Nabla ambient recording upload handler

require_once(__DIR__ . '/globals.php');
require_once($GLOBALS['fileroot'] . '/library/documents.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');

use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json');

if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

if (empty($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No audio provided']);
    exit;
}

$pid = (int)($_POST['pid'] ?? 0);
$category = document_category_to_id('Nabla Recordings');
if (!$category) {
    $category = sqlInsert("INSERT INTO categories (name) VALUES (?)", ['Nabla Recordings']);
}

$result = addNewDocument(
    $_FILES['audio']['name'],
    $_FILES['audio']['type'],
    $_FILES['audio']['tmp_name'],
    $_FILES['audio']['error'],
    $_FILES['audio']['size'],
    '',
    $pid,
    $category
);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

echo json_encode(['success' => true, 'docId' => $result['doc_id']]);
