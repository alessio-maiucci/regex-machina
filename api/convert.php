<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\DfaConverter;

header('Content-Type: application/json');

try {
    $nfaData = json_decode(file_get_contents('php://input'), true);
    if (!$nfaData) throw new InvalidArgumentException('Dati NFA non validi.');
    
    $converter = new DfaConverter($nfaData);
    $dfa = $converter->convert();
    
    http_response_code(200);
    echo json_encode($dfa);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}