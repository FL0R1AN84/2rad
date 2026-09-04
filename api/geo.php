<?php
/**
 * Roughly determines from the visitor's IP whether the request comes from Cologne.
 * Uses the free ip-api.com service (no API key required, intended for low
 * volume). For local/private IPs (e.g. during development), "unknown" is
 * returned so the frontend falls back to German/English.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function get_client_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '';
}

function is_private_ip(string $ip): bool
{
    if ($ip === '') {
        return true;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

$response = [
    'city' => null,
    'countryCode' => null,
    'isCologne' => false,
];

$ip = get_client_ip();

if (!is_private_ip($ip)) {
    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,city';
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $result = @file_get_contents($url, false, $context);

    if ($result !== false) {
        $geo = json_decode($result, true);
        if (is_array($geo) && ($geo['status'] ?? '') === 'success') {
            $city = (string) ($geo['city'] ?? '');
            $response['city'] = $city;
            $response['countryCode'] = $geo['countryCode'] ?? null;
            $response['isCologne'] = (stripos($city, 'köln') !== false)
                || (stripos($city, 'koeln') !== false)
                || (stripos($city, 'cologne') !== false);
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
