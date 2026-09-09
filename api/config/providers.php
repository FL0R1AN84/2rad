<?php
/**
 * Provider configuration: which GBFS feed (if any) backs each provider.
 * There is no dummy/fake data anywhere in this app: providers without a
 * working live feed are reported as unavailable (see api/providers.php),
 * never with made-up numbers.
 *
 * source.type:
 *   - 'gbfs-stations'      station-based system (station_status.json)
 *   - 'gbfs-free-floating' free-floating system (free_bike_status.json)
 *   - 'static'             no known public feed yet; reported as unavailable
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
 * Shared OAuth2 client-credentials config for all MOBIDROM-backed feeds
 * (they all use the same Keycloak client). Only the secret is sensitive;
 * token URL and client id have working defaults but can be overridden.
 * Returns null if MOBIDROM_GBFS_CLIENT_SECRET isn't configured, meaning
 * "don't use MOBIDROM for anything, fall back per-provider instead".
 */
function mobidrom_auth(): ?array
{
    $clientSecret = getenv('MOBIDROM_GBFS_CLIENT_SECRET');
    if ($clientSecret === false || $clientSecret === '') {
        return null;
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
        'type' => 'oauth2-client-credentials',
        'token_url' => $tokenUrl,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ];
}

/**
 * Voi publishes open GBFS data for Cologne via the NRW mobility-data
 * agency MOBIDROM (mobilitaetsdaten.nrw), authenticated via OAuth2
 * client-credentials (Keycloak). MOBIDROM data is always preferred over
 * other sources when available (falls back to Voi's nationwide feed,
 * filtered to Cologne, no auth required, otherwise).
 */
function voi_gbfs_source(): array
{
    $auth = mobidrom_auth();
    if ($auth === null) {
        return [
            'type' => 'gbfs-free-floating',
            'discovery_url' => 'https://api.mobidata-bw.de/sharing/gbfs/v3/voi_de/gbfs',
            'bbox' => COLOGNE_BBOX, // nationwide feed, needs filtering.
            'default_form_factor' => 'scooter',
            'label' => 'voi-de-nationwide', // MOBIDROM_GBFS_CLIENT_SECRET not set.
        ];
    }

    $discoveryUrl = getenv('MOBIDROM_VOI_GBFS_URL');
    if ($discoveryUrl === false || $discoveryUrl === '') {
        $discoveryUrl = 'https://www.mobilitaetsdaten.nrw/api/systemadapter-gbfs-provider/feed/v3.0/voi-koeln/source-voi-koeln/gbfs.json';
    }

    return [
        'type' => 'gbfs-free-floating',
        'discovery_url' => $discoveryUrl,
        'bbox' => null, // Cologne-only dataset already.
        'default_form_factor' => 'scooter',
        'label' => 'mobidrom-cologne', // MOBIDROM_GBFS_CLIENT_SECRET is set.
        'auth' => $auth,
    ];
}

/**
 * Bolt publishes open GBFS data for a combined Cologne+Bonn region via
 * MOBIDROM. No public nationwide fallback is known for Bolt, so without
 * MOBIDROM credentials this provider is reported as unavailable.
 */
function bolt_gbfs_source(): array
{
    $auth = mobidrom_auth();
    if ($auth === null) {
        return ['type' => 'static']; // no MOBIDROM credentials, no other known feed.
    }

    $discoveryUrl = getenv('MOBIDROM_BOLT_GBFS_URL');
    if ($discoveryUrl === false || $discoveryUrl === '') {
        $discoveryUrl = 'https://www.mobilitaetsdaten.nrw/api/systemadapter-gbfs-provider/feed/v3.0/bolt-koeln-bonn/source-bolt-koeln-bonn/gbfs.json';
    }

    return [
        'type' => 'gbfs-free-floating',
        'discovery_url' => $discoveryUrl,
        'bbox' => COLOGNE_BBOX, // combined Cologne+Bonn dataset, filter to Cologne only.
        'default_form_factor' => 'scooter',
        'label' => 'mobidrom-cologne-bonn',
        'auth' => $auth,
    ];
}

function get_provider_config(): array
{
    return [
        [
            'id' => 'kvb-rad',
            'name' => 'KVB Rad',
            'types' => ['bike'],
            'color' => '#e30613',
            'source' => [
                'type' => 'gbfs-stations',
                'discovery_url' => 'https://gbfs.nextbike.net/maps/gbfs/v2/nextbike_kg/gbfs.json',
                'bbox' => null, // Cologne-only system already.
            ],
        ],
        [
            'id' => 'call-a-bike',
            'name' => 'Call a Bike',
            'types' => ['bike'],
            'color' => '#bd0012',
            'source' => [
                'type' => 'gbfs-free-floating',
                'discovery_url' => 'https://api.mobidata-bw.de/sharing/gbfs/v3/callabike/gbfs',
                'bbox' => COLOGNE_BBOX, // nationwide feed, needs filtering.
                'default_form_factor' => 'bicycle',
            ],
        ],
        [
            'id' => 'ryde',
            'name' => 'Ryde',
            'types' => ['escooter'],
            'color' => '#3ea219',
            'source' => [
                'type' => 'gbfs-free-floating',
                'discovery_url' => 'https://www.mobilitaetsdaten.nrw/api/systemadapter-gbfs-provider/feed/v3.0/ryde-koeln/source-ryde-koeln/gbfs.json',
                'bbox' => null, // Cologne-only dataset
                'default_form_factor' => 'scooter',
                'label' => 'mobidrom-cologne',
                'auth' => mobidrom_auth(), // Braucht OAuth2-Authentifizierung
    ],
],
        [
            'id' => 'lime',
            'name' => 'Lime',
            'types' => ['escooter', 'bike'],
            'color' => '#00b100',
            // MOBIDROM's "lime-nrw" dataset exists but only covers Dortmund
            // and Essen (no Cologne system as of Sept. 2026) — not usable here.
            'source' => ['type' => 'static'],
        ],
        [
            'id' => 'tier',
            'name' => 'TIER',
            'types' => ['escooter'],
            'color' => '#1b1b1b',
            'source' => ['type' => 'static'], // no public feed for Cologne yet.
        ],
        [
            'id' => 'bolt',
            'name' => 'Bolt',
            'types' => ['escooter'],
            'color' => '#18784c',
            'source' => bolt_gbfs_source(),
        ],
        [
            'id' => 'voi',
            'name' => 'Voi',
            'types' => ['escooter'],
            'color' => '#d04740',
            'source' => voi_gbfs_source(),
        ],
        [
            'id' => 'dott',
            'name' => 'Dott',
            'types' => ['escooter'],
            'color' => '#009ddb',
            'source' => [
                'type' => 'gbfs-free-floating',
                'discovery_url' => 'https://gbfs.api.ridedott.com/public/v2/cologne/gbfs.json',
                'bbox' => null, // Cologne-only system already.
                'default_form_factor' => 'scooter',
            ],
        ],
    ];
}
