<?php
/**
 * Map bundle manifest helper — resolves versioned filenames produced by
 * `frontend/build-map.ts`.
 *
 * The build emits `dist/map-{leafletVer}.{js,css}` and
 * `dist/map-maplibre-{maplibreVer}.{js,css}` plus a `dist/map-bundles.json`
 * manifest. PHP reads the manifest to pick the right `<script src>`.
 *
 * Usage:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
 *   map_bundle_css('map-maplibre');  // <link rel="stylesheet" href="/dist/map-maplibre-5.24.0.css" />
 *   map_bundle_js('map-maplibre');   // <script src="/dist/map-maplibre-5.24.0.js"></script>
 */

$_map_bundle_manifest = null;

function map_bundle_manifest(): array
{
    global $_map_bundle_manifest;
    if ($_map_bundle_manifest === null) {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/dist/map-bundles.json';
        $_map_bundle_manifest = file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?? [])
            : [];
    }
    return $_map_bundle_manifest;
}

function map_bundle_js_path(string $name): ?string
{
    return map_bundle_manifest()[$name]['js'] ?? null;
}

function map_bundle_css_path(string $name): ?string
{
    return map_bundle_manifest()[$name]['css'] ?? null;
}

function map_bundle_js(string $name): void
{
    $p = map_bundle_js_path($name);
    if ($p) {
        echo '<script src="' . htmlspecialchars($p) . '"></script>' . "\n";
    }
}

function map_bundle_css(string $name): void
{
    $p = map_bundle_css_path($name);
    if ($p) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($p) . '" />' . "\n";
    }
}
