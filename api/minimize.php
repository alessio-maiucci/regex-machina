<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\DfaMinimizer;

// Aggiungi queste righe SOLO PER IL DEBUG. Rimuovile o commentale in produzione.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $dfaData = json_decode(file_get_contents('php://input'), true);
    if ($dfaData === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('JSON malformato.');
    }
    if (empty($dfaData)) {
        throw new InvalidArgumentException('Dati DFA non forniti.');
    }

    $minimizer = new DfaMinimizer($dfaData);
    $minimalDfa = $minimizer->minimize();

    http_response_code(200);
    echo json_encode($minimalDfa);

} catch (InvalidArgumentException $e) {
    // Errore nei dati forniti dall'utente (es. non è un DFA)
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    // Qualsiasi altro errore inaspettato del server
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Si è verificato un errore interno del server: ' . $e->getMessage()]);
}