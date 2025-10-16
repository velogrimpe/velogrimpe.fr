<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

$page = $_GET['page'] ?? 0;
$limit = 100;
$offset = $page * $limit;

$edit_logs = $mysqli->prepare("SELECT * FROM edit_logs ORDER BY date DESC LIMIT ? OFFSET ?");
$edit_logs->bind_param("ii", $limit, $offset);
$edit_logs->execute();
$edit_logs = $edit_logs->get_result()->fetch_all(MYSQLI_ASSOC);

$page_count = $mysqli->query("SELECT COUNT(*) as count FROM edit_logs")->fetch_assoc()['count'];
$page_count = ceil($page_count / $limit);

$type_labels = [
  'insert' => 'Création',
  'update' => 'Modification',
  'delete' => 'Suppression',
];
$table_labels = [
  'commentaires_falaises' => "Commentaire",
  'falaises' => "Falaise",
  'falaises_liens' => "Lien Falaise",
  'gares' => "Gare",
  'train' => "Train",
  'velo' => "Itinéraire Vélo",
  'villes' => "Villes",
  'zones' => "Zones"
];


$formatter = new IntlDateFormatter(
  'fr_FR',                // Locale française
  IntlDateFormatter::SHORT, // Format long pour la date
  IntlDateFormatter::SHORT, // Format court pour l'heure
  'Europe/Paris',         // Fuseau horaire
  IntlDateFormatter::GREGORIAN,
  "d' 'MMM' 'YY 'à 'HH'h'mm"    // Pattern personnalisé
);

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
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
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
          <th title="Voir la falaise concernée">🧗</th>
          <th>Changements</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($edit_logs as $log): ?>
          <tr class="bg-base-100 p-4 rounded-lg shadow-md">
            <td><?= $formatter->format(new DateTime($log['date'])) ?></td>
            <td>
              <?= htmlspecialchars($log['author']) ?>
              <a class="" href="mailto:<?= htmlspecialchars($log['author_email']) ?>">
                <svg class="w-3 h-3 fill-current inline">
                  <use xlink:href="/symbols/icons.svg#ri-mail-fill"></use>
                </svg>
              </a>
            </td>
            <td><?= $type_labels[$log['type']] ?? htmlspecialchars($log['type']) ?></td>
            <td><?= $table_labels[$log['collection']] ?? htmlspecialchars($log['collection']) ?></td>
            <td><?= htmlspecialchars($log['record_id']) ?></td>
            <td>
              <?php if ($log['falaise_id'] !== null): ?>
                <a href="/falaise.php?falaise_id=<?= $log['falaise_id'] ?>">
                  <svg class="w-4 h-4 fill-current">
                    <use xlink:href="/symbols/icons.svg#ri-eye-fill"></use>
                  </svg>
                </a>
              <?php endif; ?>
            </td>
            <!-- changes is a json list of {field, old, new} -->
            <?php $changes = json_decode($log['changes'], true); ?>
            <td>
              <div class="flex flex-row flex-wrap gap-2">
                <?php foreach ($changes as $change): ?>
                  <div>
                    <strong><?= htmlspecialchars($change['field']) ?>:</strong>
                    <span class="line-through text-error"><?= htmlspecialchars($change['old']) ?></span>
                    <span class="font-bold text-success"><?= htmlspecialchars($change['new']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
    </table>
    <div class="flex flex-row gap-2 items-center mx-auto">
      <?php if ($page > 0): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">&larr;</a>
      <?php else: ?>
        <button class="btn btn-disabled btn-sm">&larr;</button>
      <?php endif; ?>
      <div class="font-bold text-sm">Page <?= $page + 1 ?> / <?= $page_count ?></div>
      <?php if ($page < $page_count - 1): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">&rarr;</a>
      <?php else: ?>
        <button class="btn btn-disabled btn-sm">&rarr;</button>
      <?php endif; ?>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>