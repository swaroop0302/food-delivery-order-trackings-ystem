<?php
/**
 * MongoDB Connection Configuration
 * Uses MongoDB PHP Driver (mongodb extension)
 */

require_once __DIR__ . '/../vendor/autoload.php';

class Database {
    private static $instance = null;
    private $client;
    private $db;

    private function __construct() {
        $host     = defined('MONGO_HOST')     ? MONGO_HOST     : 'mongodb://localhost:27017';
        $dbName   = defined('MONGO_DB')       ? MONGO_DB       : 'food_delivery';

        try {
            $this->client = new MongoDB\Client($host);
            $this->db     = $this->client->selectDatabase($dbName);
        } catch (Exception $e) {
            error_log('MongoDB connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed']));
        }
    }

    /** Singleton accessor */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Return raw MongoDB\Database */
    public function getDB(): MongoDB\Database {
        return $this->db;
    }

    /** Shorthand: get a collection */
    public function collection(string $name): MongoDB\Collection {
        return $this->db->selectCollection($name);
    }
}
