<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use AllowDynamicProperties;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

#[AllowDynamicProperties]
final class ShopwareConnector
{
    private Client $http;
    private ?string $accessToken = null;

    public function __construct(
        string $apiUrl,
        string $accessKeyId,
        string $accessKeySecret,
        LoggerInterface $logger,
        string $salesChannelName = 'Storefront'
    ) {
        $this->apiUrl = rtrim($apiUrl, '/'); // 👈 wichtig!
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->logger = $logger;
        $this->salesChannelName = $salesChannelName;
        $this->http = new \GuzzleHttp\Client(['verify' => false]);
    }


    public function ensureAccessToken(): void
    {
        $this->logger->info("🔐 Hole Access Token von '{$this->apiUrl}/api/oauth/token' ...");

        // ⬇️ Log direkt am Anfang einfügen
        $this->logger->info("🔐 Hole Access Token von {$this->apiUrl}/api/oauth/token ...");

        if ($this->accessToken && $this->tokenExpiresAt > time()) {
            return;
        }

        try {
            $response = $this->http->post($this->apiUrl . '/api/oauth/token', [
                'json' => [
                    'client_id' => $this->accessKeyId,
                    'client_secret' => $this->accessKeySecret,
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->accessToken = $data['access_token'];
            $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 600);

            $this->logger->info('✅ Access Token erfolgreich geholt.');
        } catch (\Throwable $e) {
            $this->logger->error('❌ Fehler beim Abruf des Access Tokens: ' . $e->getMessage());
            throw $e;
        }
    }


    public function requestJson(string $method, string $uri, array $options = [], bool $didRetry = false): array
    {
        $this->ensureAccessToken();
        $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
        $options['headers']['Accept'] = 'application/json';

        try {
            $res = $this->http->request($method, $uri, $options);
            $body = (string)$res->getBody();

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $status = $response?->getStatusCode();
            $this->logger->error("❌ HTTP Fehler: {$status} {$e->getMessage()}");

            if ($status === 401 && !$didRetry) {
                $this->logger->warning('401 Unauthorized – Access Token wird erneuert ...');
                $this->accessToken = null;
                return $this->requestJson($method, $uri, $options, true);
            }

            throw $e;
        } catch (\JsonException $e) {
            $this->logger->error('❌ JSON Decode Error: ' . $e->getMessage());
            return [];
        }
    }


    public function getRootCategoryId(): ?string
    {
        $this->ensureAccessToken();

        $endpoint = rtrim($this->apiUrl, '/') . '/api/sales-channel';
        $this->logger->info("📡 Rufe Sales-Channels von {$endpoint} ab ...");

        try {
            $data = $this->requestJson('GET', $endpoint);
        } catch (\Throwable $e) {
            $this->logger->error('❌ Fehler beim Abruf der Sales-Channels: ' . $e->getMessage());
            return null;
        }

        if (empty($data['data'])) {
            $this->logger->warning('⚠️ Keine Sales-Channels von der API erhalten.');
            return null;
        }

        foreach ($data['data'] as $channel) {
            $name = $channel['attributes']['name'] ?? 'Unbekannt';
            $navCategoryId = $channel['relationships']['navigationCategory']['data']['id'] ?? null;

            if ($navCategoryId && strcasecmp($name, $this->salesChannelName) === 0) {
                $this->logger->info("✅ Sales-Channel '{$name}' gefunden, NavigationCategoryId = {$navCategoryId}");
                return $navCategoryId;
            }
        }

        $this->logger->warning("⚠️ Kein Sales-Channel mit Namen '{$this->salesChannelName}' gefunden!");
        return null;
    }

    public function createCategory(array $payload): string
    {
        $this->logger->debug('📦 Erstelle Kategorie: ' . json_encode($payload));

        $data = $this->requestJson('POST', 'api/category', ['json' => $payload]);
        return $data['data']['id'] ?? '';
    }
}
