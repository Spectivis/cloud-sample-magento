# Spectivis Cloud sample: Magento

A Mage-OS 4.0 store (the community distribution of Magento Open Source 2.4) with a generated catalog of 50,000 SKUs, for testing Spectivis Cloud with a store the size of a real one: build time, first install, page speed, search, cron, cache and bot traffic.

| Part | What it is |
|---|---|
| Store | Mage-OS 4.0 on PHP 8.4, dressed as Larkspur, a made-up clothing and goods shop. The theme is `Spectivis/demo`, a child of the free [Hyvä](https://hyva.io) theme, with Hyvä's Luma checkout fallback. No `repo.magento.com` keys needed. |
| Catalog | `Spectivis_DemoCatalog` in `app/code`: 344 categories in three levels, six filterable attributes (brand, colour, size, material, pattern, season), simple products and configurable products with colour and size children, special prices, stock, related products and 144 shared product photos (three per subcategory). Brand names are invented. |
| Look | `Spectivis_DemoStore` in `app/code` selects the theme and sets the store name, titles and footer line; the home page shows a banner, the departments and two product sliders. |
| Load tests | k6 scripts in `loadtest/`: a shopper who buys, a crawler that walks filter combinations, and a page timer. |

The store works with no mail server: two-factor auth is off, mail is disabled and admin passwords do not expire.

## Deploy it on Spectivis Cloud

1. New app from this repository, framework **Magento**. Size 4 GB (2 GB is the minimum). Cloud adds OpenSearch 2 GB; add Valkey 512 MB as well (see Measurements for why).
2. Add a MySQL database.
3. Set `DEMO_CATALOG_GENERATE=1` to fill the store. Deploy.

The first release installs Magento on the empty database. Then the cron process (`bin/magento cron:run`, every minute) runs the generator: each run writes products for up to four minutes and the run that writes the last batch reindexes everything. Follow it in the logs (`demo catalog: products: 12000 of 50000 SKUs ...`). When it says `done`, the store is complete; later runs do nothing. A restart in the middle carries on where it stopped.

The admin is at the address in `MAGENTO_ADMIN_PATH`, with `MAGENTO_ADMIN_USER` and `MAGENTO_ADMIN_PASSWORD` from the environment's variables.

| Variable | Default | |
|---|---|---|
| `DEMO_CATALOG_GENERATE` | `0` | `1` lets cron generate the catalog. |
| `DEMO_CATALOG_SKUS` | `50000` | SKUs, configurable children included. Raise it later and cron adds the rest. |
| `DEMO_CATALOG_SEED` | `1` | The same seed gives the same catalog. It cannot change once products exist. |
| `DEMO_CATALOG_BATCH` | `2000` | SKUs per database transaction. |
| `DEMO_CATALOG_RUN_SECONDS` | `240` | How long one cron run writes before leaving the rest to the next. |
| `DEMO_CATALOG_INDEXER_MODE` | `schedule` | Indexer mode once the catalog is indexed: `schedule`, `realtime` or `keep`. |

### What the repository holds

Only this project's files: `composer.json`, `composer.lock`, `app/etc/config.php`, `app/code`, `app/design` and an empty `var/composer_home`. `composer install` puts the rest of Mage-OS in place. The build compiles code and static files with no database, which is why `app/etc/config.php` lists the modules, scopes and themes (`bin/magento app:config:dump scopes themes i18n`). `app/etc/env.php` is not committed; the platform writes it at start from the environment's variables.

## How the catalog is generated

Products are written with batched SQL, one transaction per batch, as Magento's importer and performance fixtures do internally: attribute values, website, category, legacy stock and the default inventory source, the image, the URL rewrite, configurable options and links. Attributes and categories go through Magento's own APIs, since there are only a few hundred.

Magento's own tools were the first choice and were set aside:

- `setup:performance:generate-fixtures` writes everything in one long process with no way to resume, so it fits neither the 15 minute release limit nor a cron job. It names products "Simple Product 123", which makes search tests meaningless.
- The import model (`Magento\ImportExport`) copies an image file for every product (sharing works only within one batch), and every product that points at an existing image logs a stack trace. On the platform `var/log` is a 64 MB scratch folder, so 50,000 products would fill it.

Every product is worked out from the seed and its family number alone, so a batch can be written again in any order with the same result, and a run resumes from the highest SKU in the database. SKUs are `DC-000123` (a simple or configurable product) and `DC-000123-NAVY-M` (a child). Generated products can be edited and deleted in the admin like any other. The images are shared: removing an image from one product in the admin removes it from every product using it.

Each product gets the photo of its subcategory and position, so a sneaker shows a sneaker. A store generated before the photos (each department had a few placeholder pictures) is moved over by the next cron run: it rewrites the image attributes and gallery rows of every `DC-` product in batches and deletes the old files. The flag `spectivis_demo_catalog` records that it is done.

## Theme

`app/design/frontend/Spectivis/demo` is a Hyvä child theme. Hyvä is free and open source (OSL-3.0) and installed from its public GitHub repositories, listed in `composer.json`; the Mollie bundle it suggests is replaced with nothing. Its CSS is built with Tailwind and committed as `web/css/styles.css`, because the platform build has no Node. After changing a template's classes or `web/tailwind`, rebuild it:

```
cd app/design/frontend/Spectivis/demo/web/tailwind
npm ci
npm run build
```

Then remove `pub/static/frontend` and flush the cache to see it locally.

Photos are from [Pixabay](https://pixabay.com) under the Pixabay Content License; each file and its source are listed in `app/code/Spectivis/DemoCatalog/images/CREDITS.md` and `app/design/frontend/Spectivis/demo/web/images/CREDITS.md`.

## Run it locally

You need Docker with about 4 GB of memory free.

```
composer install --ignore-platform-reqs   # or let docker/install.sh do it
docker compose up -d --build
docker compose exec app sh docker/install.sh
docker compose exec app php bin/magento demo:catalog:generate
```

The store is on http://localhost:8080 and the admin on http://localhost:8080/admin (`admin` / `Admin12345demo`). `demo:catalog:generate --skus=5000` makes a smaller catalog; `demo:catalog:status` shows how far it has got. The `cron` service runs `bin/magento cron:run` every minute. Locally the store runs in default mode with the code mounted, so the first pages are slow while Magento compiles them.

## Screenshots

`scripts/screenshots.mjs` captures the home, category and product pages at desktop (1440×900) and mobile (390×844) sizes into `.screenshots/<label>/`. It needs the generated catalog with the default seed, and Playwright:

```
npm install --no-save playwright && npx playwright install chromium
BASE_URL=http://localhost:8080 node scripts/screenshots.mjs after            # all pages
BASE_URL=https://store.example.com node scripts/screenshots.mjs before home   # one page
```

## Load tests

The scripts need [k6](https://k6.io). They send real traffic and place real orders: point them only at a store you run, such as your own test deployment.

```
k6 run -e BASE_URL=https://store.example.com loadtest/shopper.js
k6 run -e BASE_URL=https://store.example.com loadtest/crawler.js
k6 run -e BASE_URL=https://store.example.com loadtest/pages.js
```

| Script | What it does | Options |
|---|---|---|
| `shopper.js` | Home, a category and another page of it, a layered navigation filter, a search, a product, add to cart, and a guest checkout paid by check / money order. | `VUS` (2), `DURATION` (2m), `THINK` seconds between pages (1) |
| `crawler.js` | Category URLs with random filter, sort, page and limit combinations, no cookies and no pauses, like the bots that use up a store's PHP workers. Most URLs miss the page cache. | `VUS` (4), `DURATION` (2m) |
| `pages.js` | Each of 100 URLs once, 20 per page type, and the median and 95th percentile time per type. Flush the cache first (admin, System, Cache Management) for cold numbers, then run it again for warm ones. | `VUS` (2), `PER_TYPE` (20) |

With Docker instead of a local k6: `docker run --rm -i --network host -v "$PWD/loadtest:/scripts" grafana/k6 run -e BASE_URL=... /scripts/shopper.js`.

## Measurements

Measured with the Spectivis Cloud daemon's own build and run code (Magento preset, read-only image, 64 MB scratch folders) on one 4-core, 12 GB test machine running the app, MySQL 8.4, OpenSearch 2 and Valkey 8 side by side. Not measured on the hosted platform; treat them as a baseline.

| | |
|---|---|
| Build, no cache | 3 min 17 s (Composer install 57 s, PHP extensions 61 s, compile and static files 39 s) |
| Build after a code change | about 70 s |
| Image | 1.45 GB, 415 MB compressed |
| First release, install on an empty database | 23 s |
| Later release, nothing to upgrade | 3 s |
| Generating 50,000 SKUs from cron | 2 min 58 s: products 58 s (about 1,000 SKUs a second), reindex 2 min |
| Catalog | 32,524 products in the grid: 30,788 simple, 1,736 configurable with 17,476 children; 346 categories |
| Database | 175 MB |
| `pub/media` | 0.6 MB, 1.2 MB once the resized images are cached (measured with the earlier placeholder images; the photos add about 14 MB) |
| OpenSearch index | 30,699 documents, 38 MB |
| Memory | app 285 MB idle, 435 MB under the crawler; OpenSearch 1.6 GB (1 GB heap); MySQL 1.4 to 1.8 GB (1 GB buffer pool) |

Page times from `pages.js` with 2 virtual users, 20 URLs per type, cache in Valkey. Cold is right after a cache flush (first visit to each page), warm is the same URLs again.

| Page | Cold median | Cold p95 | Warm median | Warm p95 |
|---|---|---|---|---|
| Home (one URL) | 1.19 s | | 14 ms | 19 ms |
| Category | 128 ms | 173 ms | 14 ms | 16 ms |
| Category, page 2 | 121 ms | 174 ms | 14 ms | 15 ms |
| Filtered category | 112 ms | 153 ms | 14 ms | 14 ms |
| Search | 160 ms | 202 ms | 14 ms | 15 ms |
| Product | 124 ms | 211 ms | 14 ms | 17 ms |

`crawler.js` with 4 virtual users for 90 s: 33 requests a second, median 105 ms, p95 192 ms, no errors; almost every URL is new, so this is the uncached speed. `shopper.js` with 2 virtual users for 90 s: 19 orders, no errors.

Without Valkey the caches go to `var/cache` and `var/page_cache`, 64 MB each on the platform. The crawler filled both in about a minute. Nothing fails after that: Magento stops caching new pages without logging anything, so every page not cached yet costs about 100 ms for good. With Valkey (512 MB, least recently used keys evicted) the same crawl used 416 MB.

### Platform workarounds in this repository

- `var/composer_home` (with its `cache` folder) is committed. Magento reads its own version through Composer, which makes that folder if it is missing; on a read-only image the admin dashboard failed with `mkdir(): Read-only file system`.
- `Spectivis_CacheBackendName` maps the cache backend class `Magento\Framework\Cache\Backend\Redis` to `redis`. Mage-OS 4 knows backends only by short name and uses the file cache for any other, so the Valkey settings Spectivis Cloud writes to `env.php` were ignored.

Both can go once the Magento preset writes `'backend' => 'redis'` and makes `var/composer_home` writable or ships it.

## Licence

Mage-OS is licensed under OSL-3.0 and AFL-3.0 (`LICENSE.txt`, `LICENSE_AFL.txt`). Hyvä is licensed under OSL-3.0. The `Spectivis_DemoCatalog`, `Spectivis_DemoStore` and `Spectivis_CacheBackendName` modules, the `Spectivis/demo` theme's own files, the load tests and the Docker files in this repository are released under the same two licences.
