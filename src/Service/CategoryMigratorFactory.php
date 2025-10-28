<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Doctrine\DBAL\Connection;                              // 👈 neu
use MigrationSwinde\MigrationOxidToShopware\Migration\CategoryMigrator;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class CategoryMigratorFactory
{
    private string $mapFile;

    public function __construct(
        private SystemConfigService $configService,
        private LoggerInterface $logger,
        private string $shopwareBaseUrl,
        private string $salesChannelName,

    ) {
        $this->mapFile = __DIR__ . '/../../var/category_map.json';
    }

    public function create(): CategoryMigrator
    {
        $this->logger->info('🏗️ Initialisiere CategoryMigratorFactory ...');

        // --- OXID-DB aufbauen (nur für OXID)
        $dbConfig = [
            'host'     => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidHost'),
            'port'     => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPort') ?? 3306,
            'dbname'   => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidDatabase'),
            'user'     => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidUser'),
            'password' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPassword'),
        ];

        try {
            $oxidPdo = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['dbname']),
                $dbConfig['user'],
                $dbConfig['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
            $this->logger->info('✅ OXID-Datenbankverbindung hergestellt.');
        } catch (\PDOException $e) {
            $this->logger->error('❌ OXID-DB-Fehler: ' . $e->getMessage());
            throw $e;
        }

        // --- Shopware API Credentials dynamisch laden ---
        // 1️⃣ Basis-URL zuerst
        $apiUrl = rtrim(
            getenv('SHOPWARE_BASE_URL')
                ?: $this->shopwareBaseUrl,
            '/'
        );

        // 2️⃣ Integration Label oder Keys aus .env oder Plugin-Konfig
        $integrationLabel = getenv('SHOPWARE_INTEGRATION_LABEL')
           ?: $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.integrationLabel');

        $accessKeyId = getenv('SHOPWARE_ACCESS_KEY_ID')
            ?: $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeyId');

        $accessKeySecret = getenv('SHOPWARE_ACCESS_KEY_SECRET')
            ?: $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeySecret');

        // 3️⃣ Log für Debug-Zwecke
        $this->logger->info('🔧 Lade API-Zugangsdaten ...', [
            'apiUrl'          => $apiUrl,
            'integrationLabel'=> $integrationLabel,
            'accessKeyId'     => $accessKeyId ? substr($accessKeyId, 0, 6) . '…' : null,
        ]);

        // 4️⃣ Validierung
        if (!$apiUrl || (!$accessKeyId && !$integrationLabel)) {
            throw new \RuntimeException('❌ Keine gültigen API-Zugangsdaten gefunden (.env oder Plugin-Konfiguration).');
        }

        // --- Shopware API Credentials nur aus Plugin-Konfiguration holen ---
        $apiUrl          = rtrim($this->shopwareBaseUrl, '/');
        $accessKeyId     = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeyId');
        $accessKeySecret = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeySecret');

        if (!$apiUrl || !$accessKeyId || !$accessKeySecret) {
            $this->logger->error('❌ Fehlende API-Zugangsdaten in der Plugin-Konfiguration.');
            throw new \RuntimeException('Fehlende API-Zugangsdaten in der Plugin-Konfiguration.');
        }

        $this->logger->info("🔑 Verwende API-URL: {$apiUrl}");
        $this->logger->info("🆔 Access Key ID: {$accessKeyId}");

        if (!$apiUrl || !$accessKeyId || !$accessKeySecret) {
            throw new \RuntimeException('❌ Fehlende API-Zugangsdaten (URL/Key/Secret).');
        }

        $shopwareConnector = new ShopwareConnector(
            $apiUrl,
            $accessKeyId,
            $accessKeySecret,
            $this->logger,
            'Storefront' // oder aus Config: $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.salesChannelName')
        );

        $oxidConnector = new OxidConnector($oxidPdo, $this->logger);  // 👈 passt zu deinem Konstruktor

        $this->logger->info('✅ CategoryMigratorFactory bereit.');

        return new CategoryMigrator(
            $oxidConnector,
            $shopwareConnector,
            $this->mapFile,
            $this->logger
        );
    }
}
