<?php
/**
 * @file     Db.php
 * @author   Estéban DESESSARD
 * @brief    Fichier de déclaration et définition de la classe Db
 * @details
 * @date     15/05/2025
 * @version  0.0
 */


/**
 * @brief Classe Db
 * @details Cette classe gère les connexions aux deux bases de données
 * et l'exécution des requêtes.
 */
class Db
{
    private static array $instance = [];
    private PDO $conn;

    /**
     * Constructeur privé pour la classe Db
     * 
     * @brief Initialise la connexion à la base de données
     * @details Crée une nouvelle connexion PDO à la base de données spécifiée
     * en utilisant les configurations définies dans getDbConfig
     * 
     * @param string $dbName Le nom de la base de données à laquelle se connecter
     * @throws PDOException Si la connexion à la base de données échoue
     */
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

    /**
     * Méthode clone privée pour empêcher la duplication de l'instance
     * 
     * @brief Empêche la duplication de l'instance de la classe Db
     */
    private function __clone()
    {
    }

    /**
     * Méthode statique pour obtenir la configuration de la base de données
     * 
     * @brief Retourne la configuration de la base de données spécifiée
     * @details Renvoie un tableau contenant les informations de connexion
     * pour la base de données demandée
     * 
     * @param string $dbName Le nom de la base de données
     * @return array La configuration de la base de données
     * @throws InvalidArgumentException Si le nom de la base de données n'est pas reconnu
     */
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

    /**
     * Méthode statique pour obtenir une instance de la classe Db
     * 
     * @brief Retourne une instance de la classe Db pour la base de données spécifiée
     * @details Si l'instance n'existe pas, elle est créée. Sinon, l'instance existante est retournée.
     * 
     * @param string $dbName Le nom de la base de données à laquelle se connecter
     * @return Db L'instance de la classe Db pour la base de données spécifiée
     */
    public static function getInstance(string $dbName): Db
    {
        if (!array_key_exists($dbName, self::$instance)) {
            self::$instance[$dbName] = new Db($dbName);
        }
        return self::$instance[$dbName];
    }

    /**
     * Méthode pour obtenir la connexion PDO
     * 
     * @brief Retourne l'objet PDO de la connexion à la base de données
     * @details Permet d'accéder à la connexion pour exécuter des requêtes
     * 
     * @return PDO L'objet PDO de la connexion à la base de données
     */
    public function getConnection(): PDO
    {
        return $this->conn;
    }

    /**
     * Méthode pour exécuter une requête SQL
     * 
     * @brief Exécute une requête SQL préparée avec des paramètres
     * @details Permet d'exécuter des requêtes SQL en utilisant des paramètres pour éviter les injections SQL
     * 
     * @param string $sql La requête SQL à exécuter
     * @param array $params Les paramètres à lier à la requête
     * @return PDOStatement Le résultat de la requête exécutée
     */
    public function query(string $sql, array $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}