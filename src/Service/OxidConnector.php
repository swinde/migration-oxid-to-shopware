<?php
namespace MigrationSwinde\MigrationOxidToShopware\Service;

use PDO;

class OxidConnector
{
    private array $dbConfig;
    private PDO $pdo;

    public function __construct(array $dbConfig)
    {
        $this->dbConfig = $dbConfig;
        $this->connect();
    }

    private function connect(): void
    {
        $dsn = "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset=utf8";
        $this->pdo = new PDO($dsn, $this->dbConfig['user'], $this->dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function fetchProducts(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM oxarticles WHERE OXSHOPID=1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchImages(array $product): array
    {
        $images = [];
        for ($i = 1; $i <= 5; $i++) {
            $col = "OXPICTURE{$i}";
            if (!empty($product[$col])) {
                $images[] = rtrim($this->dbConfig['image_base_path'], '/') . '/' . $product['OXID'] . '/' . $product[$col];
            }
        }
        return $images;
    }
}
