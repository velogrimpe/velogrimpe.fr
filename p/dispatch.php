<?php
$slug = trim($_GET['slug'] ?? '');

if (empty($slug) || !preg_match('/^[a-z0-9-]+(\/[a-z0-9-]+)*$/', $slug)) {
  header('Location: /404.php');
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$preview = false;
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$adminParam = $_GET['admin'] ?? null;
if ($adminParam && $adminParam === $config['admin_token']) {
  $preview = true;
}

if ($preview) {
  $stmt = $mysqli->prepare("SELECT * FROM pages WHERE slug = ?");
} else {
  $stmt = $mysqli->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
}
$stmt->bind_param('s', $slug);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
  header('Location: /404.php');
  exit;
}

$sections = json_decode($page['sections'], true) ?? [];

$banner_title = trim($page['banner_title'] ?? '');
$banner_img = trim($page['banner_img'] ?? '');
if (!$banner_img && $banner_title) {
  $banner_img = '/images/mw/027-velo-aiguille-40.webp';
}
$has_banner = $banner_title !== '' || $banner_img !== '';
$og_image = $banner_img ?: '';

$siblings = [];
$slash_pos = strrpos($page['slug'], '/');
if ($slash_pos !== false) {
  $dirname = substr($page['slug'], 0, $slash_pos);
  $like_siblings = $dirname . '/%';
  $like_deeper = $dirname . '/%/%';
  $stmt = $mysqli->prepare("SELECT slug, title, short_title FROM pages WHERE status = 'published' AND slug LIKE ? AND slug NOT LIKE ? ORDER BY id");
  $stmt->bind_param('ss', $like_siblings, $like_deeper);
  $stmt->execute();
  $siblings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/schema.php';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page['title']) ?> - Velogrimpe.fr</title>
  <meta name="description" content="<?= htmlspecialchars($page['description'] ?? '') ?>" />
  <meta property="og:title" content="<?= htmlspecialchars($page['title']) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($page['description'] ?? '') ?>" />
  <meta property="og:type" content="article" />
  <meta property="og:site_name" content="Velogrimpe.fr" />
  <?php if ($og_image): ?>
    <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>" />
  <?php endif; ?>
  <?php vite_css('main'); ?>
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <?php
  // --- Données structurées JSON-LD ---
  $page_url = VG_BASE . '/p/' . $page['slug'];
  $article = [
    '@type'            => 'Article',
    'headline'         => $page['title'],
    'mainEntityOfPage' => $page_url,
    'url'              => $page_url,
    'author'           => ['@id' => VG_BASE . '/#organization'],
    'publisher'        => ['@id' => VG_BASE . '/#organization'],
  ];
  if (!empty($page['description'])) {
    $article['description'] = $page['description'];
  }
  if (!empty($og_image)) {
    $article['image'] = vg_url($og_image);
  }

  $breadcrumb = vg_breadcrumb([
    ['name' => 'Accueil', 'url' => '/'],
    ['name' => $page['title'], 'url' => '/p/' . $page['slug']],
  ]);

  vg_jsonld(vg_organization(), $article, $breadcrumb);
  ?>
</head>

<body class="flex flex-col min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <?php if ($has_banner): ?>
    <div class="hero min-h-100 bg-center" style="background-image: url(<?= htmlspecialchars($banner_img) ?>);">
      <div class="hero-overlay bg-slate-600/70"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h1 class="text-5xl font-bold"><?= htmlspecialchars($banner_title !== '' ? $banner_title : $page['title']) ?></h1>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <?php
  $current_slug = $page['slug'];
  include $_SERVER['DOCUMENT_ROOT'] . "/components/nav-page-siblings.php";
  ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto p-6">
    <?php if ($preview && $page['status'] !== 'published'): ?>
      <div class="alert alert-warning mb-4">Aperçu — cette page est en brouillon, non visible publiquement.</div>
    <?php endif; ?>
    <article class="prose max-w-none">
      <?php if (!$has_banner): ?>
        <h1><?= htmlspecialchars($page['title']) ?></h1>
      <?php endif; ?>
      <?php foreach ($sections as $section): ?>
        <?php if (($section['type'] ?? '') === 'text'): ?>
          <?= $section['html'] ?? '' ?>
        <?php elseif (($section['type'] ?? '') === 'iframe'): ?>
          <?php if (!empty($section['title'])): ?>
            <h2><?= htmlspecialchars($section['title']) ?></h2>
          <?php endif; ?>
          <?php if (!empty($section['intro_html'])): ?>
            <?= $section['intro_html'] ?>
          <?php endif; ?>
          <?php if (!empty($section['embed_code'])): ?>
            <div class="not-prose my-4"><?= $section['embed_code'] ?></div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </article>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>
