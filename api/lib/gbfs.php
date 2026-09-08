<?php
/**
 * Small GBFS (General Bikeshare Feed Specification) client.
 *
 * Only implements the bits needed to turn a provider's GBFS auto-discovery
 * feed into a simple ['bikes' => int, 'escooters' => int] count:
 *  - station-based systems (station_information.json + station_status.json)
 *  - free-floating systems (free_bike_status.json, optionally vehicle_types.json)
 *
 * No external dependencies; uses cURL directly so it runs on plain PHP-FPM/
 * Plesk setups without Composer.
 */

/**
 * Fetch and JSON-decode a URL. Returns null on any failure (network error,
 * non-2xx status, invalid JSON) so callers can fall back gracefully.
 */
function gbfs_fetch_json(string $url, int $timeoutSeconds = 8): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => '2rad-waechter-koeln/1.0 (+https://2rad.waechter.koeln)',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    // curl_close() is a no-op since PHP 8.0 (handles are closed automatically)
    // and deprecated since PHP 8.5, so it's intentionally omitted here.

    if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Resolve a feed name (e.g. "station_status", "free_bike_status",
 * "vehicle_types") from a GBFS auto-discovery document (gbfs.json) to its
 * actual URL. Picks the first available language.
 */
function gbfs_resolve_feed_url(array $discovery, string $feedName): ?string
{
    $data = $discovery['data'] ?? null;
    if (!is_array($data) || empty($data)) {
        return null;
    }

    // GBFS 1.x/2.x: data is keyed by language ("en" => ["feeds" => [...]]).
    // GBFS 3.x: data is the feeds list directly ("feeds" => [...]).
    $languageBlocks = isset($data['feeds']) ? [$data] : array_values($data);

    foreach ($languageBlocks as $block) {
        $feeds = $block['feeds'] ?? [];
        foreach ($feeds as $feed) {
            if (($feed['name'] ?? '') === $feedName && !empty($feed['url'])) {
                return $feed['url'];
            }
        }
    }

    return null;
}

/**
 * Whether a lat/lon pair falls inside a bounding box
 * ['minLat' => .., 'maxLat' => .., 'minLon' => .., 'maxLon' => ..].
 * Returns true when no bbox is given (i.e. "no filtering").
 */
function gbfs_in_bbox(?array $bbox, $lat, $lon): bool
{
    if ($bbox === null) {
        return true;
    }
    if (!is_numeric($lat) || !is_numeric($lon)) {
        return false;
    }

    return $lat >= $bbox['minLat'] && $lat <= $bbox['maxLat']
        && $lon >= $bbox['minLon'] && $lon <= $bbox['maxLon'];
}

/**
 * Classifies a GBFS "form_factor" value into 'bike', 'escooter' or 'other'.
 * GBFS 3.x introduced more specific values (e.g. "scooter_standing",
 * "scooter_seated", "cargo_bicycle") on top of the older "bicycle"/
 * "scooter"/"moped", so this matches by prefix instead of an exact list.
 */
function gbfs_classify_form_factor(?string $formFactor): string
{
    if ($formFactor === null) {
        return 'other';
    }
    if (str_starts_with($formFactor, 'bicycle')) {
        return 'bike';
    }
    if (str_starts_with($formFactor, 'scooter') || $formFactor === 'moped') {
        return 'escooter';
    }
    return 'other';
}

/**
 * Sum available vehicles across a station-based GBFS system.
 * Returns null if the feed could not be read.
 */
function gbfs_count_stations(string $discoveryUrl, ?array $bbox = null): ?int
{
    $discovery = gbfs_fetch_json($discoveryUrl);
    if ($discovery === null) {
        return null;
    }

    $statusUrl = gbfs_resolve_feed_url($discovery, 'station_status');
    if ($statusUrl === null) {
        return null;
    }
    $status = gbfs_fetch_json($statusUrl);
    $stations = $status['data']['stations'] ?? null;
    if (!is_array($stations)) {
        return null;
    }

    // Only needed for bbox-filtering (nationwide station networks); most
    // Cologne-only systems can skip this extra request.
    $positions = [];
    if ($bbox !== null) {
        $infoUrl = gbfs_resolve_feed_url($discovery, 'station_information');
        $info = $infoUrl !== null ? gbfs_fetch_json($infoUrl) : null;
        foreach (($info['data']['stations'] ?? []) as $station) {
            if (isset($station['station_id'])) {
                $positions[$station['station_id']] = [$station['lat'] ?? null, $station['lon'] ?? null];
            }
        }
    }

    $count = 0;
    foreach ($stations as $station) {
        if ($bbox !== null) {
            [$lat, $lon] = $positions[$station['station_id'] ?? null] ?? [null, null];
            if (!gbfs_in_bbox($bbox, $lat, $lon)) {
                continue;
            }
        }
        // GBFS 1.x/2.x uses num_bikes_available, GBFS 3.x uses num_vehicles_available.
        $count += (int) ($station['num_vehicles_available'] ?? $station['num_bikes_available'] ?? 0);
    }

    return $count;
}

/**
 * Count available free-floating vehicles (bikes vs. escooters) for a
 * GBFS system, optionally restricted to a bounding box (for nationwide
 * feeds such as Call a Bike or Voi that need filtering down to Cologne).
 *
 * $defaultFormFactor is used when the feed has no vehicle_types.json
 * (older GBFS versions) to classify all vehicles as bike/escooter.
 *
 * Returns ['bikes' => int, 'escooters' => int] or null on failure.
 */
function gbfs_count_free_floating(string $discoveryUrl, ?array $bbox, string $defaultFormFactor): ?array
{
    $discovery = gbfs_fetch_json($discoveryUrl);
    if ($discovery === null) {
        return null;
    }

    // GBFS 3.x renamed "free_bike_status" to "vehicle_status".
    $vehiclesUrl = gbfs_resolve_feed_url($discovery, 'free_bike_status')
        ?? gbfs_resolve_feed_url($discovery, 'vehicle_status');
    if ($vehiclesUrl === null) {
        return null;
    }
    $vehicleData = gbfs_fetch_json($vehiclesUrl);
    // GBFS 1.x/2.x: data.bikes, GBFS 3.x: data.vehicles.
    $vehicles = $vehicleData['data']['vehicles'] ?? $vehicleData['data']['bikes'] ?? null;
    if (!is_array($vehicles)) {
        return null;
    }

    // Map vehicle_type_id => form_factor ("bicycle", "scooter", ...), when available.
    $formFactorByTypeId = [];
    $typesUrl = gbfs_resolve_feed_url($discovery, 'vehicle_types');
    if ($typesUrl !== null) {
        $types = gbfs_fetch_json($typesUrl);
        foreach (($types['data']['vehicle_types'] ?? []) as $type) {
            if (isset($type['vehicle_type_id'])) {
                $formFactorByTypeId[$type['vehicle_type_id']] = $type['form_factor'] ?? null;
            }
        }
    }

    $bikes = 0;
    $escooters = 0;
    foreach ($vehicles as $vehicle) {
        if (!empty($vehicle['is_disabled']) || !empty($vehicle['is_reserved'])) {
            continue;
        }
        if (!gbfs_in_bbox($bbox, $vehicle['lat'] ?? null, $vehicle['lon'] ?? null)) {
            continue;
        }

        $formFactor = $formFactorByTypeId[$vehicle['vehicle_type_id'] ?? null] ?? $defaultFormFactor;
        switch (gbfs_classify_form_factor($formFactor)) {
            case 'bike':
                $bikes++;
                break;
            case 'escooter':
                $escooters++;
                break;
            // Other form factors (car, other, ...) aren't tracked by this site.
        }
    }

    return ['bikes' => $bikes, 'escooters' => $escooters];
}
