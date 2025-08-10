<?php

namespace App\Http\Controllers;

use App\Jobs\ShopifySyncJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Artisan;

class ShopifyFullSyncController extends Controller
{
    public function syncAll(Request $request)
    {
        // TODO: only admin can run sync
        // $this->authorize('sync-shopify');

        $data = $request->validate([
            'first' => ['nullable', 'integer', 'min:1', 'max:250'],
            'since' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'DRAFT', 'ARCHIVED'])],
            'after' => ['nullable', 'string'],
            'max' => ['nullable', 'integer', 'min:1'],
            'dry' => ['nullable', 'boolean'],
        ]);

        $modeOverride = $request->string('mode')->lower()->value(); // 'job'|'direct'|''

        $driver = in_array($modeOverride, ['job', 'direct'], true)
            ? $modeOverride
            : config('services.shopify.sync.driver', 'job');

        return $driver === 'direct'
            ? $this->runDirect($data)     // direct sync
            : $this->enqueueJob($data);   // job sync
    }

    private function enqueueJob(array $options)
    {
        ShopifySyncJob::dispatch($options);

        return response()->json([
            'status' => 'started',
            'mode' => 'job',
            'queued' => true,
            'params' => $options,
        ], 202);
    }

    private function runDirect(array $options)
    {
        $timeout = (int)config('services.shopify.sync.timeout', 0);
        if ($timeout > 0) {
            set_time_limit($timeout);
            ini_set('max_execution_time', (string)$timeout);
        } else {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
        }

        $args = [];
        if (isset($options['first'])) $args['--first'] = (string)$options['first'];
        if (!empty($options['since'])) $args['--since'] = (string)$options['since'];
        if (!empty($options['status'])) $args['--status'] = (string)$options['status'];
        if (!empty($options['after'])) $args['--after'] = (string)$options['after'];
        if (!empty($options['max'])) $args['--max'] = (string)$options['max'];
        if (!empty($options['dry'])) $args['--dry'] = true;

        $exitCode = Artisan::call('shopify:sync', $args);
        $output = Artisan::output();

        return response()->json([
            'status' => $exitCode === 0 ? 'ok' : 'error',
            'mode' => 'direct',
            'exitCode' => $exitCode,
            'output' => mb_substr($output, -4000),
            'params' => $args,
        ], $exitCode === 0 ? 200 : 500);
    }
}
