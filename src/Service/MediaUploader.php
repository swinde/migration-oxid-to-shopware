<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

class MediaUploader
{
    private string $shopwareUrl;
    private string $token;

    public function __construct(string $shopwareUrl, string $token)
    {
        $this->shopwareUrl = rtrim($shopwareUrl, '/');
        $this->token = $token;
    }

    public function upload(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("❌ Bild existiert nicht: $filePath");
        }

        // TODO: Shopware 6 Media Upload implementieren
        // Rückgabe: mediaId oder null
        return null;
    }
}
