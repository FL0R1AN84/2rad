<?php
/**
 * Minimal file-based cache for GBFS lookups.
 *
 * Keeps the last known-good value per provider so that:
 *  - upstream feeds aren't hammered on every page load (TTL-based reuse)
 *  - a temporarily unreachable feed doesn't wipe out real data; we keep
 *    serving the last successful reading until it can be refreshed
 */

const CACHE_FILE = __DIR__ . '/../cache/gbfs_cache.json';
const CACHE_TTL_SECONDS = 120;

function cache_read_all(): array
{
    if (!is_file(CACHE_FILE)) {
        return [];
    }
    $content = @file_get_contents(CACHE_FILE);
    $decoded = $content !== false ? json_decode($content, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function cache_write_all(array $cache): void
{
    $dir = dirname(CACHE_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE));
}

/**
 * Returns the cached ['bikes'=>.., 'escooters'=>.., 'fetched_at'=>..] entry
 * for a provider id if it's still within TTL_SECONDS, otherwise null.
 */
function cache_get_fresh(array $cache, string $providerId): ?array
{
    $entry = $cache[$providerId] ?? null;
    if ($entry === null) {
        return null;
    }
    if (time() - (int) ($entry['fetched_at'] ?? 0) > CACHE_TTL_SECONDS) {
        return null;
    }
    return $entry;
}

/**
 * Returns the cached entry for a provider id regardless of age, used as a
 * last-resort fallback when a live fetch fails.
 */
function cache_get_stale(array $cache, string $providerId): ?array
{
    return $cache[$providerId] ?? null;
}
