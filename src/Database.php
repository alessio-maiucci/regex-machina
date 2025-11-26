<?php
namespace App;

use PDO;
use PDOException;

// Singleton per la connessione al database
class Database {
    private static $instance = null;
    private $pdo;

    private const DB_SERVER = 'localhost';
    private const DB_USERNAME = 'root';
    private const DB_PASSWORD = '';
    private const DB_NAME = 'regex_machina_db';

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . self::DB_SERVER . ";dbname=" . self::DB_NAME,
                self::DB_USERNAME,
                self::DB_PASSWORD
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // In produzione, loggheremmo l'errore invece di mostrarlo
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}