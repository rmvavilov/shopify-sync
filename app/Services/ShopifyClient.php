<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyClient
{
    protected string $domain;
    protected string $token;
    protected string $version;

    public function __construct()
    {
        $this->domain  = rtrim(config('services.shopify.domain'), '/');
        $this->token   = config('services.shopify.token');
        $this->version = config('services.shopify.version');
    }

    public function graphql(string $query, array $variables = []): array
    {
        $url = "https://{$this->domain}/admin/api/{$this->version}/graphql.json";

        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $resp->throw();

        return $resp->json();
    }
}
