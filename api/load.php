<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

header('Content-Type: application/json');

try {
    if (!isset($_GET['key']) || empty($_GET['key'])) {
        throw new InvalidArgumentException('Chiave di condivisione non fornita.');
    }
    $share_key = $_GET['key'];

    $pdo = Database::getInstance()->getConnection();
    $sql = "SELECT automaton_json FROM automata WHERE share_key = :share_key";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':share_key' => $share_key]);

    if ($stmt->rowCount() == 1) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        http_response_code(200);
        // Stampa direttamente la stringa JSON dal database
        echo $row['automaton_json'];
    } else {
        throw new RuntimeException('Nessun automa trovato con questa chiave.', 404);
    }

} catch (Exception $e) {
    $code = $e->getCode() === 404 ? 404 : 400;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}