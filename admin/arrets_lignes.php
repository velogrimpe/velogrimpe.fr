<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

// Tous les arrêts (y compris supprimés : filtre côté client) et toutes les lignes (sans géométrie).
$arrets = [];
$res = $mysqli->query(
  "SELECT a.id, a.nom, a.commune, a.deleted, a.ter, a.tgv, a.bus, a.tram, a.teleph,
          ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng,
          (SELECT COUNT(*) FROM arrets_lignes_transport alt WHERE alt.arret_id = a.id) AS nb_lignes,
          (SELECT MIN(alt.ligne_id) FROM arrets_lignes_transport alt WHERE alt.arret_id = a.id) AS ligne_id,
          (SELECT COUNT(*) FROM acces_falaise af WHERE af.arret_id = a.id) AS nb_acces
   FROM arrets a ORDER BY a.nom",
);
// Falaises reliées à chaque arrêt (via acces_falaise), pour l'affichage et la recherche.
$falaises_par_arret = [];
$resF = $mysqli->query(
  "SELECT DISTINCT af.arret_id, f.falaise_nom FROM acces_falaise af
   JOIN falaises f ON f.falaise_id = af.falaise_id ORDER BY f.falaise_nom",
);
while ($r = $resF->fetch_assoc()) $falaises_par_arret[(int) $r["arret_id"]][] = $r["falaise_nom"];

while ($row = $res->fetch_assoc()) {
  $arrets[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "commune" => $row["commune"],
    "deleted" => (bool) $row["deleted"],
    "types" => arret_types($row),
    "lat" => (float) $row["lat"],
    "lng" => (float) $row["lng"],
    "nb_lignes" => (int) $row["nb_lignes"],
    "ligne_id" => $row["ligne_id"] !== null ? (int) $row["ligne_id"] : null, // une des lignes desservies (couleur du cercle)
    "nb_acces" => (int) $row["nb_acces"],
    "falaises" => $falaises_par_arret[(int) $row["id"]] ?? [],
  ];
}

$lignes = [];
$res = $mysqli->query(
  "SELECT l.id, l.nom, l.numero, l.operateur, l.couleur,
          (SELECT COUNT(*) FROM arrets_lignes_transport alt WHERE alt.ligne_id = l.id) AS nb_arrets
   FROM lignes_transport l ORDER BY l.nom",
);
while ($row = $res->fetch_assoc()) {
  $lignes[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "numero" => $row["numero"],
    "operateur" => $row["operateur"],
    "couleur" => $row["couleur"],
    "nb_arrets" => (int) $row["nb_arrets"],
  ];
}

// Falaises (repères sur la carte + liaison à un arrêt)
$falaises = [];
$res = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_latlng FROM falaises WHERE falaise_latlng LIKE '%,%' ORDER BY falaise_nom");
while ($row = $res->fetch_assoc()) {
  [$lat, $lng] = array_map("floatval", explode(",", $row["falaise_latlng"]));
  $falaises[] = ["id" => (int) $row["falaise_id"], "nom" => $row["falaise_nom"], "lat" => $lat, "lng" => $lng];
}

$pageData = [
  "arrets" => $arrets,
  "lignes" => $lignes,
  "falaises" => $falaises,
  // Contributeur des liaisons arrêt ↔ falaise faites depuis cette page (requis par api/link_arret_falaise.php)
  "contrib" => ["nom" => "Admin", "email" => (string) ($config["contact_mail"] ?? "")],
  "token" => $token,
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Arrêts et lignes (admin) - Vélogrimpe.fr</title>
  <?php map_bundle_js("map-maplibre"); ?>
  <?php map_bundle_css("map-maplibre"); ?>
  <?php vite_css("main"); ?>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <style>
    /* Noms d'arrêts : texte noir à halo blanc, sans cadre (comme carte.php) */
    .vg-arret-label { pointer-events: none; color: #000; font-size: 10px; font-weight: 700; white-space: nowrap;
      text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff, 0 0 3px #fff; }
    .vg-marker { cursor: pointer; }
    .vg-arret-icon { z-index: 1; }
    .vg-hover-popup .maplibregl-popup-content { padding: 2px 6px; font-size: 12px; border-radius: 4px; }
    .maplibregl-ctrl-top-left, .maplibregl-ctrl-top-right, .maplibregl-ctrl-bottom-right { z-index: 10; }
    /* Les popups doivent passer devant les marqueurs DOM d'arrêts (z-index 1) */
    .maplibregl-popup { z-index: 5; }
    .maplibregl-popup-content { padding: 13px 24px 13px 20px; border-radius: 12px; box-shadow: 0 3px 14px rgba(0,0,0,.4);
      color: #333; line-height: 1.3; text-align: left; font-size: 13px; }
    .maplibregl-popup-close-button { width: 24px; height: 24px; font: 16px/24px Tahoma, Verdana, sans-serif; color: #757575; }
    .maplibregl-popup-close-button:hover { background: transparent; color: #585858; }
    /* Sélecteur de fond de carte */
    .vg-layer-switcher { background: #fff; border-radius: 4px; font-size: 13px; max-width: 260px; }
    .vg-layer-switcher summary { list-style: none; display: flex; align-items: center; gap: 6px; padding: 5px; cursor: pointer; color: #333; }
    .vg-layer-switcher summary::-webkit-details-marker { display: none; }
    .vg-layer-switcher details:not([open]) .vg-layer-switcher-label { display: none; }
    .vg-layer-switcher details[open] > div { padding: 0 8px 6px; }
    .vg-layer-switcher label { display: block; padding: 2px 0; cursor: pointer; }
    /* Recherche de lieu */
    .vg-search-ctl { position: relative; }
    .vg-search-ctl input { width: 190px; height: 30px; padding: 0 .6rem; border: 0; border-radius: 4px; background: #fff;
      box-shadow: 0 0 0 2px rgba(0,0,0,.1); font-size: .8rem; color: #222; }
    .vg-search-results { position: absolute; left: 0; right: 0; top: 100%; margin-top: 4px; max-height: 13rem; overflow-y: auto;
      background: #fff; color: #222; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.25); font-size: .75rem; }
    .vg-search-results button { display: block; width: 100%; padding: .375rem .5rem; text-align: left; white-space: nowrap;
      overflow: hidden; text-overflow: ellipsis; background: transparent; border: 0; cursor: pointer; }
    .vg-search-results button:hover { background: rgba(0,0,0,.08); }
  </style>
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-xl) mx-auto p-4 flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-2xl font-bold"><span class="text-red-900">ADMIN</span> · Arrêts et lignes</h1>
      <div class="flex gap-2">
        <a class="btn btn-sm btn-primary text-primary-content" href="/ajout/ajout_arret.php?admin=<?= urlencode($token) ?>">+ Arrêt</a>
        <a class="btn btn-sm btn-primary text-primary-content" href="/ajout/ajout_ligne.php?admin=<?= urlencode($token) ?>">+ Ligne</a>
        <a class="btn btn-sm btn-ghost" href="/admin/">← Admin</a>
      </div>
    </div>

    <div role="tablist" class="tabs tabs-border">
      <button role="tab" class="tab tab-active" data-view="liste">Liste</button>
      <button role="tab" class="tab" data-view="carte">Carte</button>
    </div>

    <div class="flex flex-col items-start gap-2">
      <input id="search" type="search" class="input input-sm input-bordered w-full max-w-xs"
        placeholder="Rechercher (arrêt, commune, falaise, n°, opérateur, id)…" autocomplete="off" />
      <div id="type-filters" class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-semibold">Types d'arrêt :</span>
        <?php foreach (['ter' => 'TER', 'tgv' => 'TGV', 'bus' => 'Bus', 'tram' => 'Tram', 'telepherique' => 'Téléphérique'] as $k => $label): ?>
          <label class="flex items-center gap-1 cursor-pointer">
            <input type="checkbox" class="checkbox checkbox-xs" data-type="<?= $k ?>"
              <?= in_array($k, ['ter', 'tgv'], true) ? '' : 'checked' ?> />
            <?= $label ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div id="other-filters" class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-semibold">Autres filtres :</span>
        <label class="flex items-center gap-1 cursor-pointer">
          <input type="checkbox" class="checkbox checkbox-xs" id="only-falaises" /> Reliés à des falaises
        </label>
        <label class="flex items-center gap-1 cursor-pointer">
          <input type="checkbox" class="checkbox checkbox-xs" id="show-deleted" /> Supprimés
        </label>
      </div>
    </div>

    <div id="merge-banner" class="hidden alert alert-info text-sm items-center justify-between"></div>

    <!-- Vue liste -->
    <section id="view-liste" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <h2 class="text-lg font-bold mb-2">Arrêts <span id="count-arrets" class="badge badge-neutral"></span></h2>
        <div class="overflow-auto max-h-[70vh] rounded-md bg-base-100">
          <table class="table table-sm table-pin-rows">
            <thead><tr><th>Arrêt</th><th>Types</th><th class="text-right">Lignes</th><th>Falaises</th><th></th></tr></thead>
            <tbody id="tbody-arrets"></tbody>
          </table>
        </div>
      </div>
      <div>
        <h2 class="text-lg font-bold mb-2">Lignes <span id="count-lignes" class="badge badge-neutral"></span></h2>
        <div class="overflow-auto max-h-[70vh] rounded-md bg-base-100">
          <table class="table table-sm table-pin-rows">
            <thead><tr><th>Ligne</th><th>Opérateur</th><th class="text-right">Arrêts</th><th></th></tr></thead>
            <tbody id="tbody-lignes"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Vue carte -->
    <section id="view-carte" class="hidden flex-col gap-2">
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" id="layer-arrets" class="checkbox checkbox-xs" checked /> Arrêts</label>
        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" id="layer-lignes" class="checkbox checkbox-xs" checked /> Lignes</label>
        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" id="layer-falaises" class="checkbox checkbox-xs" checked /> Falaises</label>
        <span id="map-status" class="text-base-content/60"></span>
      </div>
      <div id="admin-map" class="w-full h-[70vh] rounded-md"></div>
    </section>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <script type="application/json" id="page-data"><?= json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script type="module" src="/js/admin-arrets-lignes.js"></script>
</body>

</html>
