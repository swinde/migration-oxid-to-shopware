<?php
namespace MigrationSwinde\MigrationOxidToShopware\Service;

class MediaUploader
{
    private ShopwareConnector $shopwareConnector;

    public function __construct(ShopwareConnector $shopwareConnector)
    {
        $this->shopwareConnector = $shopwareConnector;
    }

    public function uploadMedia(string $imagePath, string $productName): ?string
    {
        if (!file_exists($imagePath)) {
            return null;
        }

        // Optional: Dateiname für SEO umbenennen
        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower($productName));
        $fileName = $safeName . '.' . $ext;

        // TODO: Upload-Logik an Shopware API
        // Return Media-ID oder null
        return 'dummy-media-id';
    }
}
