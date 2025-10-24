<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ShopwareConnector
{
    private string $apiUrl;
    private string $accessKeyId;
    private string $accessKeySecret;
    private Client $client;
    private ?string $accessToken = null;

    public function __construct(string $apiUrl, string $accessKeyId, string $accessKeySecret)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;

        // Beim Start authentifizieren
        $this->authenticate();

        // Guzzle-Client initialisieren
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ]);
    }

    /**
     * Authentifiziert sich bei der Shopware API und holt ein gültiges Bearer-Token
     */
    private function authenticate(): void
    {
        try {
            $client = new Client(['base_uri' => $this->apiUrl]);
            $response = $client->post('/api/oauth/token', [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->accessKeyId,
                    'client_secret' => $this->accessKeySecret,
                ],
            ]);

            $data = json_decode((string)$response->getBody(), true);
            $this->accessToken = $data['access_token'] ?? null;

            if (!$this->accessToken) {
                throw new \RuntimeException('Kein Access Token von der Shopware API erhalten.');
            }
        } catch (RequestException $e) {
            throw new \RuntimeException('Fehler bei der Authentifizierung: ' . $e->getMessage());
        }
    }

    /**
     * Produkt in Shopware anlegen
     */
    public function createProduct(array $payload): ?string
    {
        try {
            $response = $this->client->post('/api/product', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['id'] ?? null;
        } catch (RequestException $e) {
            $body = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
            echo "❌ Fehler beim Erstellen des Produkts: " . $body . PHP_EOL;
            return null;
        }
    }

    /**
     * Kategorie in Shopware anlegen
     */
    public function createCategory(array $payload): ?string
    {
        try {
            $response = $this->client->post('/api/category', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['id'] ?? null;
        } catch (RequestException $e) {
            $body = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
            echo "❌ Fehler beim Erstellen der Kategorie: " . $body . PHP_EOL;
            return null;
        }
    }
}
