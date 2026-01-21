<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];

if (!$admin) {
  die('Accès refusé');
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter une zone (admin)</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($admin): ?>
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
      <?php else: ?>
        document.getElementById('admin').value = '0';
      <?php endif; ?>
    });
  </script>
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4 prose-pre:my-0 prose-pre:text-center">
    <h1 class="text-4xl font-bold text-wrap text-center"> AJOUTER UNE ZONE (ADMIN) </h1>
    <form method="post" action="/api/add_zone.php" class="flex flex-col gap-4">
      <input type="hidden" id="admin" name="admin" value="0" />
      <label class="form-control" for="zone_nom">
        <b>Zone :</b>
        <input type="text" class="input input-primary input-sm" id="zone_nom" name="zone_nom" required>
      </label>
      <button class="btn btn-primary" type="submit">AJOUTER LA ZONE</button>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>