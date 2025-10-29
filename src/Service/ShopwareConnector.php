<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use AllowDynamicProperties;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

#[AllowDynamicProperties]
final class ShopwareConnector
{
    private Client $http;
    private ?string $accessToken = null;

    public function __construct(
        private string $apiUrl,
        private string $accessKeyId,
        private string $accessKeySecret,
        private LoggerInterface $logger,
        private ?Connection $shopwareConnection = null
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->http = new Client(['verify' => false]);
    }

    private function ensureAccessToken(): void
    {
        if ($this->accessToken) {
            return;
        }

        $this->logger->info("🔐 Hole Access Token von {$this->apiUrl} ...");

        try {
            $res = $this->http->post("{$this->apiUrl}/api/oauth/token", [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->accessKeyId,
                    'client_secret' => $this->accessKeySecret,
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $data = json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->accessToken = $data['access_token'] ?? null;

            $this->logger->info("✅ Access Token erfolgreich geholt.");
        } catch (RequestException $e) {
            $this->logger->error("❌ Fehler beim Holen des Access Tokens: " . $e->getMessage());
            throw $e;
        }
    }

    public function requestJson(string $method, string $uri, array $options = [], bool $didRetry = false): array
    {
        $this->ensureAccessToken();

        $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
        $options['headers']['Accept'] = 'application/json';

        try {
            $res = $this->http->request($method, "{$this->apiUrl}/{$uri}", $options);
            return json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === 401 && !$didRetry) {
                $this->logger->warning('401 Unauthorized – Token wird erneuert.');
                $this->accessToken = null;
                return $this->requestJson($method, $uri, $options, true);
            }

            $body = $e->getResponse()?->getBody()?->__toString() ?? 'Keine Antwort';
            $this->logger->error("❌ Fehler beim Request ({$method} {$uri}): " . $e->getMessage());
            $this->logger->debug("Antwort: {$body}");
            throw $e;
        }
    }

    public function createCategory(array $payload): array
    {
        try {
            return $this->requestJson('POST', 'api/category', ['json' => $payload]);
        } catch (\Throwable $e) {
            $body = $e instanceof RequestException && $e->getResponse()
                ? $e->getResponse()->getBody()->__toString()
                : 'Keine Antwort';
            $name = $payload['name'] ?? 'Unbekannt';
            $this->logger->error("❌ Fehler beim Erstellen der Kategorie '{$name}': " . $e->getMessage());

            $this->logger->debug("Antwort: {$body}");
            return [];
        }
    }

    public function getRootCategoryId(string $salesChannelId): ?string
    {
        $this->logger->info("🔍 Suche Root-Kategorie für SalesChannel", ['salesChannelId' => $salesChannelId]);

        if (!$this->shopwareConnection) {
            $this->logger->warning("⚠️ Keine DB-Verbindung, Rückgabe von null.");
            return null;
        }

        $result = $this->shopwareConnection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE LOWER(HEX(id)) = LOWER(:id)',
            ['id' => $salesChannelId]
        );

        if ($result) {
            $this->logger->info("✅ Root-Kategorie-ID via DB gefunden", ['navigationCategoryId' => $result]);
            return $result;
        }

        $this->logger->warning("⚠️ Kein passender Sales-Channel für ID {$salesChannelId} in der DB gefunden.");
        return null;
    }

    public function categoryExists(string $id): bool
    {
        try {
            $this->ensureAccessToken();
            $res = $this->http->request('GET', "{$this->apiUrl}/api/category/{$id}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Accept' => 'application/json'
                ],
            ]);
            return $res->getStatusCode() === 200;
        } catch (\Throwable $e) {
            if ($e instanceof RequestException && $e->getResponse()?->getStatusCode() === 404) {
                return false;
            }
            $this->logger->warning("⚠️ Fehler bei categoryExists({$id}): " . $e->getMessage());
            return false;
        }
    }
}
