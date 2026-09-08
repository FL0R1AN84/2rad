<?php
/**
 * Provider configuration: which GBFS feed (if any) backs each provider,
 * plus the dummy fallback numbers used when no live feed is configured or
 * a live fetch fails.
 *
 * source.type:
 *   - 'gbfs-stations'      station-based system (station_status.json)
 *   - 'gbfs-free-floating' free-floating system (free_bike_status.json)
 *   - 'static'             no known public feed yet; dummy numbers only
 *
 * source.bbox: restricts counting to Cologne for nationwide feeds
 * (e.g. Call a Bike, Voi). Left out for feeds that are already
 * Cologne-only (KVB Rad, Dott Cologne).
 */

// Rough bounding box around Cologne, used to filter nationwide GBFS feeds.
const COLOGNE_BBOX = [
    'minLat' => 50.83,
    'maxLat' => 51.08,
    'minLon' => 6.77,
    'maxLon' => 7.16,
];

/**
 * Voi has started publishing open GBFS data for Cologne via the NRW
 * mobility-data agency MOBIDROM (mobidrom.nrw), in addition to its
 * nationwide feed. Since the exact public MOBIDROM endpoint isn't
 * documented yet, it can be configured via environment variable once
 * available (e.g. through registration as a data consumer); otherwise the
 * code falls back to Voi's nationwide feed filtered down to Cologne.
 */
function voi_gbfs_discovery_url(): string
{
    $mobidromUrl = getenv('MOBIDROM_VOI_GBFS_URL');
    return $mobidromUrl !== false && $mobidromUrl !== ''
        ? $mobidromUrl
        : 'https://api.mobidata-bw.de/sharing/gbfs/v3/voi_de/gbfs';
}

function get_provider_config(): array
{
    return [
        [
            'id' => 'kvb-rad',
            'name' => 'KVB Rad',
            'types' => ['bike'],
            'color' => '#004b93',
            'source' => [
                'type' => 'gbfs-stations',
                'discovery_url' => 'https://gbfs.nextbike.net/maps/gbfs/v2/nextbike_kg/gbfs.json',
                'bbox' => null, // Cologne-only system already.
            ],
            'fallback' => ['bikes' => 1250, 'escooters' => 0],
        ],
        [
            'id' => 'call-a-bike',
            'name' => 'Call a Bike',
            'types' => ['bike'],
            'color' => '#ec0016',
            'source' => [
                'type' => 'gbfs-free-floating',
                'discovery_url' => 'https://api.mobidata-bw.de/sharing/gbfs/v3/callabike/gbfs',
                'bbox' => COLOGNE_BBOX, // nationwide feed, needs filtering.
                'default_form_factor' => 'bicycle',
            ],
            'fallback' => ['bikes' => 480, 'escooters' => 0],
        ],
        [
            'id' => 'donkey-republic',
            'name' => 'Donkey Republic',
            'types' => ['bike'],
            'color' => '#f2b01e',
            'source' => ['type' => 'static'], // no public feed for Cologne yet.
            'fallback' => ['bikes' => 310, 'escooters' => 0],
        ],
        [
            'id' => 'lime',
            'name' => 'Lime',
            'types' => ['escooter', 'bike'],
            'color' => '#00e676',
            'source' => ['type' => 'static'], // no public feed for Cologne yet.
            'fallback' => ['bikes' => 90, 'escooters' => 640],
        ],
        [
            'id' => 'tier',
            'name' => 'TIER',
            'types' => ['escooter'],
            'color' => '#1b1b1b',
            'source' => ['type' => 'static'], // no public feed for Cologne yet.
            'fallback' => ['bikes' => 0, 'escooters' => 520],
        ],
        [
            'id' => 'bolt',
            'name' => 'Bolt',
            'types' => ['escooter'],
            'color' => '#34d186',
            'source' => ['type' => 'static'], // no public feed for Cologne yet.
            'fallback' => ['bikes' => 0, 'escooters' => 380],
        ],
        [
            'id' => 'voi',
            'name' => 'Voi',
            'types' => ['escooter'],
            'color' => '#ff2d55',
            'source' => [
                'type' => 'gbfs-free-floating',
                'discovery_url' => voi_gbfs_discovery_url(),
                // Kept even when using a MOBIDROM Cologne-only endpoint:
                // harmless there, required for the nationwide fallback feed.
                'bbox' => COLOGNE_BBOX,
                'default_form_factor' => 'scooter',
            ],
            'fallback' => ['bikes' => 0, 'escooters' => 275],
        ],
        [
            'id' => 'dott',
            'name' => 'Dott',
            'types' => ['escooter'],
            'color' => '#ffe000',
            'source' => [
                'type' => 'gbfs-free-floating',
                'discovery_url' => 'https://gbfs.api.ridedott.com/public/v2/cologne/gbfs.json',
                'bbox' => null, // Cologne-only system already.
                'default_form_factor' => 'scooter',
            ],
            'fallback' => ['bikes' => 0, 'escooters' => 210],
        ],
    ];
}
