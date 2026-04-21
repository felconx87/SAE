<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/**
 * Consulta metadata de actualizacion remota y la cachea en disco.
 */
function checkForAppUpdate(): array
{
    $result = [
        'enabled' => APP_UPDATE_MANIFEST_URL !== '',
        'current_version' => APP_VERSION,
        'update_available' => false,
        'latest_version' => APP_VERSION,
        'release_date' => null,
        'download_url' => null,
        'changelog' => null,
        'error' => null,
    ];

    if (!$result['enabled']) {
        return $result;
    }

    $cacheDir = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/update_check.json';

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    $useCache = is_file($cacheFile) && (time() - (int) @filemtime($cacheFile) < APP_UPDATE_CACHE_TTL);
    if ($useCache) {
        $cached = @json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return array_merge($result, $cached);
        }
    }

    $manifestRaw = fetchUpdateManifest(APP_UPDATE_MANIFEST_URL);
    if ($manifestRaw === null) {
        $result['error'] = 'No se pudo consultar el manifiesto remoto.';
        writeUpdateCache($cacheFile, $result);
        return $result;
    }

    $manifest = json_decode($manifestRaw, true);
    if (!is_array($manifest) || empty($manifest['latest_version'])) {
        $result['error'] = 'El manifiesto remoto no tiene un formato valido.';
        writeUpdateCache($cacheFile, $result);
        return $result;
    }

    $latestVersion = (string) $manifest['latest_version'];
    $result['latest_version'] = $latestVersion;
    $result['release_date'] = isset($manifest['release_date']) ? (string) $manifest['release_date'] : null;
    $result['download_url'] = isset($manifest['download_url']) ? (string) $manifest['download_url'] : null;
    $result['changelog'] = isset($manifest['changelog']) ? (string) $manifest['changelog'] : null;
    $result['update_available'] = version_compare($latestVersion, APP_VERSION, '>');

    writeUpdateCache($cacheFile, $result);
    return $result;
}

function fetchUpdateManifest(string $url): ?string
{
    if ($url === '') {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'PanelMateriales-UpdateChecker/1.0',
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (is_string($response) && $status >= 200 && $status < 300) {
            return $response;
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "Accept: application/json\r\nUser-Agent: PanelMateriales-UpdateChecker/1.0\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    return is_string($response) ? $response : null;
}

function writeUpdateCache(string $path, array $data): void
{
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
