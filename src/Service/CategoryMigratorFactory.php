<?php

declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use MigrationSwinde\MigrationOxidToShopware\Helper\DatabaseConnectionHelper;
use MigrationSwinde\MigrationOxidToShopware\Migration\CategoryMigrator;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use PDO;

final class CategoryMigratorFactory
{
    private SystemConfigService $configService;
    private LoggerInterface $logger;
    private string $shopwareBaseUrl;
    private string $integrationLabel;
    private string $mapFile;
    private string $salesChannelName;


    public function __construct(
        SystemConfigService $configService,
        LoggerInterface $logger,
        string $shopwareBaseUrl,
        string $integrationLabel,
        string $salesChannelName
    ) {
        $this->configService   = $configService;
        $this->logger          = $logger;
        $this->shopwareBaseUrl = $shopwareBaseUrl;
        $this->integrationLabel = $integrationLabel;
        $this->salesChannelName = $salesChannelName;
        $this->mapFile = __DIR__ . '/../../var/category_map.json';
    }


    public function create(): CategoryMigrator
    {
        // 🧩 1️⃣ Datenbank-Verbindungen herstellen
        $connections = new DatabaseConnectionHelper($this->configService, $this->logger);

        /** @var PDO $oxidPdo */
        $oxidPdo = $connections->getOxidPdo();

        /** @var PDO $shopwarePdo */
        $shopwarePdo = $connections->getShopwarePdo();

        // 🧭 2️⃣ Shopware-Integration per Label prüfen
        try {
            $stmt = $shopwarePdo->prepare("
                SELECT access_key, secret_access_key
                FROM integration
                WHERE label = :label AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute(['label' => $this->integrationLabel]);
            $row = $stmt->fetch();
        } catch (\PDOException $e) {
            throw new \RuntimeException('Fehler beim Lesen aus der Shopware-Datenbank: ' . $e->getMessage());
        }

        if (!$row) {
            $this->logger->warning(sprintf(
                "⚠️ Keine Shopware-Integration mit Label '%s' gefunden. Verwende Plugin-Konfiguration.",
                $this->integrationLabel
            ));
        }

        // 3️⃣ Shopware-API-Credentials laden
        $apiUrl = $this->shopwareBaseUrl;

        // 🔧 Immer: aus Plugin-Konfiguration laden (nicht aus DB!)
        $accessKeyId = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeyId');
        $accessKeySecret = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeySecret');


        // 🧩 4️⃣ Connectoren erzeugen
        $oxidConnector = new OxidConnector($oxidPdo, $this->logger);
        $shopwareConnector = new ShopwareConnector(
            $this->shopwareBaseUrl,   // <- string aus .env / services.yaml
            $accessKeyId,             // <- aus Plugin-Konfiguration
            $accessKeySecret,         // <- aus Plugin-Konfiguration
            $this->logger
        );

        // 🧩 5️⃣ CategoryMigrator erzeugen und zurückgeben
        $this->logger->info('✅ CategoryMigratorFactory erfolgreich initialisiert.');

        return new CategoryMigrator(
            $oxidConnector,
            $shopwareConnector,
            $this->mapFile,
            $this->logger
        );
    }
}
