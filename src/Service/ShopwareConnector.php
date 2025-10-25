<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

final class ShopwareConnector
{
    private Client $http;
    private string $apiBase;
    private ?string $integrationLabel = null;
    private string $accessKeyId;
    private string $accessKeySecret;
    private ?string $accessToken = null;
    private LoggerInterface $logger;

    public function __construct(string $apiUrl, string $accessKeyId, string $accessKeySecret, LoggerInterface $logger, ?string $integrationLabel = null)
    {
        $this->apiBase = rtrim($apiUrl, '/');
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->logger = $logger;
        $this->integrationLabel = $integrationLabel;
        $this->http = new \GuzzleHttp\Client(['base_uri' => $this->apiBase]);
    }


    private function ensureAccessToken(): void
    {
        if ($this->accessToken) {
            return;
        }
        $this->refreshAccessToken();
    }

    private function refreshAccessToken(): void
    {
        $res = $this->http->post($this->apiBase . '/api/oauth/token', [
            'json' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->accessKeyId,
                'client_secret' => $this->accessKeySecret,
            ],
        ]);

        $data = json_decode((string)$res->getBody(), true);
        $this->accessToken = $data['access_token'] ?? null;

        if (!$this->accessToken) {
            throw new \RuntimeException('Access Token konnte nicht abgerufen werden.');
        }

        $this->logger->info('Shopware Access-Token erfolgreich erneuert.');
    }

    public function requestJson(string $method, string $uri, array $options = [], bool $didRetry = false): array
    {
        $this->ensureAccessToken();
        $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
        $options['headers']['Accept'] = 'application/json';

        /** @var \Psr\Http\Message\ResponseInterface|null $res */
        $res = null;

        try {
            $res = $this->http->request($method, $uri, $options);
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === 401 && !$didRetry) {
                $this->logger->warning('401 Unauthorized – Token wird erneuert.');
                $this->refreshAccessToken();
                return $this->requestJson($method, $uri, $options, true);
            }

            if ($e->hasResponse()) {
                $this->logger->error("❌ Fehlerhafte API-Antwort ({$method} {$uri}): " . (string)$e->getResponse()->getBody());
            }
            throw $e;
        }

        if (!$res) {
            $this->logger->error("❌ Keine Antwort erhalten für {$method} {$uri}.");
            return [];
        }

        $status = $res->getStatusCode();
        $body = (string)$res->getBody();

        if (empty($body)) {
            if ($status === 204) {
                $this->logger->info("✅ {$method} {$uri} erfolgreich (HTTP 204 – kein Inhalt).");
                return [];
            }
            $this->logger->warning("⚠️ Leere Antwort von Shopware ({$status}) für {$method} {$uri}.");
            return [];
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error("❌ JSON Decode Error bei {$method} {$uri}: " . $e->getMessage());
            $this->logger->debug("Antwort-Inhalt: " . $body);
            throw $e;
        }
    }

    public function getRootCategoryId(?string $salesChannelName = null): ?string
    {
        $salesChannelName = $salesChannelName ?? 'Storefront';

        // 🧠 1️⃣ Integration prüfen (über /api/integration)
        try {
            $integrations = $this->requestJson('GET', $this->apiBase . '/api/integration');
            $found = false;

            foreach ($integrations['data'] ?? [] as $integration) {
                $label = $integration['attributes']['label'] ?? '';
                if (strcasecmp($label, $this->integrationLabel ?? 'OxidMigration') === 0) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $this->logger->warning("⚠️ Keine Shopware-Integration mit Label '{$this->integrationLabel}' gefunden. Verwende Plugin-Konfiguration.");
            } else {
                $this->logger->info("✅ Shopware-Integration '{$this->integrationLabel}' erfolgreich gefunden.");
            }
        } catch (\Throwable $e) {
            $this->logger->error('❌ Fehler beim Prüfen der Integration: ' . $e->getMessage());
        }

        // 🪴 2️⃣ Sales-Channels abrufen
        try {
            $channels = $this->requestJson('GET', $this->apiBase . '/api/sales-channel');
            if (empty($channels['data'])) {
                $this->logger->error('❌ Keine Sales-Channels gefunden.');
                return null;
            }

            foreach ($channels['data'] as $sc) {
                $name = $sc['attributes']['name'] ?? '';
                if (strcasecmp($name, $salesChannelName) === 0) {
                    $id = $sc['relationships']['navigationCategory']['data']['id'] ?? null;
                    if ($id) {
                        $this->logger->info("🪴 Root-Kategorie des Sales-Channels '{$salesChannelName}': {$id}");
                        return $id;
                    }
                }
            }

            // 🧩 Fallback: ersten Channel nehmen
            $fallback = $channels['data'][0]['relationships']['navigationCategory']['data']['id'] ?? null;
            if ($fallback) {
                $this->logger->warning("⚠️ Verwende Root-Kategorie des ersten Sales-Channels als Fallback: {$fallback}");
                return $fallback;
            }
        } catch (\Throwable $e) {
            $this->logger->error('❌ Fehler beim Laden der Sales-Channels: ' . $e->getMessage());
        }

        // 🚨 3️⃣ Wenn gar nichts gefunden wurde
        $this->logger->error('❌ Keine gültige Root-Kategorie gefunden.');
        return null;
    }

    public function createCategory(array $payload): string
    {
        // leere Felder entfernen
        $clean = array_filter(
            $payload,
            static fn($v) => $v !== null && $v !== ''
        );

        $res = $this->requestJson('POST', $this->apiBase . '/api/category', [
            'json' => $clean,
        ]);

        return $res['data']['id'] ?? ($res['id'] ?? '');
    }
}
