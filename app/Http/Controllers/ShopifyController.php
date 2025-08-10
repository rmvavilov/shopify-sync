<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ShopifyClient;
use App\Services\ShopifySyncService;
use App\Models\Product;

class ShopifyController extends Controller
{
    public function products(Request $request, ShopifyClient $client, ShopifySyncService $syncService)
    {
        $mode = config('services.shopify.mode', 'proxy');

        if ($mode === 'local') {
            // local: DB data
            $items = Product::query()
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->shopify_id,
                    'title' => $p->title,
                    'description' => $p->description,
                    'category' => $p->category,
                ])
                ->values();

            return response()->json($items);
        }

        // proxy: Shopify GraphQL realtime data
        $query = <<<'GQL'
query GetProducts($first:Int!) {
  products(first: $first, sortKey: UPDATED_AT) {
    edges {
      node {
        id
        title
        descriptionHtml
        handle
        status
        onlineStoreUrl
        onlineStorePreviewUrl
        productType
        totalInventory
        featuredMedia {
          ... on MediaImage {
            image { url(transform: {maxWidth: 360}) altText width height }
            preview {
              image { url(transform: {maxWidth: 800}) altText width height }
            }
          }
        }

        variants(first: 1) {
          edges {
            node {
              id
              price
              compareAtPrice
              image { url(transform: {maxWidth: 800}) altText width height }
            }
          }
        }
      }
    }
  }
}
GQL;

        $data = $client->graphql($query, ['first' => 10]);

        // TODO: move local/proxy to separate methods
        // foreach (($data['data']['products']['edges'] ?? []) as $edge) {
        //     $syncService->upsertFromShopifyNode($edge['node']);
        // }

        $items = collect($data['data']['products']['edges'] ?? [])
            ->pluck('node')
            ->map(function ($n) use ($data) {
                $featured = data_get($n, 'featuredMedia.image.url');
                $fallback = data_get($n, 'media.nodes.0.image.url');
                $imageUrl = $featured ?: $fallback;

                $nodes = data_get($n, 'media.nodes', []);
                $img0 = data_get($nodes, '0.image.url') ?: data_get($nodes, '0.preview.image.url');
                $img1 = data_get($nodes, '1.image.url') ?: data_get($nodes, '1.preview.image.url');

                $min = data_get($n, 'priceRangeV2.minVariantPrice.amount');
                $max = data_get($n, 'priceRangeV2.maxVariantPrice.amount');
                $currency = data_get($n, 'priceRangeV2.minVariantPrice.currencyCode')
                    ?: data_get($data, 'data.shop.currencyCode');

                $v0 = data_get($n, 'variants.edges.0.node');

                return [
                    'id' => $n['id'],
                    'title' => $n['title'],
                    'description' => $n['descriptionHtml'],
                    'category' => $n['productType'] ?? null,
                    'total' => $n['totalInventory'] ?? null,

                    'priceMin' => $min !== null ? (float)$min : null,
                    'priceMax' => $max !== null ? (float)$max : null,
                    'currency' => $currency,

                    'imageVariant' => $imageUrl ?? data_get($v0, 'image.url'),

                    'image' => $img0 ?: data_get($n, 'featuredMedia.image.url') ?: data_get($n, 'featuredMedia.preview.image.url'),
                    'imageAlt' => $img1,

                    'variant' => $v0 ? [
                        'id' => $v0['id'],
                        'price' => isset($v0['price']) ? (float)$v0['price'] : null,
                        'compareAt' => isset($v0['compareAtPrice']) ? (float)$v0['compareAtPrice'] : null,
                        'currency' => $currency,
                        'image' => data_get($v0, 'image.url'),
                    ] : null,

                    'handle' => $n['handle'],
                    'onlineStoreUrl' => $n['onlineStoreUrl'],
                    'onlineStorePreviewUrl' => $n['onlineStorePreviewUrl'],
                    'status' => $n['status'],

                ];
            })
            ->values();

        return response()->json($items);
    }
}
