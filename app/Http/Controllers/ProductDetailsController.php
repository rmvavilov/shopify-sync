<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Shopify\Client\ShopifyClient;
use App\Shopify\Queries\ProductFragments;
use App\Shopify\Transformers\ProductTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProductDetailsController extends Controller
{
    public function showProxy(string $id64, ShopifyClient $client)
    {
        $gid = $this->decodeId($id64);
        if (!$gid) return response()->json(['message' => 'Invalid id'], 422);

        $gql = ProductFragments::fields() . <<<'GQL'
query ProductDetails($id: ID!) {
  shop { currencyCode }
  product(id: $id) { ...ProductFields }
}
GQL;
        $resp = $client->graphql($gql, ['id' => $gid]);

        $node = Arr::get($resp, 'data.product');
        if (!$node) return response()->json(['message' => 'Not found'], 404);

        $currency = Arr::get($resp, 'data.shop.currencyCode');
        $item = ProductTransformer::fromGraphQL($node, $currency, null);

        return response()->json([
            'mode' => 'proxy',
            'product' => $item,
        ]);
    }

    public function showLocal(string $id64)
    {
        $gid = $this->decodeId($id64);
        if (!$gid) return response()->json(['message' => 'Invalid id'], 422);

        $p = Product::where('shopify_id', $gid)->first();
        if (!$p) return response()->json(['message' => 'Not found'], 404);

        return response()->json([
            'mode' => 'local',
            'product' => $p->toApiProduct(),
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
