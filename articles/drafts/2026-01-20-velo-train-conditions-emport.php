<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

// Extract table from HTML file
$html_content = file_get_contents(__DIR__ . '/public/velogrimpe-x-cartotrain-aii9.html');
preg_match('/<table[^>]*>.*<\/table>/s', $html_content, $matches);
$table_html = $matches[0] ?? '';

// Remove first row if it has no text content (only empty cells)
$table_html = preg_replace('/<tbody>\s*<tr>(?:\s*<td[^>]*>\s*<\/td>\s*)+<\/tr>/s', '<tbody>', $table_html);

// Convert plain URLs to formatted links
$table_html = preg_replace_callback(
  '/https?:\/\/[^\s<>&]+/',
  function ($matches) {
    $url = rtrim($matches[0], '.,;:');
    $parsed = parse_url($url);
    $domain = preg_replace('/^www\./', '', $parsed['host'] ?? '');
    return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="link link-primary">' . htmlspecialchars($domain) . '</a>';
  },
  $table_html
);

$description = "Tableau récapitulatif des conditions d'emport de vélos dans les trains en France : TGV, TER, Intercités, Ouigo et compagnies étrangères. - CARTOTRAIN x Velogrimpe";
$title = "Vélo + Train : Conditions d'emport en France - CARTOTRAIN x Vélogrimpe";
$image = "https://velogrimpe.fr/images/articles/2026-01-20-velo-train-conditions-emport/logo-cartotrain.png";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title><?= $title ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <meta name="description" content="<?= $description ?>">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/articles/2026-01-20-velo-train-conditions-emport.php">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($image) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($image) ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <style>
    .table-container {
      overflow-x: auto;
      max-width: 100%;
    }

    .table-container table {
      border-collapse: collapse;
      font-size: 0.875rem;
    }

    .table-container td {
      padding: 0.5rem;
      border: 1px solid #d1d5db;
    }

    @media (max-width: 768px) {
      .table-container table {
        font-size: 0.75rem;
      }
    }
  </style>
</head>

<body>
  <?php include "../components/header.html"; ?>
  <main class="pb-4">
    <div class="hero min-h-50"
      style="background-image: url(/images/articles/2026-01-20-velo-train-conditions-emport/cartotrain.jpg);">
      <div class="hero-overlay bg-slate-600/50"></div>
      <div class="hero-content text-center text-base-100">
        <div class="text-4xl font-bold">CARTOTRAIN x Velogrimpe</div>
      </div>
    </div>
    <div class="bg-base-100 p-4 md:p-8 max-w-6xl mx-auto">
      <img src="/images/articles/2026-01-20-velo-train-conditions-emport/logo-cartotrain.png" alt="Logo Cartotrain"
        class="mx-auto mb-4 h-20" />
      <h1 class="text-3xl font-bold mb-4">Vélo + Train : Conditions d&apos;emport en France</h1>
      <p class="mb-4 text-slate-600">Tableau récapitulatif des conditions d&apos;emport de vélos (démontés et non
        démontés) dans les différents trains en France, par compagnie ferroviaire. Données fournies par <a
          href="https://cartotrain.fr" target="_blank" class="link link-primary">Cartotrain</a>.</p>
      <div class="table-container">
        <?= $table_html ?>
      </div>
      <p class="mt-6 text-sm text-slate-500 italic">Dernière mise à jour : Décembre 2025. Les informations sont
        susceptibles d&apos;évoluer, consultez les sites officiels des compagnies pour les conditions les plus récentes.
        Données fournies par <a href="https://cartotrain.fr" target="_blank" class="link link-primary">Cartotrain</a>,
        réutilisation permise avec mention de Cartotrain et lien vers leur site <a href="https://cartotrain.fr"
          target="_blank" class="link link-primary">cartotrain.fr</a>.</p>
    </div>
  </main>
  <?php include "../components/footer.php"; ?>
</body>

</html>