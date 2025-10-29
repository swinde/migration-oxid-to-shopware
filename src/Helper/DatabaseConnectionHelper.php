<?php

namespace MigrationSwinde\MigrationOxidToShopware\Helper;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class DatabaseConnectionHelper
{
    public function __construct(
        private SystemConfigService $configService,
        private LoggerInterface $logger
    ) {}

    /**
     * Verbindet mit der OXID-Datenbank anhand der Plugin-Konfiguration
     */
    public function getOxidPdo(): PDO
    {
        $dbHost = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidHost');
        $dbPort = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPort') ?? 3306;
        $dbName = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidDatabase');
        $dbUser = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidUser');
        $dbPass = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPassword');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->logger->info('✅ OXID-Datenbankverbindung erfolgreich hergestellt.');
            return $pdo;
        } catch (PDOException $e) {
            $this->logger->error('❌ Fehler beim Verbinden zur OXID-Datenbank: ' . $e->getMessage());
            throw new \RuntimeException('OXID-DB-Verbindung fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verbindet mit der Shopware-Datenbank anhand der .env-Variablen
     */
    public function getShopwarePdo(): PDO
    {
        $dbHost = $_ENV['DB_HOST'] ?? 'db';
        $dbPort = $_ENV['DB_PORT'] ?? 3306;
        $dbName = $_ENV['DB_NAME'] ?? 'db';
        $dbUser = $_ENV['DB_USER'] ?? 'db';
        $dbPass = $_ENV['DB_PASSWORD'] ?? 'db';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->logger->info('✅ Shopware-Datenbankverbindung erfolgreich hergestellt.');
            return $pdo;
        } catch (PDOException $e) {
            $this->logger->error('❌ Fehler beim Verbinden zur Shopware-Datenbank: ' . $e->getMessage());
            throw new \RuntimeException('Shopware-DB-Verbindung fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }
}
