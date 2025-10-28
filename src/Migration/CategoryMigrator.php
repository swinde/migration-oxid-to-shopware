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

    public function migrate(): void
    {
        $this->logger->info("🚀 Starte Kategorie-Migration ...");

        // --- Kategorien aus OXID laden ---
        $categories = $this->oxid->fetchCategories();
        $this->logger->info("📦 OXID-Kategorien geladen: " . count($categories));

        // --- Root-Kategorie in Shopware ermitteln ---
        $rootCategoryId = $this->shopware->getRootCategoryId();
        if (!$rootCategoryId) {
            throw new \RuntimeException('❌ Root category not found or invalid navigationCategoryId.');
        }
        $this->logger->info("🏠 Verwende Shopware-Root-Kategorie-ID: {$rootCategoryId}");

        $mapping = [];

        // --- 1️⃣ Hauptkategorien anlegen ---
        foreach ($categories as $cat) {
            if ($cat['OXROOTID'] !== $cat['OXID'] && $cat['OXPARENTID'] !== '') {
                continue;
            }

            $payload = [
                'name' => $cat['OXTITLE'],
                'active' => (bool)$cat['OXACTIVE'],
                'parentId' => $rootCategoryId,
                'position' => (int)$cat['OXSORT'] ?? 0,
                'metaTitle' => $cat['OXKEYWORDS'] ?? null,
                'metaDescription' => $cat['OXDESC'] ?? null,
            ];

            $swId = $this->shopware->createCategory($payload);
            $mapping[$cat['OXID']] = $swId;

            $this->logger->info("✅ Hauptkategorie '{$cat['OXTITLE']}' erstellt (Shopware-ID: {$swId})");
        }

        // --- 2️⃣ Unterkategorien anlegen ---
        $remaining = true;
        $maxPasses = 5;

        while ($remaining && $maxPasses-- > 0) {
            $remaining = false;

            foreach ($categories as $cat) {
                if (isset($mapping[$cat['OXID']])) {
                    continue;
                }
                $parentId = $cat['OXPARENTID'];

                if (!isset($mapping[$parentId])) {
                    $remaining = true;
                    continue; // Parent noch nicht angelegt
                }

                $payload = [
                    'name' => $cat['OXTITLE'],
                    'active' => (bool)$cat['OXACTIVE'],
                    'parentId' => $mapping[$parentId],
                    'position' => (int)$cat['OXSORT'] ?? 0,
                    'metaTitle' => $cat['OXKEYWORDS'] ?? null,
                    'metaDescription' => $cat['OXDESC'] ?? null,
                ];

                $swId = $this->shopware->createCategory($payload);
                $mapping[$cat['OXID']] = $swId;

                $this->logger->info("🧩 Unterkategorie '{$cat['OXTITLE']}' unter '{$parentId}' erstellt");
            }
        }

        // --- Mapping speichern ---
        file_put_contents($this->mapFile, json_encode($mapping, JSON_PRETTY_PRINT));
        $this->logger->info('📁 Kategorie-Mapping gespeichert unter ' . $this->mapFile);
        $this->logger->info('🏁 Kategorie-Migration abgeschlossen.');
    }
}
