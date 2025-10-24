<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final class CategoryMigratorFactory
{
    public function __construct(private readonly SystemConfigService $configService) {}

    public function create(): CategoryMigrator
    {
        // OXID-DB aus Plugin-Konfig
        $dbConfig = [
            'host'            => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidHost'),
            'port'            => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPort') ?? 3306,
            'dbname'          => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidDatabase'),
            'user'            => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidUser'),
            'password'        => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidPassword'),
            'image_base_path' => $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidImageBasePath'),
        ];

        // Shopware-API aus Plugin-Konfig
        $apiUrl         = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.apiUrl');
        $accessKeyId    = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeyId');
        $accessKeySecret= $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.accessKeySecret');

        if (!$apiUrl || !$accessKeyId || !$accessKeySecret) {
            throw new \RuntimeException('Fehlende API-Zugangsdaten in der Plugin-Konfiguration.');
        }

        $oxidConnector    = new OxidConnector($dbConfig);
        $shopwareConnector= new ShopwareConnector($apiUrl, $accessKeyId, $accessKeySecret);

        return new CategoryMigrator(
            $oxidConnector,
            $shopwareConnector,
            $this->configService
        );
    }

    // optional, falls dein check-config darauf zugreift:
    public function getConfigValue(string $key): mixed
    {
        return $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.' . $key);
    }
}
