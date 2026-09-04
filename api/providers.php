<?php
/**
 * Dummy data endpoint for bike/eScooter sharing providers in Cologne.
 * Replace later with real API integrations (e.g. providers' GBFS feeds).
 * Response format stays the same so the frontend keeps working unchanged.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Dummy dataset. "bikes" and "escooters" are currently available vehicles.
$providers = [
    [
        'id' => 'kvb-rad',
        'name' => 'KVB Rad',
        'types' => ['bike'],
        'bikes' => 1250,
        'escooters' => 0,
        'color' => '#004b93',
    ],
    [
        'id' => 'call-a-bike',
        'name' => 'Call a Bike',
        'types' => ['bike'],
        'bikes' => 480,
        'escooters' => 0,
        'color' => '#ec0016',
    ],
    [
        'id' => 'donkey-republic',
        'name' => 'Donkey Republic',
        'types' => ['bike'],
        'bikes' => 310,
        'escooters' => 0,
        'color' => '#f2b01e',
    ],
    [
        'id' => 'lime',
        'name' => 'Lime',
        'types' => ['escooter', 'bike'],
        'bikes' => 90,
        'escooters' => 640,
        'color' => '#00e676',
    ],
    [
        'id' => 'tier',
        'name' => 'TIER',
        'types' => ['escooter'],
        'bikes' => 0,
        'escooters' => 520,
        'color' => '#1b1b1b',
    ],
    [
        'id' => 'bolt',
        'name' => 'Bolt',
        'types' => ['escooter'],
        'bikes' => 0,
        'escooters' => 380,
        'color' => '#34d186',
    ],
    [
        'id' => 'voi',
        'name' => 'Voi',
        'types' => ['escooter'],
        'bikes' => 0,
        'escooters' => 275,
        'color' => '#ff2d55',
    ],
    [
        'id' => 'dott',
        'name' => 'Dott',
        'types' => ['escooter'],
        'bikes' => 0,
        'escooters' => 210,
        'color' => '#ffe000',
    ],
];

echo json_encode([
    'updated' => date('c'),
    'providers' => $providers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
