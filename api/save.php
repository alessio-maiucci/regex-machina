<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

header('Content-Type: application/json');

try {
    $automaton = json_decode(file_get_contents('php://input'), true);
    if (!$automaton) throw new InvalidArgumentException('Dati automa non validi.');
    
    $share_key = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 8)), 0, 8);
    $automaton_json_string = json_encode($automaton);

    $pdo = Database::getInstance()->getConnection();
    $sql = "INSERT INTO automata (share_key, automaton_json) VALUES (:share_key, :automaton_json)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':share_key' => $share_key, ':automaton_json' => $automaton_json_string]);

    http_response_code(200);
    echo json_encode(['success' => true, 'share_key' => $share_key]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore durante il salvataggio: ' . $e->getMessage()]);
}