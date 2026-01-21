<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/fetch_mail_template.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

$articleDateFormatter = new IntlDateFormatter(
  'fr_FR',                // Locale française
  IntlDateFormatter::LONG, // Format long pour la date
  IntlDateFormatter::NONE
);
$newsletterDateFormatter = new IntlDateFormatter(
  'fr_FR',                // Locale française
  IntlDateFormatter::LONG,
  IntlDateFormatter::NONE,
  null,
  null,
  'MMMM yyyy'
);
$logoUrl = "https://velogrimpe.fr/images/logo_velogrimpe.png";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Vélogrimpe.fr - Actualités</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/actualites/" />
  <meta name="description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Actualités">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/actualites/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="./index.css" />
  <link rel="manifest" href="./site.webmanifest" />
</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="pb-4">
    <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/075-matos-5.webp);">
      <div class="hero-overlay bg-slate-600/70"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h1 class="text-5xl font-bold">Actualités Vélogrimpe</h1>
        </div>
      </div>
    </div>
    <div class="bg-base-100 p-8 max-w-3xl mx-auto">
      <h2 class="text-3xl font-bold mb-8">Dernières Actualités</h2>
      <div class="flex flex-col gap-8 justify-stretch w-full">
        <?php
        $articles = [];
        $newsletters = [];
        // Load articles from the 'articles' directory
        $article_files = glob($_SERVER['DOCUMENT_ROOT'] . '/articles/20*.php');
        $newsletters_files = glob($_SERVER['DOCUMENT_ROOT'] . '/actualites/20*.php');
        // merge and sort by date descending
        foreach (array_merge($article_files, $newsletters_files) as $file) {

          $type = strpos($file, '/actualites/') !== false ? 'actualites' : 'articles';
          $template = fetchMailTemplate("/" . $type . "/" . basename($file));
          $title = $template['title'];
          $desc = $template['description'];
          $image = $template['image'];
          $filename = basename($file);
          $date_str = $type === "actualites" ? substr($filename, 0, 7) . "-01" : substr($filename, 0, 10);
          $date = DateTime::createFromFormat('Y-m-d', $date_str);

          if ($date) {
            $articles[] = [
              'date' => $date,
              'file' => $file,
              'title' => $title,
              'description' => $desc,
              'image' => $type === "actualites" ? $logoUrl : $image,
              'type' => $type,
              'url' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file)
            ];
          }
        }
        usort($articles, function ($a, $b) {
          return $b['date'] <=> $a['date'];
        });
        ?>
        <?php foreach ($articles as $article): ?>
              <?php
              $title = htmlspecialchars($article['title']);
              $url = htmlspecialchars($article['url']);
              $typeLabel = $article['type'] === 'actualites' ? 'Actualité' : 'Article';
              $dateFormatted = $article['type'] === "actualites" ? ucfirst($newsletterDateFormatter->format($article['date'])) : $articleDateFormatter->format($article['date']);
              ?>
              <a href="<?= $url ?>" class="card-link block font-normal">
                <div
                  class="flex flex-col-reverse sm:flex-row items-center sm:justify-between gap-2 shadow-xl hover:shadow-lg hover:bg-base-200 rounded-md transition border p-2">
                  <div class="text-center sm:text-left">
                    <h3 class="text-xl font-bold mb-1">
                      <?php if ($article['type'] === 'actualites'): ?>
                            <svg class="w-5 h-5 fill-current inline pb-1">
                              <use xlink:href="/symbols/icons.svg#mail-fill"></use>
                            </svg>
                      <?php endif; ?>
                      <?= $title ?>
                    </h3>
                    <p class="text-sm text-slate-600 mb-2"><?= $dateFormatted ?></p>
                    <p class="text-normal text-slate-800 mb-4"><?= htmlspecialchars($article['description']) ?></p>
                    <div class="text-primary font-bold sm:text-xs text-normal">Lire la suite</div>
                  </div>
                  <div class="shrink-0 pt-2 sm:pt-0">
                    <img src="<?= htmlspecialchars($article['image']) ?>" alt="<?= $title ?>"
                      class="w-auto h-48 sm:w-36 sm:h-36 rounded-md object-contain" />
                  </div>
                </div>
              </a>
        <?php endforeach; ?>
      </div>
      <hr class="mt-8 mb-2" />
      <h3 class="text-2xl font-bold">Inscrivez-vous à notre newsletter</h3>
      <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/newsletter-form.php"; ?>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>