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
 * Voi publishes open GBFS data for Cologne via the NRW mobility-data
 * agency MOBIDROM (mobilitaetsdaten.nrw), authenticated via OAuth2
 * client-credentials (Keycloak). Only the client *secret* is sensitive,
 * so it's the only required environment variable; token URL, client id
 * and resource URL have working defaults but can be overridden too.
 *
 * Falls back to Voi's nationwide feed (filtered to Cologne, no auth
 * required) when MOBIDROM_GBFS_CLIENT_SECRET isn't configured.
 */
function voi_gbfs_source(): array
{
    $clientSecret = getenv('MOBIDROM_GBFS_CLIENT_SECRET');
    if ($clientSecret === false || $clientSecret === '') {
        return [
            'type' => 'gbfs-free-floating',
            'discovery_url' => 'https://api.mobidata-bw.de/sharing/gbfs/v3/voi_de/gbfs',
            'bbox' => COLOGNE_BBOX, // nationwide feed, needs filtering.
            'default_form_factor' => 'scooter',
        ];
    }

    $discoveryUrl = getenv('MOBIDROM_VOI_GBFS_URL');
    if ($discoveryUrl === false || $discoveryUrl === '') {
        $discoveryUrl = 'https://www.mobilitaetsdaten.nrw/api/systemadapter-gbfs-provider/feed/v3.0/voi-koeln/source-voi-koeln/gbfs.json';
    }
    $tokenUrl = getenv('MOBIDROM_GBFS_TOKEN_URL');
    if ($tokenUrl === false || $tokenUrl === '') {
        $tokenUrl = 'https://www.mobilitaetsdaten.nrw/keycloak/realms/mobidrom/protocol/openid-connect/token';
    }
    $clientId = getenv('MOBIDROM_GBFS_CLIENT_ID');
    if ($clientId === false || $clientId === '') {
        $clientId = 'gbfs-api';
    }

    return [
        'type' => 'gbfs-free-floating',
        'discovery_url' => $discoveryUrl,
        'bbox' => null, // Cologne-only dataset already.
        'default_form_factor' => 'scooter',
        'auth' => [
            'type' => 'oauth2-client-credentials',
            'token_url' => $tokenUrl,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
    ];
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
            'source' => voi_gbfs_source(),
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
