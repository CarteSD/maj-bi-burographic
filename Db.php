<?php
/**
 * @file     Db.php
 * @author   Estéban DESESSARD
 * @brief    Fichier de déclaration et définition de la classe Db
 * @details
 * @date     15/05/2025
 * @version  0.0
 */

 require_once 'config.php';

/**
 * @brief Classe Db
 * @details Cette classe gère les connexions aux deux bases de données
 * et l'exécution des requêtes.
 */
class Db
{
    private static ?Db $instance = null;
    private PDO $conn;

    /**
     * Constructeur privé pour la classe Db
     * 
     * @brief Initialise la connexion à la base de données
     * @throws PDOException Si la connexion à la base de données échoue
     */
    private function __construct()
    {
        try {
            $this->conn = new PDO("sqlsrv:Server=" . DB_SERVER . ";Database=" . DB_BATIGEST, DB_USERNAME, DB_PASSWORD);
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
     * Méthode statique pour obtenir l'instance unique de la classe Db
     * 
     * @brief Retourne l'instance unique de la classe Db
     * @details Si l'instance n'existe pas, elle est créée. Sinon, l'instance existante est retournée.
     * 
     * @return Db L'instance unique de la classe Db
     */
    public static function getInstance(string $dbName): Db
    {
        if (self::$instance === null) {
            self::$instance= new Db($);
        }
        return self::$instance;
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