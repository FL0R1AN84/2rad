# 2Rad Wächter Köln

A static website that lists all bike- and eScooter-sharing providers in Cologne,
showing the total number of available vehicles as well as a breakdown per
provider. Built as plain HTML/CSS/JavaScript with a small PHP backend, ready
for deployment on a Plesk server.

🔗 Live: [2rad.waechter.koeln](https://2rad.waechter.koeln)

> **Status:** the provider data is currently **dummy data** for demonstration
> purposes. It is meant to be replaced with real feeds (e.g. GBFS) per
> provider later on.

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
│   ├── providers.php     Dummy JSON endpoint with provider/vehicle data
│   └── geo.php           IP-based geolocation (Cologne detection)
├── icon.svg / icon.png / favicon.ico / apple-touch-icon.png
├── img/logo-512.png      Logo used for Open Graph / PWA icon
├── site.webmanifest      Web app manifest
└── robots.txt
```

## API endpoints

### `GET /api/providers.php`

Returns the list of sharing providers with their vehicle counts.

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
      "color": "#004b93"
    }
  ]
}
```

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
No build step, no dependencies to install. Make sure outbound HTTP requests
to `ip-api.com` are allowed from the server for the geolocation feature to
work.
