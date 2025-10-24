<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class CategoryMigrator
{
    private OxidConnector $oxidConnector;
    private ShopwareConnector $shopwareConnector;
    private SystemConfigService $configService;

    /** @var array<string, string> */
    private array $categoryMap = []; // [OXID_ID => SHOPWARE_ID]

    public function __construct(
        OxidConnector $oxidConnector,
        ShopwareConnector $shopwareConnector,
        SystemConfigService $configService
    ) {
        $this->oxidConnector = $oxidConnector;
        $this->shopwareConnector = $shopwareConnector;
        $this->configService = $configService;
    }

    /**
     * Migriert alle Kategorien aus OXID nach Shopware
     */
    public function migrateAllCategories(): void
    {
        $categories = $this->oxidConnector->fetchCategories();

        // 1️⃣ Hauptkategorien zuerst
        foreach ($categories as $category) {
            if (empty($category['OXPARENTID']) || $category['OXPARENTID'] === 'oxrootid') {
                $this->migrateCategory($category);
            }
        }

        // 2️⃣ Danach Unterkategorien (Parent muss vorher existieren)
        foreach ($categories as $category) {
            if (!empty($category['OXPARENTID']) && $category['OXPARENTID'] !== 'oxrootid') {
                $this->migrateCategory($category);
            }
        }
        // 3️⃣ CategoryMap speichern
        $this->saveCategoryMap();

        echo "✅ Kategorienmigration abgeschlossen.\n";
    }

    /**
     * Einzelne Kategorie migrieren
     */
    private function migrateCategory(array $category): void
    {
        $oxidId = $category['OXID'];
        $name = $category['OXTITLE'] ?? 'Unbenannte Kategorie';
        $description = $category['OXLONGDESC'] ?? ($category['OXDESC'] ?? '');
        $active = (bool)($category['OXACTIVE'] ?? 1);
        $parentId = $category['OXPARENTID'] ?? null;

        // Prüfen, ob Kategorie bereits migriert wurde
        if (isset($this->categoryMap[$oxidId])) {
            return;
        }

        // Shopware Payload
        $payload = [
            'name' => $name,
            'active' => $active,
            'description' => $description,
            'type' => 'page',
            'productAssignmentType' => 'product',
            'displayNestedProducts' => true,
        ];

        // Parent-Zuordnung, falls vorhanden
        if ($parentId && isset($this->categoryMap[$parentId])) {
            $payload['parentId'] = $this->categoryMap[$parentId];
        }

        // Kategorie erstellen und ID merken
        $shopwareId = $this->shopwareConnector->createCategory($payload);

        if ($shopwareId) {
            $this->categoryMap[$oxidId] = $shopwareId;
            echo "➡️  Kategorie {$name} migriert (OXID: {$oxidId}, Shopware: {$shopwareId})\n";
        } else {
            echo "❌ Fehler beim Anlegen der Kategorie {$name}\n";
        }
    }

    /**
     * Speichert die OXID->Shopware Kategoriezuordnung als JSON
     */
    private function saveCategoryMap(): void
    {
        $path = __DIR__ . '/../../Resources/category_map.json';
        file_put_contents($path, json_encode($this->categoryMap, JSON_PRETTY_PRINT));
        echo "🗂️  CategoryMap gespeichert unter: {$path}\n";
    }

}
