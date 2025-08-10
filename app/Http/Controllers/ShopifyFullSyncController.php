<?php

namespace App\Http\Controllers;

use App\Jobs\ShopifySyncJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ShopifyFullSyncController extends Controller
{
    public function syncAll(Request $request)
    {
        // TODO: only admin can run sync
        // $this->authorize('sync-shopify');

        $data = $request->validate([
            'first' => ['nullable', 'integer', 'min:1', 'max:250'],
            'since' => ['nullable', 'string'], // ISO8601
            'status' => ['nullable', Rule::in(['ACTIVE', 'DRAFT', 'ARCHIVED'])],
            'after' => ['nullable', 'string'],
            'max' => ['nullable', 'integer', 'min:1'],
            'dry' => ['nullable', 'boolean'],
        ]);

        ShopifySyncJob::dispatch($data);

        Cache::put('shopify.sync.last_requested_at', now(), 60 * 60 * 24 * 30);

        return response()->json([
            'status' => 'started',
            'queued' => true,
            'params' => $data,
        ], 202);
    }
}
