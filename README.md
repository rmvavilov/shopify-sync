# Laravel 12 + Vue 3 (Vuetify) SPA — Shopify Products

A single-page app (SPA) built with **Laravel 12**, **Vue 3 + Vue Router + Pinia**, and **Vuetify**.  
Backend serves a JSON API and one Blade entry point; frontend handles all routing.  
Two product views are available:

- **Products Live** — talks directly to Shopify Admin GraphQL (proxy mode, cursor pagination).
- **Products Local** — reads products from your local DB (offset pagination). Local data is filled via a sync command.

---

## 1) Prerequisites

- PHP **8.4** and Composer
- Node.js **18+** (or 20+) and npm
- Database (MySQL/MariaDB/PostgreSQL)
- Shopify Private App token with Admin API access (Products scope)

> Linux/macOS recommended. On Windows, use WSL2 or a Dockerized stack.

---

## 2) Quick Start (Development)

```bash
# 1) Install PHP deps
composer install

# 2) Install JS deps
npm install

# 3) Copy env and set secrets
cp .env.example .env
php artisan key:generate
```

Edit `.env` (see the **Environment** section below), then:

```bash
# 4) Run migrations (adds Shopify fields to products table via a new migration)
php artisan migrate

# 5) Start dev servers (two terminals)
npm run dev
php artisan serve --host=localhost --port=8000
```

Open **http://localhost:8000**.  
Vite dev server should be reachable at **http://localhost:5173** (HMR).

---

## 3) Environment

Add the following to your `.env`:

```dotenv
# App
APP_NAME="Laravel Vue Shopify"
APP_ENV=local
APP_URL=http://localhost:8000

# Session (same-site domain; keep both app & Vite on same host in dev)
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Vite (dev server)
VITE_DEV_SERVER_URL=http://localhost:5173

# Shopify Admin API
SHOPIFY_DOMAIN=zevefchobs.myshopify.com
SHOPIFY_TOKEN=shpat_xxx           # Admin API access token
SHOPIFY_VERSION=2025-04           # API version (falls back to default in code)

# Products data source mode (used by a combined endpoint; pages also use dedicated endpoints)
SHOPIFY_MODE=proxy                # or 'local'
```

> **Dev tip:** Ensure your Laravel server and Vite dev server use the **same host** (both `localhost` or both `127.0.0.1`) to avoid CORS/cookie issues.  
> In `vite.config.js`, set `server.host` and `server.hmr.host` consistently.

---

## 4) Pages / Tabs

- **Products Live**  
  Route: `/products-live` → calls `GET /api/shopify/products/proxy`  
  Server-side sorting for keys: `title | category | created_at | updated_at | id`  
  Cursor pagination with `first/after` & `last/before`.

- **Products Local**  
  Route: `/products-local` → calls `GET /api/shopify/products/local`  
  Classic offset pagination using DB and the same sortable keys as above.

Both pages render via a shared **`<ProductTable/>`** component (Vuetify `v-data-table-server`) and share formatting helpers.

---

## 5) Sync Command (fills Local DB)

The command fetches products from Shopify in pages via GraphQL and upserts them into the local DB using snake_case columns; accessors expose camelCase in API.

### Usage

```bash
# Basic full sync, newest first, batches of 100
php artisan shopify:sync --first=100

# Delta sync: only products updated since a date
php artisan shopify:sync --since=2025-07-01 --first=100

# Filter by status (ACTIVE | DRAFT | ARCHIVED)
php artisan shopify:sync --status=ACTIVE --first=100

# Resume from a cursor string (printed at the end of previous run)
php artisan shopify:sync --after=eyJsYXN0X2lkIjoiZ2lkOi8v...

# Stop after syncing N products
php artisan shopify:sync --max=1000

# Dry run (no DB writes)
php artisan shopify:sync --dry
```

### What it does

- Calls Shopify Admin GraphQL for products (title, descriptionHtml, productType, handle, status, totalInventory, priceRangeV2, media/featuredMedia, first variant data, onlineStore URLs).
- For each node, `SyncService::upsertFromShopifyNode()` writes to `products` table fields:
    - `shopify_id, handle, status, title, description, product_type, total_inventory`
    - `price_min, price_max, currency`
    - `image, image_alt, image_variant`
    - `online_store_url, online_store_preview_url`
    - `variant_id, variant_price, variant_compare_at, variant_image`

> The `Product` model exposes camelCase via accessors (e.g., `priceMin`, `priceMax`, `category`, `variant`, etc.) and provides `toApiProduct()` for a response identical to “live”.

---

## 6) Production Build

```bash
# Build assets
npm run build

# Optimize app
php artisan optimize

# Serve your app via a web server pointing to public/
# (e.g., Nginx + PHP-FPM). Ensure APP_URL is correct and HTTPS is configured.
```

---

## 7) Troubleshooting

- **419 / CSRF on logout or POST**: ensure you include cookies (`credentials: 'same-origin'`) and either post via Laravel routes that use `web` middleware or send the `X-CSRF-TOKEN` header extracted from `<meta name="csrf-token">`.
- **CORS or HMR blocked**: use the same host (both `localhost` or both `127.0.0.1`); set `VITE_DEV_SERVER_URL` and Vite `server.hmr.host` accordingly.
- **Images/links missing**: password-protected storefronts may return `onlineStoreUrl = null` — use `onlineStorePreviewUrl` or build a fallback URL from the product handle.

---

## 8) API Overview (for reference)

- `GET /api/shopify/products/proxy` — Shopify Admin GraphQL proxy  
  Params: `page, itemsPerPage, sortBy[0][key|order], q, after, before, direction`

- `GET /api/shopify/products/local` — Local DB listing  
  Params: `page, itemsPerPage, sortBy[0][key|order], q`

Both return:
```json
// proxy
{ "mode":"proxy", "items":[...], "pageInfo":{...}, "itemsPerPage":10 }

// local
{ "mode":"local", "items":[...], "total":123, "page":1, "itemsPerPage":10 }
```
