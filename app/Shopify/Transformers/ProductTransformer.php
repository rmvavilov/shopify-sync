<?php

namespace App\Shopify\Transformers;

use Illuminate\Support\Arr;

class ProductTransformer
{
    /**
     * @param array $node product node from GraphQL
     * @param string|null $shopCurrency
     * @param string|null $cursor
     */
    public static function fromGraphQL(array $node, ?string $shopCurrency = null, ?string $cursor = null): array
    {
        $nodes = Arr::get($node, 'media.nodes', []);
        $img0 = Arr::get($nodes, '0.image.url') ?: Arr::get($nodes, '0.preview.image.url');
        $img1 = Arr::get($nodes, '1.image.url') ?: Arr::get($nodes, '1.preview.image.url');
        $featured = Arr::get($node, 'featuredMedia.image.url') ?: Arr::get($node, 'featuredMedia.preview.image.url');

        $min = Arr::get($node, 'priceRangeV2.minVariantPrice.amount');
        $max = Arr::get($node, 'priceRangeV2.maxVariantPrice.amount');
        $cur = Arr::get($node, 'priceRangeV2.minVariantPrice.currencyCode') ?: $shopCurrency;

        $v0 = Arr::get($node, 'variants.edges.0.node');

        return [
            'id' => $node['id'],
            'title' => $node['title'],
            'description' => $node['descriptionHtml'],
            'category' => $node['productType'] ?? null,
            'total' => $node['totalInventory'] ?? null,

            'priceMin' => $min !== null ? (float)$min : null,
            'priceMax' => $max !== null ? (float)$max : null,
            'currency' => $cur,

            'imageVariant' => Arr::get($v0, 'image.url'),
            'image' => $featured ?: $img0,
            'imageAlt' => $img1,

            'variant' => $v0 ? [
                'id' => Arr::get($v0, 'id'),
                'price' => array_key_exists('price', $v0) ? (float)$v0['price'] : null,          // scalar Money
                'compareAt' => array_key_exists('compareAtPrice', $v0) ? (float)$v0['compareAtPrice'] : null,
                'currency' => $cur,
                'image' => Arr::get($v0, 'image.url'),
            ] : null,

            'handle' => Arr::get($node, 'handle'),
            'onlineStoreUrl' => Arr::get($node, 'onlineStoreUrl'),
            'onlineStorePreviewUrl' => Arr::get($node, 'onlineStorePreviewUrl'),
            'status' => Arr::get($node, 'status'),
            'cursor' => $cursor,
            'updatedAt' => Arr::get($node, 'updatedAt'), // Shopify ISO8601
        ];
    }

    /**
     * Displaying the local product model (if local mode enabled)
     */
    public static function fromLocalModel($p): array
    {
        return [
            'id' => $p->shopify_id,
            'title' => $p->title,
            'description' => $p->description,
            'category' => $p->productType,
            'total' => null,
            'priceMin' => null,
            'priceMax' => null,
            'currency' => null,
            'imageVariant' => null,
            'image' => null,
            'imageAlt' => null,
            'variant' => null,
            'handle' => null,
            'onlineStoreUrl' => null,
            'onlineStorePreviewUrl' => null,
            'status' => null,
        ];
    }
}
