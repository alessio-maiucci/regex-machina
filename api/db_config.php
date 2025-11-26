<?php
// Credenziali per il database.
// Le impostazioni di default di XAMPP sono queste.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'regex_machina_db'); // Il nome del database che hai creato

// Tentativo di connessione al database MySQL
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Imposta la modalità di errore di PDO su eccezione
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    // In un'applicazione reale, non mostreresti mai l'errore esatto all'utente.
    // Lo registreresti in un file di log.
    // Per questo progetto, inviamo un errore JSON generico.
    http_response_code(500);
    echo json_encode(['error' => 'Errore di connessione al database.']);
    exit();
}
?>