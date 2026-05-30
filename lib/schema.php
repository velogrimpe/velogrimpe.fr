<?php
/**
 * JSON-LD (schema.org) helper - génère les données structurées pour le SEO.
 *
 * Usage :
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/schema.php';
 *   // dans le <head> :
 *   vg_jsonld(
 *     vg_organization(),
 *     [ '@type' => 'TouristAttraction', ... ],
 *     vg_breadcrumb([ ['name' => 'Accueil', 'url' => '/'], ... ])
 *   );
 *
 * Conventions :
 * - Passer des valeurs BRUTES (issues de la BDD) : json_encode gère l'échappement.
 *   Ne PAS pré-échapper avec htmlspecialchars (cela produirait du double-échappement).
 * - Les nœuds réutilisables exposent un @id stable, référencé ailleurs via
 *   ['@id' => ...] pour éviter de dupliquer les données.
 */

const VG_BASE = 'https://velogrimpe.fr';

/**
 * Préfixe une URL relative avec la base du site. Les URLs absolues sont
 * renvoyées telles quelles.
 */
function vg_url(string $path): string {
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return VG_BASE . '/' . ltrim($path, '/');
}

/**
 * Nœud Organization (entité éditrice du site). @id stable réutilisable.
 */
function vg_organization(): array {
    return [
        '@type'  => 'Organization',
        '@id'    => VG_BASE . '/#organization',
        'name'   => 'Vélogrimpe',
        'url'    => VG_BASE . '/',
        'logo'   => [
            '@type' => 'ImageObject',
            'url'   => VG_BASE . '/images/logo_velogrimpe.png',
        ],
        'sameAs' => [
            'https://instagram.com/velogrimpe',
            'https://github.com/velogrimpe',
        ],
    ];
}

/**
 * Nœud WebSite (le site dans son ensemble). @id stable réutilisable.
 */
function vg_website(): array {
    return [
        '@type'      => 'WebSite',
        '@id'        => VG_BASE . '/#website',
        'url'        => VG_BASE . '/',
        'name'       => 'Vélogrimpe.fr',
        'inLanguage' => 'fr-FR',
        'publisher'  => ['@id' => VG_BASE . '/#organization'],
    ];
}

/**
 * Construit un BreadcrumbList à partir d'une liste ordonnée d'étapes.
 *
 * @param array $items Liste de ['name' => string, 'url' => string]
 *                     (url relative ou absolue ; la dernière étape = page courante).
 */
function vg_breadcrumb(array $items): array {
    $elements = [];
    foreach (array_values($items) as $i => $item) {
        $elements[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $item['name'],
            'item'     => vg_url($item['url']),
        ];
    }
    return [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}

/**
 * Émet un unique bloc <script type="application/ld+json"> regroupant tous
 * les nœuds passés dans un @graph. Les nœuds vides (null / []) sont ignorés.
 */
function vg_jsonld(array ...$nodes): void {
    $graph = array_values(array_filter($nodes, fn($n) => !empty($n)));
    if (empty($graph)) {
        return;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ];

    $json = json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    echo '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
}
