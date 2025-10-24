<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MediaUploader
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

        $this->authenticate();

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ]);
    }

    /**
     * Holt Access Token über /api/oauth/token
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
     * Lädt ein Bild zu einem Shopware-Produkt hoch
     */
    public function upload(string $shopwareProductId, string $imagePath): ?string
    {
        if (!file_exists($imagePath)) {
            echo "⚠️  Bilddatei nicht gefunden: {$imagePath}\n";
            return null;
        }

        try {
            // 1️⃣ Neues Media-Objekt in Shopware anlegen
            $createResponse = $this->client->post('/api/media');
            $mediaData = json_decode($createResponse->getBody()->getContents(), true);
            $mediaId = $mediaData['data']['id'] ?? null;

            if (!$mediaId) {
                throw new \RuntimeException('Fehler beim Erstellen des Media-Objekts.');
            }

            // 2️⃣ Datei an das Media-Objekt anhängen
            $fileResponse = $this->client->post("/api/_action/media/{$mediaId}/upload?extension=" . pathinfo($imagePath, PATHINFO_EXTENSION), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($imagePath, 'r'),
                        'filename' => basename($imagePath),
                    ],
                ],
            ]);

            // 3️⃣ Media-Objekt mit Produkt verknüpfen
            $this->client->patch("/api/product/{$shopwareProductId}", [
                'json' => [
                    'media' => [[
                        'mediaId' => $mediaId,
                    ]],
                ],
            ]);

            echo "📸  Bild erfolgreich hochgeladen: {$imagePath}\n";
            return $mediaId;
        } catch (RequestException $e) {
            $body = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
            echo "❌ Fehler beim Hochladen des Bildes {$imagePath}: " . $body . PHP_EOL;
            return null;
        }
    }
}
