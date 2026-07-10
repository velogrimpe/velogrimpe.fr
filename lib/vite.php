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
 * Collecte récursivement le CSS d'un chunk et de ses imports statiques.
 *
 * Vite éclate le CSS par chunk : le CSS d'un composant importé statiquement
 * (ex. l'éditeur `SectionTextEditor`) vit dans l'entrée de ce sous-chunk, pas
 * dans celle de l'app. En prod, ce CSS n'est PAS injecté au runtime (contrairement
 * aux imports dynamiques) : il faut donc remonter la chaîne `imports` pour le
 * retrouver, sinon les styles du composant manquent. On ignore `dynamicImports`
 * (chargés + injectés à la volée par le runtime Vite).
 */
function _vite_collect_css(array $manifest, string $key, array &$seen, array &$css): void {
    if (isset($seen[$key]) || !isset($manifest[$key])) {
        return;
    }
    $seen[$key] = true;
    foreach ($manifest[$key]['css'] ?? [] as $c) {
        $css[$c] = true; // clé => dédoublonnage en conservant l'ordre d'insertion
    }
    foreach ($manifest[$key]['imports'] ?? [] as $import) {
        _vite_collect_css($manifest, $import, $seen, $css);
    }
}

/**
 * Get CSS file paths for an entry point (inclut le CSS des imports statiques).
 */
function vite_css_paths(string $entry): array {
    $manifest = vite_manifest();
    $key = "src/apps/{$entry}.ts";

    $seen = [];
    $css = [];
    _vite_collect_css($manifest, $key, $seen, $css);

    return array_map(fn($c) => '/dist/' . $c, array_keys($css));
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
