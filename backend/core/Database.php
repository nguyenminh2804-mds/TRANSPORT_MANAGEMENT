<?php

// Quản lý kết nối MySQL.

class Database
{
    private $conn;

    public function connect()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST .
                   ";dbname=" . DB_NAME .
                   ";charset=" . DB_CHARSET;

            $this->conn = new PDO(
                $dsn,
                DB_USER,
                DB_PASS
            );

            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $this->conn->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $this->conn;

        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}