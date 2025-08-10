<?php

namespace App\Shopify\Services;

use App\Models\Product;

class SyncService
{
    public function upsertFromShopifyNode(array $node): Product
    {
        return Product::updateOrCreate(
            [
                'shopify_id' => $node['id']
            ],
            [
                'title' => $node['title'] ?? '',
                'description' => $node['descriptionHtml'] ?? '',
                'productType' => $node['productType'] ?? null,
            ]
        );
    }
}
