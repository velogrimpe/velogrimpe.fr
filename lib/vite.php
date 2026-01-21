<?php
/**
 * Vite manifest helper - resolves hashed asset paths from the manifest
 *
 * Usage:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
 *   vite_js('carte-info');   // outputs <script type="module" src="/dist/carte-info.js">
 *   vite_css('carte-info');  // outputs <link rel="stylesheet" href="/dist/assets/carte-info-XXX.css">
 */

$_vite_manifest = null;

function vite_manifest(): array {
    global $_vite_manifest;

    if ($_vite_manifest === null) {
        $manifest_path = $_SERVER['DOCUMENT_ROOT'] . '/dist/.vite/manifest.json';
        if (file_exists($manifest_path)) {
            $_vite_manifest = json_decode(file_get_contents($manifest_path), true) ?? [];
        } else {
            $_vite_manifest = [];
        }
    }

    return $_vite_manifest;
}

/**
 * Get the JS file path for an entry point
 */
function vite_js_path(string $entry): ?string {
    $manifest = vite_manifest();
    $key = "src/apps/{$entry}.ts";

    if (isset($manifest[$key]['file'])) {
        return '/dist/' . $manifest[$key]['file'];
    }

    return null;
}

/**
 * Get CSS file paths for an entry point
 */
function vite_css_paths(string $entry): array {
    $manifest = vite_manifest();
    $key = "src/apps/{$entry}.ts";

    if (isset($manifest[$key]['css'])) {
        return array_map(fn($css) => '/dist/' . $css, $manifest[$key]['css']);
    }

    return [];
}

/**
 * Output a script tag for an entry point
 */
function vite_js(string $entry): void {
    $path = vite_js_path($entry);
    if ($path) {
        echo '<script type="module" src="' . htmlspecialchars($path) . '"></script>' . "\n";
    }
}

/**
 * Output link tags for CSS associated with an entry point
 */
function vite_css(string $entry): void {
    foreach (vite_css_paths($entry) as $path) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($path) . '" />' . "\n";
    }
}
