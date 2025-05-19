<?php

class Db
{
    private static array $instance = [];
    private PDO $conn;

    private function __construct(string $dbName)
    {
        $config = self::getDbConfig($dbName);

        try {
            $this->conn = new PDO("sqlsrv:Server={$config['servername']};Database={$config['database']}", $config['username'], $config['password']);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    private function __clone()
    {
    }

    private static function getDbConfig(string $dbName): array
    {
        $config = [
            'interventions' => [
                'servername' => 'DESKTOP-D5H040D\\SAGEBAT',
                'database' => 'INTERVENTIONS',
                'username' => null,
                'password' => null,
            ],
            'batigest' => [
                'servername' => 'DESKTOP-D5H040D\\SAGEBAT',
                'database' => 'BTG_DOS_SOC01',
                'username' => null,
                'password' => null,
            ],
        ];

        if (!array_key_exists($dbName, $config)) {
            throw new InvalidArgumentException("Database configuration for '$dbName' not found.");
        }

        return $config[$dbName];
    }

    public static function getInstance(string $dbName): Db
    {
        if (!array_key_exists($dbName, self::$instance)) {
            self::$instance[$dbName] = new Db($dbName);
        }
        return self::$instance[$dbName];
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }

    public function query(string $sql, array $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}