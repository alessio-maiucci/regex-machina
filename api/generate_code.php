<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\CodeGenerator;

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null || !isset($data['automaton']) || !isset($data['language'])) {
        throw new InvalidArgumentException('Dati non validi o linguaggio non specificato.');
    }

    $language = $data['language'];
    $generator = new CodeGenerator($data['automaton']);
    $code = $generator->generate($language);

    http_response_code(200);
    // Restituiamo anche il linguaggio per il syntax highlighting
    echo json_encode(['code' => $code, 'language' => $language]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}