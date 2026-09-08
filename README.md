# 2Rad Wächter Köln

A static website that lists all bike- and eScooter-sharing providers in Cologne,
showing the total number of available vehicles as well as a breakdown per
provider. Built as plain HTML/CSS/JavaScript with a small PHP backend, ready
for deployment on a Plesk server.

🔗 Live: [2rad.waechter.koeln](https://2rad.waechter.koeln)

> **Status:** provider data is backed entirely by real **GBFS** (General
> Bikeshare Feed Specification) feeds — this app never shows made-up
> numbers. KVB Rad, Call a Bike, Dott, Voi and Bolt currently have live
> feeds for Cologne; Donkey Republic, Lime and TIER don't have a known
> public feed for Cologne yet and are shown as **"no live data available"**
> instead of a fabricated figure until one is found or provided.

## Features

- **Overview of all sharing providers** in Cologne (bikes & eScooters), with
  total vehicle counts and counts broken down per provider
- **Multi-language support**: German, English and Kölsch (Cologne dialect)
  - Automatic detection: German or English based on browser/OS language
  - Kölsch is only offered/auto-selected if the visitor is geolocated to
    Cologne (IP-based lookup); all three languages can always be picked
    manually
- **Cologne branding**: the characteristic Cologne red (`#ef0000`) is used
  throughout, along with a custom logo/favicon
- **Dark mode**: automatically follows the operating system/browser
  preference (`prefers-color-scheme`), with a manual toggle that overrides it;
  signals native dark-mode support to the browser and extensions like Dark
  Reader (`color-scheme` meta tag/CSS property)

## Tech stack

- Plain **HTML5**, **CSS3** (custom properties/variables, no framework) and
  vanilla **JavaScript** (no build step, no dependencies)
- **PHP** for the two small backend endpoints (see below)
- Based on the [HTML5 Boilerplate](https://html5boilerplate.com/) project
  skeleton

## Project structure

```
.
├── index.html            Main page
├── 404.html              Error page
├── css/style.css         Styles (custom properties, dark mode, layout)
├── js/app.js             Frontend logic: i18n, theming, data rendering
├── api/
│   ├── providers.php     Provider/vehicle-count endpoint (live GBFS only)
│   ├── geo.php           IP-based geolocation (Cologne detection)
│   ├── config/providers.php  Per-provider GBFS feed config
│   ├── lib/gbfs.php      Minimal GBFS client (discovery, station & free-floating counts)
│   ├── lib/cache.php     File-based cache so feeds aren't refetched on every request
│   └── cache/            Generated cache file (git-ignored)
├── icon.svg / icon.png / favicon.ico / apple-touch-icon.png
├── img/logo-512.png      Logo used for Open Graph / PWA icon
├── site.webmanifest      Web app manifest
└── robots.txt
```

## API endpoints

### `GET /api/providers.php`

Returns the list of sharing providers with their vehicle counts. Each
provider reports `available`: `false` if there's no working live feed for
it (in which case `bikes`/`escooters` are `null` — never a fabricated
number), `true` otherwise. `live` additionally distinguishes a fresh/cached
real reading (`true`) from... well, currently `live` and `available` are
the same, since there is no other source; `live` is kept for diagnostics
and possible future distinction (e.g. a reading so stale it's flagged
differently).

```json
{
  "updated": "2026-09-04T20:00:00+00:00",
  "providers": [
    {
      "id": "kvb-rad",
      "name": "KVB Rad",
      "types": ["bike"],
      "bikes": 1250,
      "escooters": 0,
      "color": "#004b93",
      "live": true,
      "available": true
    },
    {
      "id": "tier",
      "name": "TIER",
      "types": ["escooter"],
      "bikes": null,
      "escooters": null,
      "color": "#1b1b1b",
      "live": false,
      "available": false
    }
  ]
}
```

#### Live data sources (GBFS)

Vehicle counts are fetched from each provider's public
[GBFS](https://github.com/MobilityData/gbfs) feed, configured in
`api/config/providers.php`:

| Provider | Feed | Notes |
| --- | --- | --- |
| KVB Rad | `nextbike_kg` (nextbike) | Cologne-only, station-based |
| Call a Bike | `callabike` (mobidata-bw) | Nationwide feed, filtered to a Cologne bounding box |
| Voi | MOBIDROM Voi Köln (preferred, needs auth), or `voi_de` (mobidata-bw) fallback | Cologne-only via MOBIDROM; nationwide+bbox-filtered otherwise; see below |
| Bolt | MOBIDROM Bolt Köln-Bonn (needs auth) | Combined Köln+Bonn dataset, filtered to a Cologne bounding box |
| Dott | `cologne` (ridedott.com) | Cologne-only, free-floating |
| Donkey Republic, TIER | – | No known public feed for Cologne yet; reported as unavailable |
| Lime | – | MOBIDROM's `lime-nrw` dataset exists but only covers Dortmund/Essen, not Cologne; reported as unavailable |

Results are cached in `api/cache/gbfs_cache.json` for two minutes
(`CACHE_TTL_SECONDS` in `api/lib/cache.php`) to avoid hammering upstream
feeds, and the last known-good reading is kept and reused if a feed is
temporarily unreachable, so the site keeps showing real (if slightly
stale) numbers instead of showing "unavailable" during a brief outage.

**MOBIDROM (NRW mobility-data agency) for Voi & Bolt:** since September
2025, Voi and Bolt publish open GBFS data via the NRW mobility-data
platform [mobilitaetsdaten.nrw](https://www.mobilitaetsdaten.nrw) (register
as a data consumer via "Registrieren", then find the "Voi Köln"/"Bolt
Köln-Bonn" dataset under Sharing Mobility for the access details). Both
use the same OAuth2 client-credentials auth and the same client id/secret.
Set the following environment variables to use it (Voi falls back to its
nationwide feed, filtered to Cologne, no auth required, when
`MOBIDROM_GBFS_CLIENT_SECRET` isn't set; Bolt has no such fallback and is
reported as unavailable instead):

```bash
# Required — the client secret from your MOBIDROM dataset access page.
# Never commit this value; keep it only in the server's environment
# (e.g. Plesk's "Environment variables" panel), not in source control.
export MOBIDROM_GBFS_CLIENT_SECRET="..."

# Optional — defaults shown below match the current Voi Köln/Bolt Köln-Bonn datasets.
export MOBIDROM_GBFS_TOKEN_URL="https://www.mobilitaetsdaten.nrw/keycloak/realms/mobidrom/protocol/openid-connect/token"
export MOBIDROM_GBFS_CLIENT_ID="gbfs-api"
export MOBIDROM_VOI_GBFS_URL="https://www.mobilitaetsdaten.nrw/api/systemadapter-gbfs-provider/feed/v3.0/voi-koeln/source-voi-koeln/gbfs.json"
export MOBIDROM_BOLT_GBFS_URL="https://www.mobilitaetsdaten.nrw/api/systemadapter-gbfs-provider/feed/v3.0/bolt-koeln-bonn/source-bolt-koeln-bonn/gbfs.json"
```

Access tokens are cached in `api/cache/oauth_tokens.json` (file permissions
locked to `0600`) and reused until shortly before they expire, so the
Keycloak token endpoint isn't hit on every request.

**Setting `MOBIDROM_GBFS_CLIENT_SECRET` on Plesk:**

1. **Preferred (real PHP-FPM env variable):** *Websites & Domains* → your
   domain → **PHP Settings** → make sure "PHP support" is set to a
   "FPM application" handler → in the **"Additional configuration
   directives"** box (applies to the php-fpm pool), add:
   ```
   env[MOBIDROM_GBFS_CLIENT_SECRET] = OhQP9ryrXU7lnjQ9qteZib3rnys2wkTB
   ```
   Not every Plesk plan/subscription exposes this field to the domain
   owner (it may require reseller/admin access) — if it's missing, use
   the fallback below instead.
2. **Fallback (works on any plan, no server config access needed):** copy
   `api/config/secrets.local.php.example` to
   `api/config/secrets.local.php` (git-ignored, so it's never committed)
   and fill in the real secret there via `putenv(...)`. `providers.php`
   automatically loads it if present.

Either way, never commit the actual secret value to this repository.

### `GET /api/geo.php`

Resolves the visitor's IP to a city and reports whether it's Cologne, using
the free [ip-api.com](https://ip-api.com) service. Falls back to
`isCologne: false` for local/private IPs (e.g. during local development).

```json
{ "city": "Cologne", "countryCode": "DE", "isCologne": true }
```

## Local development

The frontend is fully static, so any static file server works for basic
layout/JS checks. To also exercise the PHP endpoints, use PHP's built-in
server from the project root:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000>.

## Deployment

Upload the project files as-is to any PHP-capable webspace (e.g. Plesk).
No build step, no dependencies to install (cURL and JSON PHP extensions are
required and enabled by default in most PHP setups). Make sure outbound
HTTP requests are allowed from the server to `ip-api.com` (geolocation) and
to the GBFS feed hosts listed above (live provider data); `api/cache/` must
be writable by the webserver so readings can be cached.
