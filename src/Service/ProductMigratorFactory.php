<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductMigratorFactory
{
    private SystemConfigService $configService;

    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function getConfigValue(string $key): mixed
    {
        return $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.' . $key);
    }


    public function create(): ProductMigrator
    {
        // 🔹 OXID-Datenbankverbindung
        $dbConfig = [
            'host' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidHost'),
            'port' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPort'),
            'dbname' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidDatabase'),
            'user' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidUser'),
            'password' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPassword'),
            'image_base_path' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidImageBasePath'),
        ];

        // 🔹 Shopware API Zugangsdaten
        $apiUrl = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.apiUrl');
        $accessKeyId = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeyId');
        $accessKeySecret = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeySecret');

        // 🔹 Objekte erstellen
        $oxidConnector = new OxidConnector($dbConfig);
        $shopwareConnector = new ShopwareConnector($apiUrl, $accessKeyId, $accessKeySecret);
        $mediaUploader = new MediaUploader($apiUrl, $accessKeyId, $accessKeySecret);

        // 🔹 Produkt-Migrator zusammenbauen
        return new ProductMigrator(
            $oxidConnector,
            $shopwareConnector,
            $mediaUploader,
            $this->configService
        );
    }
}
