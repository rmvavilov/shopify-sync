<?php

namespace App\Http\Controllers;

use App\Shopify\Services\ProductsService as ShopifyProductsService;
use Illuminate\Http\Request;

class ShopifyController extends Controller
{
    public function products(Request $request, ShopifyProductsService $service)
    {
        $mode = config('services.shopify.mode', 'proxy');

        return response()->json(
            $mode === 'local'
                ? $service->productsLocal($request)
                : $service->productsProxy($request)
        );
    }

    public function productsProxy(Request $request, ShopifyProductsService $service)
    {
        return response()->json($service->productsProxy($request));
    }

    public function productsLocal(Request $request, ShopifyProductsService $service)
    {
        return response()->json($service->productsLocal($request));
    }
}
