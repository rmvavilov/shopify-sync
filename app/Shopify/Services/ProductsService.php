<?php

namespace App\Shopify\Services;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Shopify\Client\ShopifyClient;
use App\Shopify\Transformers\ProductTransformer;
use App\Shopify\Queries\ProductFragments;
use Illuminate\Support\Arr;

class ProductsService
{
    public function __construct(
        private ShopifyClient $client,
        private SyncService   $syncService, // TODO: implement
    )
    {
    }

    public function productsLocal(Request $request): array
    {
        $page = max(1, (int)$request->integer('page', 1));
        $per = min(max((int)$request->integer('itemsPerPage', 10), 1), 100);
        $q = trim((string)$request->get('q', ''));
        $sortBy = (array)$request->input('sortBy', []);
        $primary = $sortBy[0] ?? ['key' => 'updated_at', 'order' => 'desc'];
        $key = (string)($primary['key'] ?? 'updated_at');
        $dir = strtolower((string)($primary['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $qb = Product::query();

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('product_type', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('handle', 'like', "%{$q}%")
                    ->orWhere('status', 'like', "%{$q}%")
                    ->orWhere('shopify_id', 'like', "%{$q}%");
            });
        }

        $sortMap = [
            'title' => 'title',
            'category' => 'product_type',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'id' => 'shopify_id',
        ];
        $qb->orderBy($sortMap[$key] ?? 'id', $dir);

        $total = (clone $qb)->count();
        $rows = $qb->forPage($page, $per)->get();

        $items = $rows->map(fn(Product $p) => $p->toApiProduct())->values();

        return [
            'mode' => 'local',
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'itemsPerPage' => $per,
        ];
    }

    public function productsProxy(Request $request): array
    {
        $per = min(max((int)$request->integer('itemsPerPage', 10), 1), 100);
        $q = trim((string)$request->get('q', ''));
        $sortBy = (array)$request->input('sortBy', []);
        $primary = $sortBy[0] ?? ['key' => 'updated_at', 'order' => 'desc'];
        $key = (string)($primary['key'] ?? 'updated_at');
        $dir = strtolower((string)($primary['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortKeyMap = [
            'title' => 'TITLE',
            'created_at' => 'CREATED_AT',
            'updated_at' => 'UPDATED_AT',
            'id' => 'ID',
        ];
        $sortKey = $sortKeyMap[$key] ?? 'UPDATED_AT';
        $reverse = ($dir === 'desc');

        $after = $request->string('after')->toString() ?: null;
        $before = $request->string('before')->toString() ?: null;
        $direction = $request->string('direction')->toString(); // 'next'|'prev'|''

        $vars = [
            'sortKey' => $sortKey,
            'reverse' => $reverse,
        ];
        if ($direction === 'prev' && $before) {
            $vars['last'] = $per;
            $vars['before'] = $before;
        } else {
            $vars['first'] = $per;
            if ($after) {
                $vars['after'] = $after;
            }
        }

        // Простой поиск по title/product_type
        if ($q !== '') {
            $safe = str_replace(['"', "'"], '', $q);
            $vars['query'] = "title:*{$safe}* OR product_type:*{$safe}*";
        }

        // ЕДИНЫЙ набор полей через общий фрагмент
        $gql = ProductFragments::fields() . <<<'GQL'
query GetProducts(
  $first:Int, $last:Int, $after:String, $before:String,
  $sortKey: ProductSortKeys, $reverse:Boolean, $query:String
) {
  shop { currencyCode }
  products(first:$first, last:$last, after:$after, before:$before, sortKey:$sortKey, reverse:$reverse, query:$query) {
    pageInfo { hasNextPage hasPreviousPage startCursor endCursor }
    edges {
      cursor
      node { ...ProductFields }
    }
  }
}
GQL;

        $data = $this->client->graphql($gql, $vars);

        $conn = Arr::get($data, 'data.products');
        $shopCurr = Arr::get($data, 'data.shop.currencyCode');

        $items = collect(Arr::get($conn, 'edges', []))
            ->map(fn($edge) => ProductTransformer::fromGraphQL(
                $edge['node'] ?? [],
                $shopCurr,
                $edge['cursor'] ?? null
            ))
            ->values();

        return [
            'mode' => 'proxy',
            'items' => $items,
            'pageInfo' => Arr::get($conn, 'pageInfo'),
            'itemsPerPage' => $per,
        ];
    }
}
