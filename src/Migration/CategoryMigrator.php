<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Migration;

use MigrationSwinde\MigrationOxidToShopware\Service\OxidConnector;
use MigrationSwinde\MigrationOxidToShopware\Service\ShopwareConnector;
use Psr\Log\LoggerInterface;

final class CategoryMigrator
{
    private OxidConnector $oxid;
    private ShopwareConnector $shopware;
    private function saveMapping(array $mapping): void
    {
        $dir = \dirname($this->mapFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->mapFile, json_encode($mapping, JSON_PRETTY_PRINT));
    }

    private LoggerInterface $logger;

    public function __construct(
        OxidConnector $oxid,
        ShopwareConnector $shopware,
        string $mapFile,
        LoggerInterface $logger
    ) {
        $this->oxid = $oxid;
        $this->shopware = $shopware;
        $this->mapFile = $mapFile;
        $this->logger = $logger;
    }

    public function migrate(): void
    {

        $categories = $this->oxid->getCategories(); // neue SQL-Version aus getCategorySelect()
        $this->logger->info('Fetched categories: ' . count($categories));
        $mapping = [];

        // --- 1️⃣ Root-Kategorie (Home) holen ---
        $homeId = $this->shopware->getRootCategoryId();
        if (!$homeId) {
            throw new \RuntimeException('Root category "Home" not found in Shopware');
        }

        // --- 2️⃣ Hauptkategorien anlegen ---
        foreach ($categories as $cat) {
            if ($cat['parentId'] !== null) {
                continue;
            }

            $payload = [
                'name'        => $cat['name'],
                'active'      => (bool)$cat['active'],
                'parentId'    => $homeId,
                'metaTitle'   => $cat['metaKeywords'] ?? null,
                'metaDescription' => $cat['metaDescription'] ?? null,
                'position'    => (int)$cat['position'],
            ];

            $swId = $this->shopware->createCategory($payload);
            $mapping[$cat['id']] = $swId;

            $this->logger->info("Created root category {$cat['name']}");
        }

        // --- 3️⃣ Unterkategorien erzeugen ---
        $remaining = true;
        $maxPasses = 5; // Sicherheit gegen Endlosschleife

        while ($remaining && $maxPasses-- > 0) {
            $remaining = false;
            foreach ($categories as $cat) {
                if ($cat['parentId'] === null) {
                    continue;
                }
                if (isset($mapping[$cat['id']])) {
                    continue; // bereits angelegt
                }
                if (!isset($mapping[$cat['parentId']])) {
                    $remaining = true;
                    continue; // Parent noch nicht vorhanden
                }

                $payload = [
                    'name'        => $cat['name'],
                    'active'      => (bool)$cat['active'],
                    'parentId'    => $mapping[$cat['parentId']],
                    'metaTitle'   => $cat['metaKeywords'] ?? null,
                    'metaDescription' => $cat['metaDescription'] ?? null,
                    'position'    => (int)$cat['position'],
                ];

                $swId = $this->shopware->createCategory($payload);
                $mapping[$cat['id']] = $swId;

                $this->logger->info("Created subcategory {$cat['name']} under {$cat['parentId']}");
            }
        }


        // --- 4️⃣ Mapping speichern ---
        $this->saveMapping($mapping);
        $this->logger->info('✅ Category migration completed.');

    }
}
