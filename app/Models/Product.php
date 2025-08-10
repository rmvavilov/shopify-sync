<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model
{
    protected $fillable = [
        'shopify_id',
        'handle',
        'status',
        'title',
        'description',
        'product_type',
        'total_inventory',
        'price_min',
        'price_max',
        'currency',
        'image',
        'image_alt',
        'image_variant',
        'online_store_url',
        'online_store_preview_url',
        'variant_id',
        'variant_price',
        'variant_compare_at',
        'variant_image',
    ];

    protected $casts = [
        'total_inventory' => 'integer',
        'price_min' => 'float',
        'price_max' => 'float',
        'variant_price' => 'float',
        'variant_compare_at' => 'float',
    ];

    protected $hidden = [
        'id',
        'product_type',
        'total_inventory',
        'price_min', 'price_max',
        'image_alt', 'image_variant',
        'online_store_url', 'online_store_preview_url',
        'variant_id', 'variant_price', 'variant_compare_at', 'variant_image',
        'created_at', 'updated_at',
    ];

    protected $appends = [
        'category',
        'total',
        'priceMin', 'priceMax',
        'imageAlt', 'imageVariant',
        'onlineStoreUrl', 'onlineStorePreviewUrl',
        'variant',
    ];

    // -------------------------------
    // Attribute accessors (snake → camel)
    // -------------------------------

    protected function category(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['product_type'] ?? null,
        );
    }

    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => isset($attributes['total_inventory'])
                ? (int)$attributes['total_inventory']
                : null,
        );
    }

    protected function priceMin(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => array_key_exists('price_min', $attributes)
                ? ($attributes['price_min'] === null ? null : (float)$attributes['price_min'])
                : null,
        );
    }

    protected function priceMax(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => array_key_exists('price_max', $attributes)
                ? ($attributes['price_max'] === null ? null : (float)$attributes['price_max'])
                : null,
        );
    }

    protected function imageAlt(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['image_alt'] ?? null,
        );
    }

    protected function imageVariant(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => ($attributes['image_variant'] ?? null)
                ?: ($attributes['variant_image'] ?? null),
        );
    }

    protected function onlineStoreUrl(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['online_store_url'] ?? null,
        );
    }

    protected function onlineStorePreviewUrl(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['online_store_preview_url'] ?? null,
        );
    }

    protected function variant(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $id = $attributes['variant_id'] ?? null;
                if (!$id) return null;

                return [
                    'id' => $id,
                    'price' => array_key_exists('variant_price', $attributes)
                        ? ($attributes['variant_price'] === null ? null : (float)$attributes['variant_price'])
                        : null,
                    'compareAt' => array_key_exists('variant_compare_at', $attributes)
                        ? ($attributes['variant_compare_at'] === null ? null : (float)$attributes['variant_compare_at'])
                        : null,
                    'currency' => $attributes['currency'] ?? null,
                    'image' => $attributes['variant_image'] ?? null,
                ];
            },
        );
    }

    // -------------------------------
    // Helper: API shape identical to "live"
    // -------------------------------

    /**
     * Prepare object for API (same format as live/Shopify):
     * - id = shopify_id
     * - camelCase
     */
    public function toApiProduct(): array
    {
        return [
            'id' => $this->getAttribute('shopify_id'),
            'title' => $this->getAttribute('title'),
            'description' => $this->getAttribute('description'),
            'category' => $this->category,
            'total' => $this->total,
            'priceMin' => $this->priceMin,
            'priceMax' => $this->priceMax,
            'currency' => $this->getAttribute('currency'),
            'imageVariant' => $this->imageVariant,
            'image' => $this->getAttribute('image'),
            'imageAlt' => $this->imageAlt,
            'variant' => $this->variant,
            'handle' => $this->getAttribute('handle'),
            'onlineStoreUrl' => $this->onlineStoreUrl,
            'onlineStorePreviewUrl' => $this->onlineStorePreviewUrl,
            'status' => $this->getAttribute('status'),
        ];
    }
}
