# Laravel 12 + Vue 3 (Vuetify) SPA — Shopify Products

A single-page app (SPA) built with **Laravel 12**, **Vue 3 + Vue Router + Pinia**, and **Vuetify**.  
Backend serves a JSON API and one Blade entry point; frontend handles all routing.  
Two product views are available and a details drawer supports view/edit/sync actions.

- **Dashboard** — includes a one-click “Sync all” to trigger `shopify:sync` via a queued job.
- **Products Live** — talks directly to Shopify Admin GraphQL (proxy mode, cursor pagination).
- **Products Local** — reads products from your local DB (offset pagination). Local data is filled via a sync command.
- **Details Drawer** — per-product details for both live and local (compare dates, quick actions).

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
# 4) Run migrations
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

# Session (keep both app & Vite on same host in dev)
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Vite (dev server)
VITE_DEV_SERVER_URL=http://localhost:5173

# Shopify Admin API
SHOPIFY_DOMAIN=zevefchobs.myshopify.com
SHOPIFY_TOKEN=shpat_xxx           # Admin API access token
SHOPIFY_VERSION=2025-04           # API version (fallback handled in code)

# Products data source mode
SHOPIFY_MODE=proxy                # or 'local'

# Queues
QUEUE_CONNECTION=database
```

> **Dev tip:** Ensure your Laravel server and Vite dev server use the **same host** (both `localhost` or both `127.0.0.1`) to avoid CORS/cookie issues.  
> In `vite.config.js`, set `server.host` and `server.hmr.host` consistently.

---

## 4) Pages / Tabs

- **Dashboard**  
  Has a **Sync all** button that triggers a queued `shopify:sync` via API.

- **Products Live**  
  Route: `/products-live` → calls `GET /api/shopify/products/proxy`  
  Server-side sorting for keys: `title | category | created_at | updated_at | id`  
  Cursor pagination with `first/after` & `last/before`.

- **Products Local**  
  Route: `/products-local` → calls `GET /api/shopify/products/local`  
  Classic offset pagination using DB and the same sortable keys as above.

- **Product Details (Drawer)**  
  Routes (use base64url-encoded GID in `:id`):
    - `GET /products-live/:id` → fetches `GET /api/shopify/products/proxy/:id`
    - `GET /products-local/:id` → fetches `GET /api/shopify/products/local/:id`  
      Shows media, description, price range, inventory, public links, and two dates:
    - **Shop Updated** = Shopify `updatedAt`
    - **Synced (Local)** = local `last_synced_at` (for local view)  
      Drawer exposes actions (see below).

---

## 5) Sync (Batch) — Command

The command fetches products from Shopify and upserts them to the local DB with **last-write-wins** by Shopify `updatedAt`.

### Usage

```bash
# Full sync, batches of 100
php artisan shopify:sync --first=100

# Delta: only products updated since a date
php artisan shopify:sync --since=2025-07-01 --first=100

# Filter by status (ACTIVE | DRAFT | ARCHIVED)
php artisan shopify:sync --status=ACTIVE --first=100

# Resume from a cursor
php artisan shopify:sync --after=eyJsYXN0X2lkIjoiZ2lkOi8v...

# Stop after N products
php artisan shopify:sync --max=1000

# Dry run (no DB writes)
php artisan shopify:sync --dry
```

Writes/upserts (snake_case columns):
- `shopify_id, handle, status, title, description, product_type, total_inventory`
- `price_min, price_max, currency`
- `image, image_alt, image_variant`
- `online_store_url, online_store_preview_url`
- `variant_id, variant_price, variant_compare_at, variant_image`
- **Sync metadata:** `remote_updated_at` (Shopify `updatedAt`), `last_synced_at`, `last_sync_source`, `last_webhook_id`

The `Product` model exposes camelCase via accessors (e.g., `priceMin`, `priceMax`, `category`, `variant`, `updatedAt`, `lastSyncedAt`) and provides `toApiProduct()` consistent with the live shape.

---

## 6) Sync (Single Product)

- **POST `/api/shopify/products/sync/:id`** — pulls one product by GID from Shopify and updates the local record (`last_sync_source='sync'`), then returns the unified product shape.

---

## 7) Live Edit/Delete (Shopify)

- **POST `/api/shopify/products/live/:id/update`** — updates a product using Shopify `productUpdate`.  
  Accepts editable fields like `title`, `handle`, `status`, `productType`, `descriptionHtml` and optional `expectedUpdatedAt` to detect version conflicts (optimistic concurrency). On success, local DB is refreshed immediately.

- **DELETE `/api/shopify/products/live/:id`** — deletes a product using Shopify `productDelete`.  
  Locally marks the product as `ARCHIVED` (or soft-deletes if you use it).

- Both endpoints use the **shared GraphQL fragment** (`ProductFragments::fields()`) so UI and sync see the same data shape.

---

## 8) Dashboard — “Sync all” (Queued)

- **POST `/api/shopify/sync-all`** — enqueues a job that runs `php artisan shopify:sync` with provided params.  
  Job: `App\Jobs\Shopify\RunSyncJob`

### Enable and run the queue worker

```bash
# one time:
php artisan queue:table
php artisan migrate

# .env
QUEUE_CONNECTION=database

# run the worker (dev):
php artisan queue:work --tries=3
```

> The Dashboard button triggers the API which schedules the job. The HTTP request returns immediately with a `202 started` response.

---

## 9) API Overview (updated)

- `GET /api/shopify/products/proxy` — Shopify Admin GraphQL proxy  
  Params: `page, itemsPerPage, sortBy[0][key|order], q, after, before, direction`

- `GET /api/shopify/products/local` — Local DB listing  
  Params: `page, itemsPerPage, sortBy[0][key|order], q`

- `GET /api/shopify/products/proxy/:id` — Live details by GID (base64url)
- `GET /api/shopify/products/local/:id` — Local details by GID (base64url)

- `POST /api/shopify/products/sync/:id` — Sync one product to local
- `POST /api/shopify/products/live/:id/update` — Live update (Shopify)
- `DELETE /api/shopify/products/live/:id` — Live delete (Shopify)
- `POST /api/shopify/sync-all` — Start full sync via queued job

All endpoints require `auth:web`. Frontend fetches must include cookies:
```js
fetch(url, { credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token } })
```

---

## 10) Production Build

```bash
# Build assets
npm run build

# Optimize app
php artisan optimize

# Serve your app via a web server pointing to public/
# Ensure APP_URL is correct and HTTPS is configured.
```

---

## 12) Troubleshooting

- **419 / CSRF on POST**: include cookies (`credentials: 'same-origin'`) and send the `X-CSRF-TOKEN` header from `<meta name="csrf-token">`.
- **CORS or HMR blocked**: use the same host (both `localhost` or both `127.0.0.1`); set `VITE_DEV_SERVER_URL` and Vite `server.hmr.host` consistently.
- **`onlineStoreUrl` / `onlineStorePreviewUrl`**: preview may be `null` for password-protected storefronts; use preview URL or build fallback from product handle.
- **Duplicate key on sync**: handled in `SyncService` (soft-deleted restore + race retry).
