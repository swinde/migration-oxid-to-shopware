<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductMigrator
{
    private OxidConnector $oxidConnector;
    private ShopwareConnector $shopwareConnector;
    private MediaUploader $mediaUploader;
    private SystemConfigService $configService;

    public function __construct(
        OxidConnector $oxidConnector,
        ShopwareConnector $shopwareConnector,
        MediaUploader $mediaUploader,
        SystemConfigService $configService
    ) {
        $this->oxidConnector = $oxidConnector;
        $this->shopwareConnector = $shopwareConnector;
        $this->mediaUploader = $mediaUploader;
        $this->configService = $configService;
    }

    /**
     * Migriert alle Produkte aus der OXID-Datenbank nach Shopware
     */
    public function migrateAllProducts(): void
    {
        $products = $this->oxidConnector->fetchProducts();
        echo "🔄 Starte Migration von " . count($products) . " Produkten...\n";

        foreach ($products as $product) {
            try {
                $this->migrateProduct($product);
            } catch (\Throwable $e) {
                $productId = $product['OXID'] ?? 'unbekannt';
                echo "❌ Fehler beim Migrieren von Produkt {$productId}: " . $e->getMessage() . PHP_EOL;
            }
        }

        echo "✅ Migration abgeschlossen.\n";
    }

    /**
     * Migriert ein einzelnes Produkt
     */
    private function migrateProduct(array $product): void
    {
        $productName = $product['OXTITLE'] ?? 'Unbekanntes Produkt';
        $productNumber = $product['OXARTNUM'] ?? '';
        $price = (float)($product['OXPRICE'] ?? 0.0);
        $taxId = $this->configService->get('core.basicInformation.defaultTax') ?? 'a5da76b447db4d0aba62e6512dadf45b';

        echo "➡️  Migriere Produkt: {$productName} ({$productNumber})\n";

        // 🧩 Produktdaten vorbereiten
        $payload = [
            'name' => $productName,
            'productNumber' => $productNumber,
            'stock' => (int)($product['OXSTOCK'] ?? 0),
            'active' => (bool)($product['OXACTIVE'] ?? true),
            'price' => [[
                'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'gross' => $price,
                'net' => $price / 1.19,
                'linked' => true,
            ]],
            'taxId' => $taxId,
        ];

        // 🧩 Produkt in Shopware anlegen
        $shopwareProductId = $this->shopwareConnector->createProduct($payload);

        if (!$shopwareProductId) {
            echo "❌ Produkt {$productName} konnte nicht angelegt werden.\n";
            return;
        }

        // 🖼️ Produktbilder migrieren
        $basePath = $this->configService->get('MigrationSwindeMigrationOxidToShopware.config.oxidImageBasePath') ?? '';
        for ($i = 1; $i <= 12; $i++) {
            $imageKey = "OXPIC{$i}";
            if (!empty($product[$imageKey])) {
                $imagePath = rtrim($basePath, '/') . '/' . $i . '/' . $product[$imageKey];

                if (!file_exists($imagePath)) {
                    echo "⚠️  Bild existiert nicht: {$imagePath}, übersprungen.\n";
                    continue;
                }

                try {
                    $this->mediaUploader->upload((string)$shopwareProductId, $imagePath);
                } catch (\Throwable $e) {
                    echo "❌ Fehler beim Hochladen von {$imagePath}: " . $e->getMessage() . PHP_EOL;
                }
            }
        }

        echo "✅ Produkt {$productName} erfolgreich migriert.\n";
    }
}
