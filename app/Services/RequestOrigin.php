<?php
declare(strict_types=1);

/** Normalize browser origins without accepting credentials, paths or suffix matches. */
function somi_normalize_origin(string $value): ?string
{
    $parts = parse_url($value);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])
        || !in_array($parts['path'] ?? '', ['', '/'], true)) return null;
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return null;
    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
    return $scheme . '://' . strtolower($parts['host'])
        . ($port === ($scheme === 'https' ? 443 : 80) ? '' : ':' . $port);
}

function somi_direct_origin(): ?string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return somi_normalize_origin(($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''));
}

/** Explicit app URL or hosting-provided public URL; never trust arbitrary forwarded headers. */
function somi_configured_origin(): ?string
{
    foreach (['SOMI_APP_URL', 'RENDER_EXTERNAL_URL'] as $variable) {
        $url = trim((string) getenv($variable));
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) continue;
        $origin = somi_normalize_origin($parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        if ($origin !== null) return $origin;
    }
    return null;
}

function somi_origin_allowed(string $value): bool
{
    $origin = somi_normalize_origin($value);
    if ($origin === null) return false;
    $direct = somi_direct_origin();
    if ($origin === $direct || $origin === somi_configured_origin()) return true;
    // A browser's same-origin request can reach PHP over HTTP after TLS termination.
    // Require the exact authority and browser-generated Fetch Metadata, not just a hostname suffix.
    return ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin'
        && $direct !== null && str_starts_with($direct, 'http://')
        && str_starts_with($origin, 'https://')
        && substr($direct, 7) === substr($origin, 8);
}

function somi_session_is_secure(): bool
{
    $direct = somi_direct_origin();
    if ($direct !== null && str_starts_with($direct, 'https://')) return true;
    $configured = somi_configured_origin();
    if ($configured !== null && str_starts_with($configured, 'https://') && $direct !== null
        && substr($configured, 8) === substr($direct, 7)) return true;
    $origin = somi_normalize_origin($_SERVER['HTTP_ORIGIN'] ?? '');
    return $origin !== null && str_starts_with($origin, 'https://')
        && ($origin === $configured || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin')
        && somi_origin_allowed($origin);
}
