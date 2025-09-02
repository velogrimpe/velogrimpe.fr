<?php
require_once "../../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

$edit_logs = $mysqli->query("SELECT * FROM edit_logs ORDER BY date DESC")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Logs d'éditions</title>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php include "../../components/header.html"; ?>
  <main class="w-full flex-grow max-w-screen-2xl mx-auto p-10 flex flex-col gap-8">
    <h1 class="text-4xl font-bold text-wrap text-center">
      <span class="text-red-900">Historique des changements</span>
    </h1>
    <table class="table bg-base-100 table-zebra table-xs w-full">
      <thead class="text-base">
        <tr>
          <th>Date</th>
          <th>Utilisateur</th>
          <th>Type</th>
          <th>Table</th>
          <th>ID</th>
          <th>Changements</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($edit_logs as $log): ?>
          <tr class="bg-base-100 p-4 rounded-lg shadow-md">
            <td><?= htmlspecialchars($log['date']) ?></td>
            <td><a class="text-info"
                href="mailto:<?= htmlspecialchars($log['author_email']) ?>"><?= htmlspecialchars($log['author']) ?></a>
            </td>
            <td><?= htmlspecialchars($log['type']) ?></td>
            <td><?= htmlspecialchars($log['collection']) ?></td>
            <td><?= htmlspecialchars($log['record_id']) ?></td>
            <!-- changes is a json list of {field, old, new} -->
            <?php $changes = json_decode($log['changes'], true); ?>
            <td>
              <div class="flex flex-row flex-wrap gap-2">
                <?php foreach ($changes as $change): ?>
                  <div>
                    <strong><?= htmlspecialchars($change['field']) ?>:</strong>
                    <span class="line-through text-danger"><?= htmlspecialchars($change['old']) ?></span>
                    <span class="font-bold text-success"><?= htmlspecialchars($change['new']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
    </table>
  </main>
  <?php include "../../components/footer.html"; ?>
</body>

</html>