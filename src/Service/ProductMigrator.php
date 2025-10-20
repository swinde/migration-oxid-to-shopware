<?php
namespace MigrationSwinde\MigrationOxidToShopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductMigrator
{
    private SystemConfigService $configService;
    private OxidConnector $oxidConnector;
    private ShopwareConnector $shopwareConnector;
    private MediaUploader $mediaUploader;

    public function __construct(
        SystemConfigService $configService,
        OxidConnector $oxidConnector,
        ShopwareConnector $shopwareConnector,
        MediaUploader $mediaUploader
    ) {
        $this->configService = $configService;
        $this->oxidConnector = $oxidConnector;
        $this->shopwareConnector = $shopwareConnector;
        $this->mediaUploader = $mediaUploader;
    }

    /**
     * Hauptfunktion zur Migration aller Produkte
     */
    public function migrateProducts(): void
    {
        $products = $this->oxidConnector->fetchProducts();

        foreach ($products as $product) {

            // Varianten prüfen
            if (!empty($product['OXPARENTID'])) {
                // Skip Variante, kann Logik angepasst werden
                continue;
            }

            // Prüfen, ob Produkt schon existiert
            if ($this->shopwareConnector->productExists($product['OXARTNUM'])) {
                echo "Produkt existiert bereits: " . $product['OXARTNUM'] . PHP_EOL;
                continue;
            }

            // Bilder vorbereiten
            $images = $this->oxidConnector->fetchImages($product);
            $mediaIds = [];
            foreach ($images as $image) {
                $mediaId = $this->mediaUploader->uploadMedia($image, $product['OXTITLE']);
                if ($mediaId) {
                    $mediaIds[] = $mediaId;
                } else {
                    echo "⚠ Bild konnte nicht hochgeladen werden: {$image}" . PHP_EOL;
                }
            }

            // Produkt an Shopware übertragen
            $success = $this->shopwareConnector->createProduct($product, $mediaIds);

            if ($success) {
                echo "✔ Produkt migriert: {$product['OXTITLE']}" . PHP_EOL;
            } else {
                echo "❌ Fehler beim Anlegen des Produkts: {$product['OXTITLE']}" . PHP_EOL;
            }
        }
    }
}
