<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance()->getConnection();
    // Selezioniamo i dati ordinandoli dal più recente al più vecchio
    $sql = "SELECT share_key, automaton_json, created_at FROM automata ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Il JSON nel database è una stringa. Dobbiamo decodificarlo in un oggetto PHP
    // prima di ricodificare l'intero array di risultati in JSON per il client.
    foreach ($results as $key => $row) {
        $results[$key]['automaton'] = json_decode($row['automaton_json'], true);
        unset($results[$key]['automaton_json']); // Rimuoviamo il campo stringa ridondante
    }

    http_response_code(200);
    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore nel recupero degli elementi salvati: ' . $e->getMessage()]);
}