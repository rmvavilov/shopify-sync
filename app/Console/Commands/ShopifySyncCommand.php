<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyClient;
use App\Services\ShopifySyncService;

class ShopifySyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync {--first=50}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Shopify';

    /**
     * Execute the console command.
     */
    public function handle(ShopifyClient $client, ShopifySyncService $sync)
    {
        $first = (int)$this->option('first');

        $query = <<<'GQL'
query Sync($first:Int!) {
  products(first: $first, sortKey: UPDATED_AT) {
    edges {
      node {
        id
        title
        descriptionHtml
        productType
      }
    }
  }
}
GQL;
        $data = $client->graphql($query, ['first' => $first]);

        foreach (($data['data']['products']['edges'] ?? []) as $edge) {
            $sync->upsertFromShopifyNode($edge['node']);
        }

        $this->info('Synced.');
    }
}
