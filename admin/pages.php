<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Administration des pages (admin) - Velogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <?php vite_css('pages-admin'); ?>
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-lg) mx-auto p-4">
    <div id="vue-pages-admin"></div>
    <script>
      window.__PAGES_DATA__ = {
        token: <?= json_encode($token) ?>
      };
    </script>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <?php vite_js('pages-admin'); ?>
</body>

</html>