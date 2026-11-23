<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
// Modalités d'arrêt : filtres de la liste et dialog de modification
$arret_types_labels = ['ter' => 'TER', 'tgv' => 'TGV', 'bus' => 'Bus', 'tram' => 'Tram', 'metro' => 'Métro', 'telepherique' => 'Téléphérique'];

// Tous les arrêts (y compris supprimés : filtre côté client) et toutes les lignes (sans géométrie).
$arrets = [];
$res = $mysqli->query(
  "SELECT a.id, a.nom, a.commune, a.deleted, a.ter, a.tgv, a.bus, a.tram, a.metro, a.teleph, a.parent_id, a.position_manuelle,
          ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng,
          COALESCE(ch.n, 0) AS nb_enfants,
          COALESCE(li.nb, 0) AS nb_lignes,
          li.ligne_id AS ligne_id,
          COALESCE(ac.n, 0) AS nb_acces
   FROM arrets a
   -- Agrégats pré-calculés une fois (et non en sous-requêtes corrélées par arrêt : O(arrêts × liaisons), ~15 s en prod).
   LEFT JOIN (SELECT parent_id, COUNT(*) AS n FROM arrets WHERE deleted = 0 AND parent_id IS NOT NULL GROUP BY parent_id) ch
     ON ch.parent_id = a.id
   -- Lignes d'un arrêt = les siennes + celles de ses enfants non supprimés.
   LEFT JOIN (
     SELECT owner, COUNT(*) AS nb, MIN(ligne_id) AS ligne_id FROM (
       SELECT arret_id AS owner, ligne_id FROM arrets_lignes_transport
       UNION ALL
       SELECT c.parent_id AS owner, alt.ligne_id FROM arrets_lignes_transport alt
         JOIN arrets c ON c.id = alt.arret_id AND c.deleted = 0 AND c.parent_id IS NOT NULL
     ) x GROUP BY owner
   ) li ON li.owner = a.id
   LEFT JOIN (SELECT arret_id, COUNT(*) AS n FROM acces_falaise GROUP BY arret_id) ac ON ac.arret_id = a.id
   ORDER BY a.nom",
);
// Falaises reliées à chaque arrêt (via acces_falaise), pour l'affichage et la recherche.
$falaises_par_arret = [];
$resF = $mysqli->query(
  "SELECT DISTINCT af.arret_id, f.falaise_nom FROM acces_falaise af
   JOIN falaises f ON f.falaise_id = af.falaise_id ORDER BY f.falaise_nom",
);
while ($r = $resF->fetch_assoc()) $falaises_par_arret[(int) $r["arret_id"]][] = $r["falaise_nom"];
// stop_id GTFS mémorisés par arrêt : rapprochement lors de l'import GTFS d'une ligne (AdminLigneGtfsImport.vue)
$gtfs_refs_par_arret = [];
$resR = $mysqli->query("SELECT arret_id, ref FROM references_externes WHERE source = 'gtfs' ORDER BY id");
while ($r = $resR->fetch_assoc()) $gtfs_refs_par_arret[(int) $r["arret_id"]][] = $r["ref"];

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
    "parent_id" => $row["parent_id"] !== null ? (int) $row["parent_id"] : null, // arrêt enfant : id de son parent
    "nb_enfants" => (int) $row["nb_enfants"], // > 0 : arrêt parent
    "position_manuelle" => (bool) $row["position_manuelle"],
    "falaises" => $falaises_par_arret[(int) $row["id"]] ?? [],
    "gtfsRefs" => $gtfs_refs_par_arret[(int) $row["id"]] ?? [],
  ];
}

$lignes = [];
$res = $mysqli->query(
  "SELECT l.id, l.nom, l.numero, l.operateur, l.operateur_url, l.horaire_url, l.gtfs_url, l.ref, l.couleur,
          (SELECT COUNT(*) FROM arrets_lignes_transport alt WHERE alt.ligne_id = l.id) AS nb_arrets
   FROM lignes_transport l ORDER BY l.nom",
);
while ($row = $res->fetch_assoc()) {
  $lignes[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "numero" => $row["numero"],
    "operateur" => $row["operateur"],
    "operateur_url" => $row["operateur_url"],
    "horaire_url" => $row["horaire_url"],
    "gtfs_url" => $row["gtfs_url"],
    "ref" => $row["ref"],
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
  <?php vite_js("admin-gtfs-import"); ?>
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
    /* Vue liste, panneaux côte à côte : la page tient dans la fenêtre, seuls les tableaux défilent */
    @media (min-width: 1024px) {
      body:has(#view-liste:not(.hidden)) { height: 100dvh; overflow: hidden; }
      body:has(#view-liste:not(.hidden)) main { min-height: 0; }
      #view-liste { flex: 1 1 0; min-height: 0; grid-template-rows: minmax(0, 1fr); }
      #view-liste .vg-panel { display: flex; flex-direction: column; min-height: 0; }
      #view-liste .vg-scroll { flex: 1 1 0; min-height: 0; max-height: none; }
    }
  </style>
</head>

<body class="min-h-screen flex flex-col">
  <?php $admin_active = 'transports'; include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-open.php"; ?>
  <main class="w-full grow max-w-(--breakpoint-xl) mx-auto p-4 flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-2xl font-bold"><span class="text-red-900">ADMIN</span> · Arrêts et lignes</h1>
      <div class="flex gap-2">
        <a class="btn btn-sm btn-primary text-primary-content" href="/ajout/ajout_arret.php?admin=<?= urlencode($token) ?>">+ Arrêt</a>
        <a class="btn btn-sm btn-primary text-primary-content" href="/ajout/ajout_ligne.php?admin=<?= urlencode($token) ?>">+ Ligne</a>
      </div>
    </div>

    <div role="tablist" class="tabs tabs-border">
      <button role="tab" class="tab tab-active" data-view="liste">Liste</button>
      <button role="tab" class="tab" data-view="carte">Carte</button>
    </div>

    <div class="flex flex-row flex-wrap items-center gap-x-4 gap-y-2">
      <input id="search" type="search" class="input input-sm input-bordered w-full max-w-xs"
        placeholder="Rechercher (arrêt, commune, falaise, n°, opérateur, id)…" autocomplete="off" />
      <?php
      // Filtres à choix unique « Tous / option » (cf. admin-arrets-lignes.js) ; data-default = choix initial.
      $filter_groups = [
        'type' => ["Types d'arrêt :", 'local', ['' => 'Tous', 'local' => 'Hors trains'] + $arret_types_labels],
        'statut' => ['Statut :', '', ['' => 'Tous', 'lies' => 'Liés', 'non_lies' => 'Non liés', 'supprimes' => 'Supprimés']],
      ];
      foreach ($filter_groups as $key => [$label, $default, $options]): ?>
        <div class="flex flex-wrap items-center gap-2 text-sm" data-filter-group="<?= $key ?>" data-default="<?= $default ?>">
          <span class="font-semibold"><?= $label ?></span>
          <div class="join">
            <?php foreach ($options as $value => $text): ?>
              <button type="button" class="btn btn-xs join-item btn-outline" data-filter="<?= $key ?>" data-value="<?= $value ?>"><?= $text ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="merge-banner" class="hidden alert alert-info text-sm items-center justify-between"></div>

    <!-- Vue liste -->
    <section id="view-liste" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="vg-panel" style="min-width:0">
        <h2 class="text-lg font-bold mb-2">Arrêts <span id="count-arrets" class="badge badge-neutral"></span></h2>
        <div class="vg-scroll overflow-auto max-h-[70vh] rounded-md bg-base-100">
          <table class="table table-sm table-pin-rows table-fixed">
            <thead><tr><th>Arrêt</th><th style="width:4.5rem">Types</th><th class="text-right" style="width:3.5rem">Lignes</th><th style="width:6rem">Falaises</th><th style="width:11.5rem"></th></tr></thead>
            <tbody id="tbody-arrets"></tbody>
          </table>
        </div>
      </div>
      <div class="vg-panel" style="min-width:0">
        <h2 class="text-lg font-bold mb-2 flex items-center gap-2">Lignes <span id="count-lignes" class="badge badge-neutral"></span>
          <button type="button" class="btn btn-xs btn-circle btn-primary text-primary-content" data-gtfs-ligne="new"
            data-tip="Nouvelle ligne depuis un GTFS" aria-label="Nouvelle ligne depuis un GTFS">+</button></h2>
        <div class="vg-scroll overflow-auto max-h-[70vh] rounded-md bg-base-100">
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

  <!-- Modifier un arrêt : nom et modalités (api/update_arret.php) -->
  <dialog id="dlg-arret" class="modal">
    <form class="modal-box flex flex-col gap-3" method="dialog" id="form-arret">
      <h3 class="font-bold text-lg">Modifier l'arrêt <span id="dlg-arret-id" class="opacity-60"></span></h3>
      <label class="form-control">
        <b class="text-sm">Nom</b>
        <input id="arret-nom" class="input input-sm input-primary w-full" type="text" maxlength="255" required autocomplete="off" />
      </label>
      <div class="form-control">
        <b class="text-sm">Modalités desservies <span class="text-error">(au moins une)</span></b>
        <div class="flex flex-wrap gap-4 mt-1" id="arret-types">
          <?php foreach ($arret_types_labels as $k => $label): ?>
            <label class="flex items-center gap-1 cursor-pointer">
              <input type="checkbox" class="checkbox checkbox-sm checkbox-primary" value="<?= $k ?>" /> <?= $label ?>
            </label>
          <?php endforeach; ?>
        </div>
        <i id="arret-types-note" class="hidden text-xs opacity-70 mt-1">Arrêt parent : ses modalités sont celles de ses arrêts regroupés.</i>
      </div>
      <div id="arret-error" class="alert alert-error text-sm hidden" role="alert"></div>
      <div class="modal-action mt-0">
        <button type="button" class="btn btn-sm" data-dialog-close>Annuler</button>
        <button type="submit" class="btn btn-sm btn-primary">Valider</button>
      </div>
    </form>
  </dialog>

  <!-- Modifier les infos de base d'une ligne (api/update_ligne.php) ; le tracé et les arrêts se mettent à jour par « Import GTFS » -->
  <dialog id="dlg-ligne" class="modal">
    <form class="modal-box max-w-2xl flex flex-col gap-3" method="dialog" id="form-ligne">
      <h3 class="font-bold text-lg">Modifier la ligne <span id="dlg-ligne-id" class="opacity-60"></span></h3>
      <label class="form-control">
        <b class="text-sm">Nom officiel</b>
        <input id="ligne-nom" class="input input-sm input-primary w-full" type="text" required autocomplete="off" />
      </label>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <label class="form-control"><b class="text-sm">Numéro</b>
          <input id="ligne-numero" class="input input-sm input-primary w-full" type="text" maxlength="64" autocomplete="off" /></label>
        <label class="form-control"><b class="text-sm">Couleur (CSS)</b>
          <input id="ligne-couleur" class="input input-sm input-primary w-full" type="text" maxlength="64" placeholder="#123456, gold…" autocomplete="off" /></label>
        <label class="form-control"><b class="text-sm">Référence officielle</b>
          <input id="ligne-ref" class="input input-sm input-primary w-full" type="text" autocomplete="off" /></label>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <label class="form-control"><b class="text-sm">Opérateur</b>
          <input id="ligne-operateur" class="input input-sm input-primary w-full" type="text" maxlength="64" autocomplete="off" /></label>
        <label class="form-control"><b class="text-sm">Site de l'opérateur</b>
          <input id="ligne-operateur_url" class="input input-sm input-primary w-full" type="url" autocomplete="off" /></label>
      </div>
      <label class="form-control"><b class="text-sm">Horaires (URL)</b>
        <input id="ligne-horaire_url" class="input input-sm input-primary w-full" type="url" autocomplete="off" /></label>
      <label class="form-control"><b class="text-sm">Fichier GTFS (URL)</b>
        <input id="ligne-gtfs_url" class="input input-sm input-primary w-full" type="url" autocomplete="off" /></label>
      <div id="ligne-error" class="alert alert-error text-sm hidden" role="alert"></div>
      <div class="modal-action mt-0">
        <button type="button" class="btn btn-sm" data-dialog-close>Annuler</button>
        <button type="submit" class="btn btn-sm btn-primary">Valider</button>
      </div>
    </form>
  </dialog>

  <!-- Import GTFS d'une ligne (Vue : frontend/src/components/admin/AdminLigneGtfsImport.vue) -->
  <div id="vue-gtfs-import"></div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-close.php"; ?>
  <script type="application/json" id="page-data"><?= json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script type="module" src="/js/admin-arrets-lignes.js"></script>
</body>

</html>
