<?php

namespace MigrationSwinde\MigrationOxidToShopware\Factory;

use PDO;
use Psr\Log\LoggerInterface;

class PdoFactory
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createOxidPdo(
        string $host,
        string $port,
        string $database,
        string $user,
        string $password
    ): PDO {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $this->logger->info("PDO-Verbindung zu OXID erfolgreich hergestellt: {$host}:{$port}/{$database}");
            return $pdo;
        } catch (\Throwable $e) {
            $this->logger->error("Fehler bei der PDO-Verbindung zu OXID: " . $e->getMessage());
            throw $e;
        }
    }
}
