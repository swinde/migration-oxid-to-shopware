<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use PDO;
use PDOException;

class OxidConnector
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        try {
            $this->pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['user'],
                $config['pass']
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('❌ OXID DB Verbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function getProducts(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM oxarticles WHERE OXSHOPID=1");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}