<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\StringGenerator;

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $automatonData = json_decode(file_get_contents('php://input'), true);
    if ($automatonData === null) {
        throw new InvalidArgumentException('Dati non validi.');
    }

    $generator = new StringGenerator($automatonData);
    $strings = $generator->generate();
    $description = $generator->analyzeLanguage();

    http_response_code(200);
    echo json_encode([
        'strings' => $strings,
        'description' => $description
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}