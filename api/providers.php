<?php
/**
 * JSON endpoint for bike/eScooter sharing providers in Cologne.
 *
 * Live vehicle counts are fetched from each provider's public GBFS feed
 * (see api/config/providers.php) where available. Providers without a
 * known public feed keep using dummy data (documented per-provider).
 *
 * Resolution order per provider, so the site stays usable even if a feed
 * is slow or temporarily down:
 *   1. Fresh cached reading (within CACHE_TTL_SECONDS)
 *   2. Live GBFS fetch (cached afterwards)
 *   3. Stale cached reading (last known-good, any age)
 *   4. Static fallback numbers
 *
 * Response format is unchanged so the frontend keeps working unchanged.
 * An additional "live" flag per provider tells the frontend whether the
 * figures are a real (or last known-good) reading vs. pure dummy data.
 */

require __DIR__ . '/lib/gbfs.php';
require __DIR__ . '/lib/cache.php';
require __DIR__ . '/config/providers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/**
 * Fetches live vehicle counts for a single provider based on its
 * configured GBFS source. Returns null if the source is 'static' or the
 * fetch failed.
 */
function fetch_live_counts(array $source): ?array
{
    switch ($source['type']) {
        case 'gbfs-stations':
            $count = gbfs_count_stations($source['discovery_url'], $source['bbox'] ?? null);
            return $count === null ? null : ['bikes' => $count, 'escooters' => 0];

        case 'gbfs-free-floating':
            return gbfs_count_free_floating(
                $source['discovery_url'],
                $source['bbox'] ?? null,
                $source['default_form_factor'] ?? 'bicycle'
            );

        default:
            return null;
    }
}

$cache = cache_read_all();
$cacheChanged = false;
$providers = [];

foreach (get_provider_config() as $config) {
    $counts = null;
    $isLive = false;

    if ($config['source']['type'] !== 'static') {
        $fresh = cache_get_fresh($cache, $config['id']);
        if ($fresh !== null) {
            $counts = ['bikes' => $fresh['bikes'], 'escooters' => $fresh['escooters']];
            $isLive = true;
        } else {
            $live = fetch_live_counts($config['source']);
            if ($live !== null) {
                $counts = $live;
                $isLive = true;
                $cache[$config['id']] = $live + ['fetched_at' => time()];
                $cacheChanged = true;
            } else {
                $stale = cache_get_stale($cache, $config['id']);
                if ($stale !== null) {
                    $counts = ['bikes' => $stale['bikes'], 'escooters' => $stale['escooters']];
                    $isLive = true;
                }
            }
        }
    }

    if ($counts === null) {
        $counts = $config['fallback'];
    }

    $providers[] = [
        'id' => $config['id'],
        'name' => $config['name'],
        'types' => $config['types'],
        'bikes' => $counts['bikes'],
        'escooters' => $counts['escooters'],
        'color' => $config['color'],
        'live' => $isLive,
    ];
}

if ($cacheChanged) {
    cache_write_all($cache);
}

echo json_encode([
    'updated' => date('c'),
    'providers' => $providers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
