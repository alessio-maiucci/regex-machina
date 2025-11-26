<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\RegexCompiler;
use App\Service\NfaSimplifier; // Importa la nuova classe

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['regex'])) {
        throw new InvalidArgumentException('Espressione regolare non fornita.');
    }
    
    // Passo 1: Compila la RegEx in un NFA con l'algoritmo di Thompson (come prima)
    $compiler = new RegexCompiler();
    $thompsonNfa = $compiler->compile($data['regex']);
    
    // Passo 2 (NUOVO): Semplifica l'NFA generato per eliminare le ε-transizioni
    $simplifier = new NfaSimplifier($thompsonNfa);
    $simplifiedNfa = $simplifier->simplify();
    
    http_response_code(200);
    // Restituisci al client l'NFA semplificato
    echo json_encode($simplifiedNfa);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}