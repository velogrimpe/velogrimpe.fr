<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_dashboard.php';

// Compteurs : chaque carte renvoie vers la liste correspondante, déjà filtrée.
$count = fn(string $sql): int => (int) $mysqli->query($sql)->fetch_row()[0];
$contributeurs = admin_dashboard_contributeurs($mysqli);
$cards = [
  ['Falaises', $count("SELECT COUNT(*) FROM falaises WHERE falaise_public = 1"), '/admin/falaises.php?statut=topo', 'success'],
  ['Falaises à vérifier', $count("SELECT COUNT(*) FROM falaises WHERE falaise_public = 2"), '/admin/falaises.php?statut=verifier', 'warning'],
  ['Accès', $count("SELECT COUNT(*) FROM acces_falaise"), '/admin/acces.php', 'primary'],
  ['Accès à vérifier', $count("SELECT COUNT(*) FROM acces_falaise WHERE public = 2"), '/admin/acces.php?statut=verifier', 'warning'],
  ['Inscrits newsletter', $count("SELECT COUNT(*) FROM mailing_list WHERE confirme = 1 AND desinscrit = 0"), '/admin/newsletter.php', ''],
  ['Contributeurs', count($contributeurs), null, ''],
];
$derniers = admin_dashboard_derniers_changements($mysqli, 50);
$top = array_slice($contributeurs, 0, 50);
$fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, 'Europe/Paris', IntlDateFormatter::GREGORIAN, "d MMM yy 'à' HH'h'mm");
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Panneau d'administration - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php $admin_active = 'dashboard'; include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-open.php"; ?>
  <main class="w-full grow max-w-(--breakpoint-lg) mx-auto p-4 flex flex-col gap-6">
    <h1 class="text-2xl font-bold"><span class="text-red-900">PANNEAU D'ADMINISTRATION</span></h1>
    <div class="flex flex-wrap gap-2">
      <a class="btn btn-outline btn-sm" href="https://couble.eu/stats-velogrimpe" target="_blank" rel="noopener">Statistiques ↗</a>
      <a class="btn btn-outline btn-sm" href="https://github.com/orgs/velogrimpe/projects/1" target="_blank" rel="noopener">Kanban ↗</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
      <?php foreach ($cards as [$label, $n, $href]): ?>
        <?php $tag = $href ? 'a href="' . htmlspecialchars($href) . '"' : 'div'; ?>
        <<?= $tag ?> class="card bg-base-100 shadow text-black <?= $href ? 'card-link transition hover:shadow-md hover:-translate-y-0.5 hover:bg-base-200' : '' ?>">
          <div class="card-body p-2 gap-0 items-center text-center">
            <span class="text-2xl font-bold text-black"><?= $n ?></span>
            <span class="text-xs text-black"><?= htmlspecialchars($label) ?></span>
          </div>
        </<?= $href ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>

    <section>
      <h2 class="text-lg font-bold mb-2">Derniers changements <span class="text-sm font-normal opacity-60"></span></h2>
      <div class="overflow-auto max-h-80 rounded-md bg-base-100"><?php /* ~8 lignes visibles, le reste défile */ ?>
        <table class="table table-sm table-pin-rows" id="derniers-changements">
          <thead><tr><th>Date</th><th>Contributeur</th><th>Falaise</th><th>Objet</th><th>Modifications</th></tr></thead>
          <tbody>
            <?php foreach ($derniers as $d): ?>
              <tr>
                <td class="whitespace-nowrap"><?= htmlspecialchars($fmt->format(new DateTime($d['date']))) ?></td>
                <td><?= htmlspecialchars($d['auteur']) ?></td>
                <td class="whitespace-nowrap">
                  <?php if ($d['falaise_id']): ?>
                    <?= $d['falaise'] !== '' ? '<b>' . htmlspecialchars($d['falaise']) . '</b>' : '<span class="opacity-60">Falaise #' . $d['falaise_id'] . ' (supprimée)</span>' ?>
                    <?php if ($d['falaise'] !== ''): ?>
                      <a class="btn btn-xs btn-square btn-outline ml-1 align-middle" href="/falaise.php?falaise_id=<?= $d['falaise_id'] ?>" title="Voir la falaise" aria-label="Voir la falaise <?= htmlspecialchars($d['falaise']) ?>"><svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#eye"></use></svg></a>
                    <?php endif; ?>
                  <?php else: ?><span class="opacity-40">—</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($d['objet']) ?></td>
                <td><?= htmlspecialchars($d['resume']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$derniers): ?><tr><td colspan="5" class="text-center opacity-60">Aucun changement.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <a class="link text-sm" href="/admin/edit_logs.php">Tout l'historique →</a>
    </section>

    <section>
      <h2 class="text-lg font-bold mb-2">Top contributeurs <span class="text-sm font-normal opacity-60">(50 premiers)</span></h2>
      <div class="overflow-auto max-h-80 rounded-md bg-base-100"><?php /* ~8 lignes visibles, le reste défile */ ?>
        <table class="table table-sm table-pin-rows" id="top-contributeurs">
          <thead><tr><th>#</th><th>Contributeur</th><th>Zone de prédilection</th><th class="text-right">Falaises</th><th class="text-right">Accès vélo</th></tr></thead>
          <tbody>
            <?php foreach ($top as $i => $c): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td class="font-semibold"><?= htmlspecialchars($c['nom']) ?></td>
                <td><?= $c['zone'] !== '' ? htmlspecialchars($c['zone']) : '<span class="opacity-40">—</span>' ?></td>
                <td class="text-right"><?= $c['falaises'] ?></td>
                <td class="text-right"><?= $c['acces_velo'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-close.php"; ?>
</body>

</html>
