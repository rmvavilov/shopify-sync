<?php

namespace App\Console\Commands;

use App\Shopify\Services\SyncService as ShopifySyncService;
use App\Shopify\Client\ShopifyClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ShopifySyncCommand extends Command
{
    /**
     * php artisan shopify:sync --first=100 --since=2025-01-01 --status=ACTIVE --max=1000 --after=xxx --dry
     */
    protected $signature = 'shopify:sync
        {--first=100 : Page size (1..250)}
        {--since= : Only products updated after this datetime (e.g. 2025-01-01 or 2025-01-01T00:00:00Z)}
        {--status= : Filter by status: ACTIVE|DRAFT|ARCHIVED}
        {--after= : Resume from cursor}
        {--max= : Stop after syncing N products}
        {--dry : Dry-run (do not write to DB)}
    ';

    protected $description = 'Sync products from Shopify (cursor-based, with filters)';

    public function handle(ShopifyClient $client, ShopifySyncService $sync): int
    {
        $first = max(1, min(250, (int)$this->option('first')));

        $sinceOpt = $this->option('since');
        $statusOpt = strtoupper((string)$this->option('status'));
        $after = $this->option('after') ?: null;
        $max = $this->option('max') !== null ? (int)$this->option('max') : null;
        $dry = (bool)$this->option('dry');

        $parts = [];

        if ($sinceOpt) {
            try {
                $since = Carbon::parse($sinceOpt)->utc()->format('Y-m-d\TH:i:s\Z');
                $parts[] = "updated_at:>={$since}";
            } catch (\Throwable $e) {
                $this->error("Invalid --since value: {$sinceOpt}");
                return self::FAILURE;
            }
        }

        if (in_array($statusOpt, ['ACTIVE', 'DRAFT', 'ARCHIVED'], true)) {
            $parts[] = "status:{$statusOpt}";
        } elseif ($statusOpt !== '') {
            $this->warn("Unknown --status={$statusOpt}, ignoring.");
        }

        $queryString = $parts ? implode(' ', $parts) : null;

        $gql = <<<'GQL'
query SyncProducts($first:Int!, $after:String, $query:String) {
  products(first:$first, after:$after, sortKey:UPDATED_AT, reverse:true, query:$query) {
    pageInfo { hasNextPage endCursor }
    edges {
      cursor
      node {
        id
        title
        descriptionHtml
        handle
        status
        productType
        totalInventory

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
              price            # scalar Money
              compareAtPrice   # scalar Money
              image { url(transform:{maxWidth:800}) altText width height }
            }
          }
        }

        onlineStoreUrl
        onlineStorePreviewUrl
      }
    }
  }
}
GQL;

        $totalSynced = 0;
        $page = 0;
        $lastCursor = $after;

        $this->info('Starting Shopify sync...');
        if ($queryString) {
            $this->line("Query: {$queryString}");
        }
        if ($after) {
            $this->line("Resume after cursor: {$after}");
        }
        if ($dry) {
            $this->warn('DRY-RUN mode: DB will not be updated.');
        }

        while (true) {
            $page++;

            $vars = [
                'first' => $first,
                'after' => $lastCursor,
                'query' => $queryString,
            ];

            $resp = $this->callGraphqlWithRetry($client, $gql, $vars);
            if (!$resp) {
                $this->error('GraphQL request failed after retries.');
                return self::FAILURE;
            }

            $edges = data_get($resp, 'data.products.edges', []);
            $pageInfo = data_get($resp, 'data.products.pageInfo', []);
            $endCursor = data_get($pageInfo, 'endCursor');
            $hasNext = (bool)data_get($pageInfo, 'hasNextPage');

            $count = 0;
            foreach ($edges as $edge) {
                $node = $edge['node'] ?? null;
                if (!$node) {
                    continue;
                }
                $count++;

                if (!$dry) {
                    $sync->upsertFromShopifyNode($node);
                }

                $totalSynced++;
                if ($max !== null && $totalSynced >= $max) {
                    $this->info("Reached max={$max}. Last cursor: {$edge['cursor']}");
                    return self::SUCCESS;
                }
            }

            $this->line(sprintf(
                'Page %d: %d item(s). %s',
                $page,
                $count,
                $hasNext ? '→ next page' : '✓ done'
            ));

            if (!$hasNext || $count === 0) {
                break;
            }

            $lastCursor = $endCursor;
        }

        $this->info("Synced {$totalSynced} product(s). Last cursor: " . ($lastCursor ?? 'null'));

        return self::SUCCESS;
    }

    protected function callGraphqlWithRetry(ShopifyClient $client, string $gql, array $vars, int $retries = 3): ?array
    {
        $attempt = 0;
        while ($attempt < $retries) {
            try {
                return $client->graphql($gql, $vars);
            } catch (\Throwable $e) {
                $attempt++;
                $code = method_exists($e, 'getCode') ? (int)$e->getCode() : 0;

                if ($attempt < $retries && ($code === 429 || $code >= 500)) {
                    $sleep = 2 * $attempt; // 2, 4, 6 сек
                    usleep($sleep * 1_000_000);
                    continue;
                }

                $this->error('GraphQL error: ' . $e->getMessage());
                return null;
            }
        }

        return null;
    }
}
