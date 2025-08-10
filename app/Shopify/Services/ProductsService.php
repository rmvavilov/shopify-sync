<?php


namespace App\Shopify\Services;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Shopify\Client\ShopifyClient;
use App\Shopify\Transformers\ProductTransformer;
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
        $sortBy = $request->input('sortBy', []);
        $primarySort = $sortBy[0] ?? ['key' => 'updated_at', 'order' => 'desc'];
        $key = $primarySort['key'] ?? 'updated_at';
        $dir = ($primarySort['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $qb = Product::query();

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('productType', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $map = [
            'title' => 'title',
            'category' => 'productType',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'id' => 'id',
        ];
        $qb->orderBy($map[$key] ?? 'id', $dir);

        $total = $qb->count();
        $rows = $qb->skip(($page - 1) * $per)->take($per)->get();

        $items = $rows->map(fn($p) => ProductTransformer::fromLocalModel($p))->values();

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
        $sortBy = $request->input('sortBy', []);
        $primarySort = $sortBy[0] ?? ['key' => 'updated_at', 'order' => 'desc'];
        $key = $primarySort['key'] ?? 'updated_at';
        $dir = ($primarySort['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

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
            if ($after) $vars['after'] = $after;
        }

        if ($q !== '') {
            $safe = str_replace(['"', "'"], '', $q);
            $vars['query'] = "title:*{$safe}* OR product_type:*{$safe}*";
        }

        $query = <<<'GQL'
query GetProducts(
  $first:Int, $last:Int, $after:String, $before:String,
  $sortKey: ProductSortKeys, $reverse:Boolean, $query:String
) {
  shop { currencyCode }
  products(first:$first, last:$last, after:$after, before:$before, sortKey:$sortKey, reverse:$reverse, query:$query) {
    pageInfo { hasNextPage hasPreviousPage startCursor endCursor }
    edges {
      cursor
      node {
        id title descriptionHtml handle status
        onlineStoreUrl onlineStorePreviewUrl productType totalInventory
        priceRangeV2 {
          minVariantPrice { amount currencyCode }
          maxVariantPrice { amount currencyCode }
        }
        featuredMedia {
          ... on MediaImage {
            image   { url(transform:{maxWidth:360}) altText width height }
            preview { image { url(transform:{maxWidth:800}) altText width height } }
          }
          ... on Video        { preview { image { url(transform:{maxWidth:800}) altText width height } } }
          ... on ExternalVideo{ preview { image { url(transform:{maxWidth:800}) altText width height } } }
          ... on Model3d      { preview { image { url(transform:{maxWidth:800}) altText width height } } }
        }
        media(first:2) {
          nodes {
            ... on MediaImage {
              image   { url(transform:{maxWidth:800}) altText width height }
              preview { image { url(transform:{maxWidth:800}) altText width height } }
            }
            ... on Video        { preview { image { url(transform:{maxWidth:800}) altText width height } } }
            ... on ExternalVideo{ preview { image { url(transform:{maxWidth:800}) altText width height } } }
            ... on Model3d      { preview { image { url(transform:{maxWidth:800}) altText width height } } }
          }
        }
        variants(first:1) {
          edges {
            node {
              id
              price
              compareAtPrice
              image { url(transform:{maxWidth:800}) altText width height }
            }
          }
        }
      }
    }
  }
}
GQL;

        $data = $this->client->graphql($query, $vars);

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
