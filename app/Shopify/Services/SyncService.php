<?php

namespace App\Shopify\Services;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\SoftDeletes;

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

        $incomingIso = Arr::get($node, 'updatedAt'); // ISO8601|null
        $incomingTs = $incomingIso ? Carbon::parse($incomingIso) : null;

        $business = $this->mapNodeToData($node);
        $metaCommon = [
            'last_synced_at' => now(),
            'last_sync_source' => $source,
            'last_webhook_id' => $webhookId,
        ];

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive(Product::class), true);

        return DB::transaction(function () use (
            $gid, $incomingIso, $incomingTs, $business, $metaCommon, $usesSoftDeletes
        ) {
            $q = Product::query();
            if ($usesSoftDeletes) {
                $q->withTrashed();
            }
            $existing = $q->where('shopify_id', $gid)->first();

            if ($existing) {
                if ($usesSoftDeletes && method_exists($existing, 'trashed') && $existing->trashed()) {
                    $existing->restore();
                }

                $shouldWrite = !$existing->remote_updated_at
                    || ($incomingTs && $incomingTs->gt($existing->remote_updated_at));

                if ($shouldWrite) {
                    $existing->fill(array_merge(
                        $business,
                        $metaCommon,
                        ['remote_updated_at' => $incomingIso]
                    ))->save();
                } else {
                    $existing->forceFill($metaCommon)->save();
                }

                return $existing->refresh();
            }

            try {
                return Product::create(array_merge(
                    ['shopify_id' => $gid],
                    $business,
                    $metaCommon,
                    ['remote_updated_at' => $incomingIso]
                ));
            } catch (QueryException $e) {
                if (Str::contains($e->getMessage(), 'Duplicate entry') && Str::contains($e->getMessage(), 'shopify_id')) {
                    $found = Product::query()->where('shopify_id', $gid)->first();
                    if ($found) {
                        $shouldWrite = !$found->remote_updated_at
                            || ($incomingTs && $incomingTs->gt($found->remote_updated_at));

                        if ($shouldWrite) {
                            $found->fill(array_merge(
                                $business,
                                $metaCommon,
                                ['remote_updated_at' => $incomingIso]
                            ))->save();
                        } else {
                            $found->forceFill($metaCommon)->save();
                        }
                        return $found->refresh();
                    }
                }
                throw $e;
            }
        });
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
