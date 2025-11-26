<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\NfaSimulator;

header('Content-Type: application/json');

try {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if (!$data || !isset($data['automaton']) || !isset($data['testString'])) {
        throw new InvalidArgumentException('Dati mancanti.');
    }
    
    $simulator = new NfaSimulator($data['automaton']);
    $result = $simulator->run($data['testString']);
    
    http_response_code(200);
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}