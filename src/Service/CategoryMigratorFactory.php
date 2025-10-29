<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use MigrationSwinde\MigrationOxidToShopware\Migration\CategoryMigrator;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class CategoryMigratorFactory
{
    private string $mapFile = __DIR__ . '/../../var/category_map.json';

    public function __construct(
        private SystemConfigService $configService,
        private LoggerInterface $logger,
        private Connection $shopwareConnection,

    ) {}

    public function create(): CategoryMigrator
    {
        // 🧩 1️⃣ Hole OXID-DB-Konfiguration
        $oxidConfig = [
            'dbname'   => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidDatabase'),
            'user'     => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidUser'),
            'password' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPassword'),
            'host'     => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidHost'),
            'port'     => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPort') ?? 3306,
            'driver'   => 'pdo_mysql',
        ];

        $oxidPdo = DriverManager::getConnection($oxidConfig)->getNativeConnection();

        // 🧩 2️⃣ Hole Shopware-API-Zugangsdaten
        $apiUrl = rtrim(
            $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.apiUrl'),
            '/'
        );
        $accessKeyId = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeyId');
        $accessKeySecret = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeySecret');

        if (!$apiUrl || !$accessKeyId || !$accessKeySecret) {
            throw new \RuntimeException('❌ Fehlende Shopware-API-Zugangsdaten in der Plugin-Konfiguration.');
        }

        // 🧩 3️⃣ Hole Sales-Channel-ID aus Konfiguration (neu!)
        $salesChannelId = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.salesChannelId');
        if (!$salesChannelId) {
            $this->logger->warning('⚠️ Keine Sales-Channel-ID in der Konfiguration gefunden. Es wird die Standard-Root-Kategorie versucht.');
        }

        // 🧩 4️⃣ Erzeuge Connectoren
        $oxidConnector = new OxidConnector($oxidPdo, $this->logger);
        $shopwareConnector = new ShopwareConnector(
            $apiUrl,
            $accessKeyId,
            $accessKeySecret,
            $this->logger,
            $this->shopwareConnection
        );

// 🧩 5️⃣ CategoryMigrator instanzieren (KORREKT)
        return new CategoryMigrator(
            $oxidConnector,
            $shopwareConnector,
            $this->mapFile,
            $this->logger
        );
    }
}
