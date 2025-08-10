<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ShopifySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $options = [])
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $args = [];

        if (isset($this->options['first'])) $args['--first'] = (string)$this->options['first'];
        if (!empty($this->options['since'])) $args['--since'] = (string)$this->options['since'];
        if (!empty($this->options['status'])) $args['--status'] = (string)$this->options['status'];
        if (!empty($this->options['after'])) $args['--after'] = (string)$this->options['after'];
        if (!empty($this->options['max'])) $args['--max'] = (string)$this->options['max'];
        if (!empty($this->options['dry'])) $args['--dry'] = true;

        Log::info('[RunSyncJob] Starting shopify:sync', $args);

        Artisan::call('shopify:sync', $args);

        Log::info('[RunSyncJob] Finished shopify:sync');
    }
}
