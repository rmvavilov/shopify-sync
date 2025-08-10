<?php

namespace App\Http\Controllers;

use App\Shopify\Client\ShopifyClient;
use App\Shopify\Queries\ProductFragments;
use App\Shopify\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProductSyncController extends Controller
{
    public function syncOne(string $id64, Request $request, ShopifyClient $client, SyncService $sync): JsonResponse
    {
        $gid = $this->decodeId($id64);
        if (!$gid) {
            return response()->json(['message' => 'Invalid id'], 422);
        }

        $gql = ProductFragments::fields() . <<<'GQL'
query ProductDetails($id: ID!) {
  product(id: $id) { ...ProductFields }
}
GQL;

        try {
            $resp = $client->graphql($gql, ['id' => $gid]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Shopify request failed', 'error' => $e->getMessage()], 502);
        }

        $node = Arr::get($resp, 'data.product');
        if (!$node) {
            return response()->json(['message' => 'Product not found on Shopify'], 404);
        }

        $model = $sync->upsertFromShopifyNode($node, 'sync', null);

        $model->refresh();
        return response()->json([
            'status' => 'ok',
            'product' => $model->toApiProduct(),
        ]);
    }

    private function decodeId(string $id64): ?string
    {
        $pad = str_repeat('=', (4 - (strlen($id64) % 4)) % 4);
        $b64 = strtr($id64, '-_', '+/') . $pad;
        $gid = base64_decode($b64, true);
        return $gid !== false ? $gid : null;
    }
}
