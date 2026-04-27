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

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
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
  <?php vite_css('main'); ?>
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto p-6">
    <?php if ($preview && $page['status'] !== 'published'): ?>
      <div class="alert alert-warning mb-4">Aperçu — cette page est en brouillon, non visible publiquement.</div>
    <?php endif; ?>
    <article class="prose max-w-none">
      <h1><?= htmlspecialchars($page['title']) ?></h1>
      <?php foreach ($sections as $section): ?>
        <?php if (($section['type'] ?? '') === 'text'): ?>
          <?= $section['html'] ?? '' ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </article>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>
