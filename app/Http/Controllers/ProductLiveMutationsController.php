<?php

namespace App\Http\Controllers;

use App\Shopify\Client\ShopifyClient;
use App\Shopify\Queries\ProductFragments;
use App\Shopify\Services\SyncService;
use App\Shopify\Transformers\ProductTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use App\Models\Product;

class ProductLiveMutationsController extends Controller
{
    public function update(string $id64, Request $request, ShopifyClient $client, SyncService $sync): JsonResponse
    {
        $gid = $this->decodeId($id64);
        if (!$gid) {
            return response()->json(['message' => 'Invalid id'], 422);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'DRAFT', 'ARCHIVED'])],
            'productType' => ['nullable', 'string', 'max:255'],
            'descriptionHtml' => ['nullable', 'string'],
            'expectedUpdatedAt' => ['nullable', 'string'],
        ]);

        if (!empty($data['expectedUpdatedAt'])) {
            $checkGql = <<<'GQL'
query($id:ID!){ product(id:$id){ id updatedAt } }
GQL;
            $check = $client->graphql($checkGql, ['id' => $gid]);
            $current = Arr::get($check, 'data.product.updatedAt');
            if ($current && $current !== $data['expectedUpdatedAt']) {
                return response()->json([
                    'message' => 'Product has changed on Shopify',
                    'code' => 'VERSION_CONFLICT',
                    'currentUpdatedAt' => $current,
                    'expectedUpdatedAt' => $data['expectedUpdatedAt'],
                ], 409);
            }
        }

        $input = array_filter([
            'id' => $gid,
            'title' => $data['title'] ?? null,
            'handle' => $data['handle'] ?? null,
            'status' => $data['status'] ?? null,
            'productType' => $data['productType'] ?? null,
            'descriptionHtml' => $data['descriptionHtml'] ?? null,
        ], fn($v) => $v !== null);

        $mutation = ProductFragments::fields() . <<<'GQL'
mutation UpdateProduct($input: ProductInput!) {
  productUpdate(input: $input) {
    product { ...ProductFields }
    userErrors { field message }
  }
}
GQL;

        $resp = $client->graphql($mutation, ['input' => $input]);

        $errors = Arr::get($resp, 'data.productUpdate.userErrors', []);
        if (!empty($errors)) {
            return response()->json(['message' => 'Shopify validation failed', 'errors' => $errors], 422);
        }

        $node = Arr::get($resp, 'data.productUpdate.product');
        if (!$node) {
            return response()->json(['message' => 'Update failed'], 500);
        }

        $model = $sync->upsertFromShopifyNode($node, 'sync', null);

        $item = ProductTransformer::fromGraphQL($node, null, null);

        return response()->json(['status' => 'ok', 'product' => $item]);
    }

    public function destroy(string $id64, ShopifyClient $client, SyncService $sync): JsonResponse
    {
        $gid = $this->decodeId($id64);
        if (!$gid) {
            return response()->json(['message' => 'Invalid id'], 422);
        }

        $mutation = <<<'GQL'
mutation DeleteProduct($input: ProductDeleteInput!) {
  productDelete(input: $input) {
    deletedProductId
    userErrors { field message }
  }
}
GQL;


        $resp = $client->graphql($mutation, ['input' => ['id' => $gid]]);

        $errors = Arr::get($resp, 'data.productDelete.userErrors', []);
        if (!empty($errors)) {
            return response()->json(['message' => 'Delete failed', 'errors' => $errors], 422);
        }

        Product::where('shopify_id', $gid)->update([
            'status' => 'ARCHIVED',
            'last_synced_at' => now(),
            'last_sync_source' => 'sync',
            'deleted_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'deletedProductId' => Arr::get($resp, 'data.productDelete.deletedProductId')]);
    }

    private function decodeId(string $id64): ?string
    {
        $pad = str_repeat('=', (4 - (strlen($id64) % 4)) % 4);
        $b64 = strtr($id64, '-_', '+/') . $pad;
        $gid = base64_decode($b64, true);
        return $gid !== false ? $gid : null;
    }
}
