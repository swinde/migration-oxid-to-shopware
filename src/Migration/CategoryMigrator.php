<?php

namespace MigrationSwinde\MigrationOxidToShopware\Migration;

use MigrationSwinde\MigrationOxidToShopware\Service\OxidConnector;
use MigrationSwinde\MigrationOxidToShopware\Service\ShopwareConnector;
use Psr\Log\LoggerInterface;

final class CategoryMigrator
{
    private OxidConnector $oxid;
    private ShopwareConnector $shopware;
    private string $mapFile;
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

    private function isUuid(?string $v): bool
    {
        return is_string($v)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) === 1;
    }

    public function migrate(): void
    {
        $categories = $this->oxid->getCategories();
        $mapping = [];

        // 🪴 Root-Kategorie (Sales-Channel) bestimmen
        $homeId = $this->shopware->getRootCategoryId();
        if (!$this->isUuid($homeId)) {
         throw new \RuntimeException('Root category "Home" not found or invalid UUID');
        }

        // 🌳 Hauptkategorien (ohne Parent) anlegen
        foreach ($categories as $cat) {
            if (!empty($cat['parentId'])) {
                continue;
            }

            $payload = [
                'name'   => $cat['name'],
                'active' => (bool)$cat['active'],
                'parentId' => $homeId,
                'metaTitle' => $cat['metaKeywords'] ?? null,
                'metaDescription' => $cat['metaDescription'] ?? null,
                'position' => (int)($cat['position'] ?? 0),
            ];

            $swId = $this->shopware->createCategory($payload);
            $mapping[$cat['id']] = $swId;
            $this->logger->info("✅ Root-Kategorie '{$cat['name']}' erstellt.");
        }

        // 🌿 Subkategorien mit Mehrfach-Pass anlegen
        $remaining = true;
        $maxPasses = 6;
        $orphans = [];

        while ($remaining && $maxPasses-- > 0) {
            $remaining = false;

            foreach ($categories as $cat) {
                if (empty($cat['parentId'])) {
                    continue;
                }
                if (isset($mapping[$cat['id']])) {
                    continue;
                }
                if (!isset($mapping[$cat['parentId']])) {
                    $remaining = true;
                    $orphans[$cat['id']] = $cat;
                    continue;
                }

                $parentUuid = $mapping[$cat['parentId']];
                if (!$this->isUuid($parentUuid)) {
                    $this->logger->warning("⚠️ Parent UUID ungültig für {$cat['name']} -> übersprungen.");
                    $remaining = true;
                    $orphans[$cat['id']] = $cat;
                    continue;
                }

                $payload = [
                    'name'   => $cat['name'],
                    'active' => (bool)$cat['active'],
                    'parentId' => $parentUuid,
                    'metaTitle' => $cat['metaKeywords'] ?? null,
                    'metaDescription' => $cat['metaDescription'] ?? null,
                    'position' => (int)($cat['position'] ?? 0),
                ];

                try {
                    $swId = $this->shopware->createCategory($payload);
                    $mapping[$cat['id']] = $swId;
                    unset($orphans[$cat['id']]);
                    $this->logger->info("✅ Subkategorie '{$cat['name']}' unter '{$cat['parentId']}' erstellt.");
                } catch (\Throwable $e) {
                    $this->logger->error("❌ Fehler bei '{$cat['name']}': " . $e->getMessage());
                    $remaining = true;
                }
            }
        }

        if (!empty($orphans)) {
            $this->logger->warning('⚠️ Verwaiste Kategorien nach Migration: ' . count($orphans));
            $dir = \dirname($this->mapFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents(
                $dir . '/category_orphans.json',
                json_encode(array_values($orphans), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        // 💾 Mapping speichern
        $dir = \dirname($this->mapFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->mapFile, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->logger->info('✅ Category migration completed.');
    }
}
