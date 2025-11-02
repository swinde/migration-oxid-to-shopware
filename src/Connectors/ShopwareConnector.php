<?php

namespace MigrationSwinde\MigrationOxidToShopware\Connectors;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ShopwareConnector
{
    private Client $client;
    private string $accessToken;

    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private string $oxidImageBasePath,
        private string $salesChannelId,
        private LoggerInterface $logger
    ) {
        $this->client = new Client(['base_uri' => rtrim($this->baseUrl, '/')]);
        $this->authenticate();
    }

    /**
     * Authentifiziert sich bei der Shopware-API und holt ein Access-Token.
     */
    private function authenticate(): void
    {
        $response = $this->client->post('/api/oauth/token', [
            'json' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $this->accessToken = $data['access_token'] ?? '';

        if (empty($this->accessToken)) {
            throw new \RuntimeException('Fehler bei der Authentifizierung: kein Token erhalten.');
        }
    }

    /**
     * Gibt Standard-Header für authentifizierte Requests zurück.
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Gibt die SalesChannel-ID zurück (aus Plugin-Konfiguration).
     */
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * Lädt eine Kategoriebild-Datei hoch (2-Schritt-Verfahren).
     *
     * @param string $relativePath Relativer Pfad oder Dateiname aus OXID
     * @param bool   $dryRun       Wenn true, nur Logausgabe – kein Upload
     * @return string|null         Media-ID oder null bei Fehler
     */
    public function uploadCategoryMedia(string $relativePath, bool $dryRun = false): ?string
    {
        $fullUrl  = $this->resolveMediaPath($relativePath);
        $fileName = basename($relativePath);

        if ($dryRun) {
            $this->logger->info("[DRY-RUN] Würde Kategorie-Bild hochladen: {$fullUrl}");
            return null;
        }

        try {
            // 1️⃣ Media-Entity erstellen
            // Dateiendung und MIME-Type dynamisch ermitteln
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $mimeType = match ($fileExtension) {
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'svg'  => 'image/svg+xml',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };

            $mediaResp = $this->client->post('/api/media', [
                'headers' => $this->getAuthHeaders(),
                'json' => [
                    'mediaFolderId' => '0199E6C0FC2E72DE8263910665F21031', // Kategorie-Medienordner
                    'fileName'      => pathinfo($fileName, PATHINFO_FILENAME),
                    'fileExtension' => $fileExtension,
                    'mimeType'      => $mimeType,
                ],
            ]);

            // Media-ID aus Header oder Body holen
            $mediaId = null;
            if ($mediaResp->hasHeader('Location')) {
                $location = $mediaResp->getHeaderLine('Location');
                $mediaId  = basename($location);
            } else {
                $mediaData = json_decode($mediaResp->getBody()->getContents(), true);
                $mediaId   = $mediaData['data']['id'] ?? ($mediaData['id'] ?? null);
            }

            if (!$mediaId) {
                throw new \RuntimeException('Media-ID konnte nicht aus der Response ermittelt werden.');
            }

            // 2️⃣ Datei-Upload
            $uploadResp = $this->client->post("/api/_action/media/{$mediaId}/upload", [
                'headers' => array_merge($this->getAuthHeaders(), [
                    'Content-Type' => 'application/octet-stream',
                    'sw-filename'  => $fileName,
                ]),
                'body' => fopen($fullUrl, 'r'),
            ]);

            if ($uploadResp->getStatusCode() >= 400) {
                throw new \RuntimeException('Fehler beim Datei-Upload: ' . $uploadResp->getReasonPhrase());
            }

            $this->logger->info("Kategorie-Bild erfolgreich hochgeladen: {$fileName} (ID: {$mediaId})");
            return $mediaId;
        } catch (\Throwable $e) {
            $this->logger->error("Fehler beim Hochladen von {$relativePath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Erstellt eine neue Kategorie in Shopware.
     *
     * @param array $data Kategorie-Daten
     * @return string ID der neu angelegten Kategorie
     */
    public function createCategory(array $data): string
    {
        try {
            $response = $this->client->post('/api/category', [
                'headers' => $this->getAuthHeaders(),
                'json' => $data,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $categoryId = $result['data']['id'] ?? '';

            $this->logger->info("Kategorie '{$data['name']}' in Shopware angelegt (ID: {$categoryId}).");
            return $categoryId;
        } catch (\Throwable $e) {
            $body = method_exists($e, 'getResponse') ? (string) $e->getResponse()->getBody() : '';
            $this->logger->error("Fehler beim Erstellen der Kategorie '{$data['name']}': {$e->getMessage()} {$body}");
            throw $e;
        }
    }

    /**
     * Wandelt einen relativen OXID-Bildpfad in eine vollständige URL um.
     * Beispiel:
     *   oxidImageBasePath = https://www.seifenstueck.de/out/pictures/master
     *   relativePath = p1010227.jpg
     *   → https://www.seifenstueck.de/out/pictures/master/category/thumb/p1010227.jpg
     */
    private function resolveMediaPath(string $relativePath): string
    {
        // absolute URLs direkt zurückgeben
        if (str_starts_with($relativePath, 'http')) {
            return $relativePath;
        }

        // Basis-URL bereinigen
        $base = rtrim($this->oxidImageBasePath, '/');

        // Unterpfad für Kategorie-Thumbnails anhängen
        return "{$base}/category/thumb/" . ltrim($relativePath, '/');
    }
}
