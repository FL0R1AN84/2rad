<?php
/**
 * Minimal OAuth2 "client credentials" helper, used for GBFS feeds that
 * require authentication (e.g. MOBIDROM / mobilitaetsdaten.nrw).
 *
 * Client id/secret must never be stored in this repository. They're read
 * from environment variables at runtime (see api/config/providers.php).
 */

const OAUTH_TOKEN_CACHE_FILE = __DIR__ . '/../cache/oauth_tokens.json';

/**
 * Requests a new access token via the client-credentials grant.
 * Returns null on any failure.
 */
function oauth_request_token(string $tokenUrl, string $clientId, string $clientSecret): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => '2rad-waechter-koeln/1.0 (+https://2rad.waechter.koeln)',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        return null;
    }

    return $decoded;
}

function oauth_read_token_cache(): array
{
    if (!is_file(OAUTH_TOKEN_CACHE_FILE)) {
        return [];
    }
    $content = @file_get_contents(OAUTH_TOKEN_CACHE_FILE);
    $decoded = $content !== false ? json_decode($content, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function oauth_write_token_cache(array $cache): void
{
    $dir = dirname(OAUTH_TOKEN_CACHE_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(OAUTH_TOKEN_CACHE_FILE, json_encode($cache));
    // Cache file contains bearer tokens, not just plain JSON data;
    // best-effort lock down permissions so it isn't world-readable.
    @chmod(OAUTH_TOKEN_CACHE_FILE, 0600);
}

/**
 * Returns a valid bearer token for the given client-credentials config,
 * reusing a cached token until shortly before it expires. $cacheKey
 * should be unique per client (e.g. the provider id) so multiple OAuth
 * clients can be cached side by side.
 *
 * Returns null if no token could be obtained.
 */
function oauth_get_client_credentials_token(string $cacheKey, string $tokenUrl, string $clientId, string $clientSecret): ?string
{
    $cache = oauth_read_token_cache();
    $cached = $cache[$cacheKey] ?? null;
    // Refresh 60s before actual expiry to avoid using a token that expires mid-request.
    if ($cached !== null && time() < (int) ($cached['expires_at'] ?? 0) - 60) {
        return $cached['access_token'];
    }

    $token = oauth_request_token($tokenUrl, $clientId, $clientSecret);
    if ($token === null) {
        // Logged (without the secret) so hosting error logs show *why* a
        // provider fell back to dummy/cached data, e.g. wrong client
        // secret or an unreachable token endpoint.
        error_log("gbfs oauth: token request failed for '$cacheKey' (token_url=$tokenUrl, client_id=$clientId)");
        // Fall back to a still-valid cached token, if any, rather than failing outright.
        return ($cached !== null && time() < (int) ($cached['expires_at'] ?? 0)) ? $cached['access_token'] : null;
    }

    $cache[$cacheKey] = [
        'access_token' => $token['access_token'],
        'expires_at' => time() + (int) ($token['expires_in'] ?? 300),
    ];
    oauth_write_token_cache($cache);

    return $token['access_token'];
}
