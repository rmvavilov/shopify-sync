<?php

namespace App\Shopify\Client;

use Illuminate\Support\Facades\Http;

class ShopifyClient
{
    public function __construct(
        private ?string $domain = null,
        private ?string $token = null,
        private ?string $version = null,
    )
    {
        $this->domain ??= rtrim(config('services.shopify.domain'), '/');
        $this->token ??= config('services.shopify.token');
        $this->version ??= config('services.shopify.version', '2025-04');
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
