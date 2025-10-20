<?php
namespace MigrationSwinde\MigrationOxidToShopware\Service;

use GuzzleHttp\Client;

class ShopwareConnector
{
    private string $apiUrl;
    private string $accessKey;

    public function __construct(string $apiUrl, string $accessKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->accessKey = $accessKey;
    }

    public function productExists(string $productNumber): bool
    {
        // Einfacher GET-Request prüfen, echte Logik kann angepasst werden
        return false;
    }

    public function createProduct(array $product, array $mediaIds = []): bool
    {
        // Hier den POST-Request an /api/product einbauen
        // $product enthält alle Daten, $mediaIds die Media-IDs
        return true;
    }
}
