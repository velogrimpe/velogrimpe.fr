<?php
/**
 * Gabarit des listes de données de l'admin (falaises, détails, accès, train, villes).
 *
 * Le serveur injecte les lignes en JSON dans #page-data ; `js/admin-list.js` les
 * affiche (recherche, filtres, barre d'actions par ligne) d'après `kind`, avec les
 * mêmes briques que « Arrêts et lignes » (js/admin-ui.js).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

/**
 * @param array{
 *   active: string,          clé d'entrée de components/admin-nav.php
 *   kind: string,            clé de config dans js/admin-list.js
 *   title: string,
 *   rows: array,
 *   token: string,
 *   add?: array<int, array{0:string,1:string}>  boutons « + » : [libellé, URL]
 * } $o
 */
function admin_list_page(array $o): void
{
  $admin_active = $o['active'];
  $pageData = ['kind' => $o['kind'], 'rows' => $o['rows'], 'token' => $o['token']];
  ?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= htmlspecialchars($o['title']) ?> (admin) - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/admin-layout-open.php'; ?>
  <main class="w-full grow max-w-(--breakpoint-xl) mx-auto p-4 flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-2xl font-bold"><span class="text-red-900">ADMIN</span> · <?= htmlspecialchars($o['title']) ?>
        <span id="count" class="badge badge-neutral align-middle"></span></h1>
      <div class="flex gap-2">
        <?php foreach ($o['add'] ?? [] as [$label, $href]): ?>
          <a class="btn btn-sm btn-primary text-primary-content" href="<?= htmlspecialchars($href) ?>">+ <?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <input id="search" type="search" class="input input-sm input-bordered w-full max-w-xs" placeholder="Rechercher…" autocomplete="off" />
      <div id="filters" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"></div>
    </div>
    <div class="overflow-auto rounded-md bg-base-100 max-h-[calc(100vh-14rem)]">
      <table class="table table-sm table-pin-rows">
        <thead id="thead"></thead>
        <tbody id="tbody"></tbody>
      </table>
      <p id="empty" class="hidden p-4 text-center opacity-70">Aucun résultat.</p>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/admin-layout-close.php'; ?>
  <script type="application/json" id="page-data"><?= json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script type="module" src="/js/admin-list.js"></script>
</body>

</html>
<?php
}
