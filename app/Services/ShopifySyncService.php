<?php

namespace App\Services;

use App\Models\Product;

class ShopifySyncService
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
                'category' => $node['productType'] ?? null,
            ]
        );
    }
}
