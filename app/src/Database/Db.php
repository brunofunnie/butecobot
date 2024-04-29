<?php

namespace Chorume\Database;

use PDO;
use PDOException;
use PDOStatement;

class Db
{
    protected static $instance;

    protected PDO $pdo;

    private function __construct()
    {
        try
        {
            $hostname = getenv('DB_SERVER');
            $dbname = getenv('DB_DATABASE');
            $username = getenv('DB_USER');
            $password = getenv('DB_PASSWORD');

            $this->pdo = new PDO("mysql:host=$hostname;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public static function getInstance()
    {
        try
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public function query($sql, $params = []): PDOStatement|array|bool|null
    {
        try {
            $query = trim($sql);
            $stmt = $this->pdo->prepare($query);
            $rawStatement = preg_split("/( |\r|\n)/", $query);
            $statement = strtolower($rawStatement[0]);

            foreach ($params as $key => $value) {
                if ($key === 'limit') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                    continue;
                }

                $stmt->bindValue($key, $value);
            }

            switch($statement) {
                case 'select':
                case 'show':
                case 'call':
                case 'describe':
                    $stmt->execute();
                    return $stmt->fetchAll();
                    break;
                case 'insert':
                case 'update':
                case 'delete':
                    return $stmt->execute();
                    break;
                default:
                    return null;
                    break;
            }
        } catch (PDOException $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }

            echo $e->getMessage();
        }
    }

    public function getLastInsertId(): int|bool
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
	{
		return $this->pdo->beginTransaction();
	}

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
	{
		return $this->pdo->rollBack();
	}

    public function inTransaction(): bool
	{
		return $this->pdo->inTransaction();
	}
}
