<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection parameters
 */

class Database {
    // Database credentials
    private $host = "localhost";
    private $db_name = "rd_management";
    private $username = "root";
    private $password = "";
    private $conn;

    /**
     * Get the database connection
     * 
     * @return PDO|null Database connection object or null on failure
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>