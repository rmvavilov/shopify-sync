<?php

namespace App\Shopify\Services;

use App\Models\Product;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class SyncService
{
    /**
     *
     * @param array $node
     * @param string $source 'sync' | 'webhook'
     * @param string|null $webhookId
     */
    public function upsertFromShopifyNode(array $node, string $source = 'sync', ?string $webhookId = null): Product
    {
        $gid = $node['id'] ?? null;
        if (!$gid) {
            throw new \InvalidArgumentException('Product node is missing "id".');
        }

        $incomingIso = Arr::get($node, 'updatedAt'); // ISO8601
        $incomingTs = $incomingIso ? Carbon::parse($incomingIso) : null;

        $existing = Product::where('shopify_id', $gid)->first();

        $shouldWrite = !$existing
            || !$existing->remote_updated_at
            || ($incomingTs && $incomingTs->gt($existing->remote_updated_at));

        $business = $this->mapNodeToData($node);

        $meta = [
            'remote_updated_at' => $incomingIso,
            'last_synced_at' => now(),
            'last_sync_source' => $source,
            'last_webhook_id' => $webhookId,
        ];

        if ($shouldWrite) {
            return Product::updateOrCreate(
                ['shopify_id' => $gid],
                array_merge($business, $meta)
            );
        }

        if ($existing) {
            $existing->forceFill($meta)->save();
            return $existing;
        }

        return Product::updateOrCreate(
            ['shopify_id' => $gid],
            array_merge($business, $meta)
        );
    }

    protected function mapNodeToData(array $node): array
    {
        $nodes = Arr::get($node, 'media.nodes', []);
        $img0 = Arr::get($nodes, '0.image.url') ?: Arr::get($nodes, '0.preview.image.url');
        $img1 = Arr::get($nodes, '1.image.url') ?: Arr::get($nodes, '1.preview.image.url');
        $featured = Arr::get($node, 'featuredMedia.image.url')
            ?: Arr::get($node, 'featuredMedia.preview.image.url');

        $min = Arr::get($node, 'priceRangeV2.minVariantPrice.amount');
        $max = Arr::get($node, 'priceRangeV2.maxVariantPrice.amount');
        $cur = Arr::get($node, 'priceRangeV2.minVariantPrice.currencyCode');

        $v0 = Arr::get($node, 'variants.edges.0.node');

        return [
            'handle' => Arr::get($node, 'handle'),
            'status' => Arr::get($node, 'status'),
            'title' => Arr::get($node, 'title', ''),
            'description' => Arr::get($node, 'descriptionHtml'),
            'product_type' => Arr::get($node, 'productType'),
            'total_inventory' => Arr::get($node, 'totalInventory'),

            'price_min' => $min !== null ? (float)$min : null,
            'price_max' => $max !== null ? (float)$max : null,
            'currency' => $cur,

            'image' => $featured ?: $img0,
            'image_alt' => $img1,
            'image_variant' => Arr::get($v0, 'image.url'),

            'online_store_url' => Arr::get($node, 'onlineStoreUrl'),
            'online_store_preview_url' => Arr::get($node, 'onlineStorePreviewUrl'),

            'variant_id' => Arr::get($v0, 'id'),
            'variant_price' => is_array($v0) && array_key_exists('price', $v0) ? (float)$v0['price'] : null,
            'variant_compare_at' => is_array($v0) && array_key_exists('compareAtPrice', $v0) ? (float)$v0['compareAtPrice'] : null,
            'variant_image' => Arr::get($v0, 'image.url'),
        ];
    }
}
