<?php

namespace MigrationSwinde\MigrationOxidToShopware\Migration;

use MigrationSwinde\MigrationOxidToShopware\Service\OxidConnector;
use MigrationSwinde\MigrationOxidToShopware\Service\ShopwareConnector;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

final class CategoryMigrator
{
    public function __construct(
        private OxidConnector $oxid,
        private ShopwareConnector $shopware,
        private string $mapFile,
        private LoggerInterface $logger
    ) {}

    public function migrate(): void
    {
        $this->logger->info('🚀 Starte Kategorie-Migration ...');

        $categories = $this->oxid->getCategories();
        $this->logger->info("✅ Kategorien aus OXID geladen: " . count($categories));

        $rootId = $this->shopware->getRootCategoryId('0199E6C14E3A737E825CF8871F33B9CC');
        if (!$rootId) {
            throw new \RuntimeException('❌ Root category not found or invalid navigationCategoryId.');
        }

        $mapping = [];
        if (file_exists($this->mapFile)) {
            $mapping = json_decode(file_get_contents($this->mapFile), true) ?: [];
        }

        // 3️⃣ Hauptkategorien anlegen
        foreach ($categories as $cat) {
            $oxidId = $cat['OXID'] ?? $cat['id'] ?? null;

            // 🔁 Prüfen, ob Kategorie schon migriert wurde
            if (isset($mapping[$oxidId])) {
                $existingId = $mapping[$oxidId];
                if ($this->shopware->categoryExists($existingId)) {
                    $this->logger->info("⏩ Kategorie '{$cat['name']}' bereits in Shopware vorhanden – überspringe.");
                    continue;
                }
            }

            $payload = [
                'id' => Uuid::randomHex(),
                'name' => $cat['name'] ?? 'Unbenannt',
                'active' => (bool)($cat['OXACTIVE'] ?? true),
                'parentId' => $rootId,
                'visible' => true,
                'type' => 'page',
                'position' => (int)($cat['OXSORT'] ?? 0),
            ];

            $response = $this->shopware->createCategory($payload);

            if (!empty($response['data']['id'])) {
                $mapping[$oxidId] = $response['data']['id'];
                $this->logger->info("✅ Hauptkategorie '{$cat['name']}' angelegt (Shopware-ID: {$response['data']['id']}).");
            } else {
                $mapping[$oxidId] = $payload['id'];
                $this->logger->warning("⚠️ Keine ID für '{$cat['name']}' erhalten – lokale ID gemappt.");
            }

            file_put_contents($this->mapFile, json_encode($mapping, JSON_PRETTY_PRINT));
            usleep(200000); // 0.2 Sek. Pause
        }

        $this->logger->info("📦 Category mapping gespeichert unter: {$this->mapFile}");
        $this->logger->info("🏁 Kategorie-Migration abgeschlossen!");
    }
}
