<?php

declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Verbindung zur Shopware 6 Admin API.
 * Lädt API-Credentials automatisch aus der Integration-Tabelle,
 * mit Fallback auf manuelle Konfiguration (z. B. .env oder services.yaml).
 */
final class ShopwareConnector
{
    private Client $http;
    private string $baseUrl;
    private ?string $clientId;
    private ?string $clientSecret;

    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        LoggerInterface $logger,
        string $baseUrl,
        ?string $clientId = null,
        ?string $clientSecret = null
    ) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 30,
        ]);
    }

    // ⚙️ Lädt Zugangsdaten automatisch aus der Shopware DB
    public function loadCredentialsFromIntegration(string $integrationLabel): void
    {
        try {
            $sql = "
                SELECT access_key, secret_access_key
                FROM integration
                WHERE label = :label AND deleted_at IS NULL
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['label' => $integrationLabel]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->logger->warning("Integration '$integrationLabel' nicht gefunden – nutze Fallback-Credentials.");
                return;
            }

            $this->clientId = $row['access_key'];
            $this->clientSecret = $row['secret_access_key'];
            $this->logger->info("API-Credentials erfolgreich aus Integration '$integrationLabel' geladen.");
        } catch (\Throwable $e) {
            $this->logger->error("Fehler beim Laden der Integration '$integrationLabel': " . $e->getMessage());
        }
    }

    // ✅ Authentifizierung (JWT) mit Auto-Refresh
    private function ensureAccessToken(): void
    {
        if ($this->accessToken === null || $this->tokenExpiresSoon()) {
            $this->refreshAccessToken();
        }
    }

    private function tokenExpiresSoon(): bool
    {
        return $this->tokenExpiresAt === null || (time() + 60) >= $this->tokenExpiresAt;
    }

    private function refreshAccessToken(): void
    {
        if (!$this->clientId || !$this->clientSecret) {
            throw new \RuntimeException('Keine gültigen API-Zugangsdaten (Client-ID oder Secret fehlen).');
        }

        $res = $this->http->post('/api/oauth/token', [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = json_decode((string)$res->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = time() + (int)$data['expires_in'];

        $this->logger->info('Shopware Access-Token erfolgreich erneuert.');
    }

    // 🔁 Universelle API-Anfrage mit Retry bei 401
    public function requestJson(string $method, string $uri, array $options = [], bool $didRetry = false): array
    {
        $this->ensureAccessToken();
        $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
        $options['headers']['Accept'] = 'application/json';

        try {
            $res = $this->http->request($method, $uri, $options);
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === 401 && !$didRetry) {
                $this->logger->warning('401 Unauthorized – Token wird erneuert.');
                $this->refreshAccessToken();
                return $this->requestJson($method, $uri, $options, true);
            }
            throw $e;
        }

        return json_decode((string)$res->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    // 📂 Beispiel-Endpunkt: Root-Kategorie holen
    public function getRootCategoryId(): ?string
    {
        $data = $this->requestJson('GET', '/api/category', [
            'query' => ['filter[name]' => 'Home']
        ]);

        return $data['data'][0]['id'] ?? null;
    }

    // 📂 Beispiel-Endpunkt: Kategorie anlegen
    public function createCategory(array $payload): string
    {
        $res = $this->requestJson('POST', '/api/category', ['json' => $payload]);
        return $res['data']['id'] ?? '';
    }
}
