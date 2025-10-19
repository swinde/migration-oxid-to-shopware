<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use PDO;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductMigrator
{
    private PDO $oxidDb;

    public function __construct(array $oxidDbConfig)
    {
        if (empty($oxidDbConfig['host'] ?? null)) {
            throw new \RuntimeException('Fehlende OXID Datenbank-Konfiguration.');
        }

        $this->oxidDb = new PDO(
            "mysql:host={$oxidDbConfig['host']};dbname={$oxidDbConfig['dbname']};charset=utf8",
            $oxidDbConfig['user'],
            $oxidDbConfig['pass']
        );
    }

    public function fetchProducts(): array
    {
        $stmt = $this->oxidDb->query("SELECT * FROM oxarticles WHERE OXSHOPID=1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function migrateProduct(array $productData): string
    {
        // UUID für Sync-Operation
        $uuid = Uuid::randomHex();

        // Hier Sync-API Payload aufbauen
        $payload = [
            'write' => [
                'op_' . $uuid => [
                    'entity' => 'product',
                    'action' => 'upsert',
                    'payload' => [$productData]
                ]
            ]
        ];

        // API-Aufruf hier (z.B. via Guzzle oder curl)
        // return mediaId oder Erfolg/Fatal-Fehler
        return $uuid;
    }
}
