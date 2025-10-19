<?php

namespace MigrationSwinde\MigrationOxidToShopware\Service;

class ShopwareConnector
{
    private string $url;
    private string $accessKeyId;
    private string $accessKeySecret;
    private ?string $token = null;

    public function __construct(array $config)
    {
        $this->url = rtrim($config['url'], '/');
        $this->accessKeyId = $config['access_key_id'];
        $this->accessKeySecret = $config['access_key_secret'];
    }

    public function getToken(): string
    {
        if ($this->token) {
            return $this->token;
        }

        $ch = curl_init($this->url . '/api/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'client_id' => $this->accessKeyId,
            'client_secret' => $this->accessKeySecret,
            'grant_type' => 'client_credentials'
        ]);

        $response = curl_exec($ch);
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new \RuntimeException('❌ Shopware Token konnte nicht abgerufen werden');
        }

        $this->token = $data['access_token'];
        return $this->token;
    }
}