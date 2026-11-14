<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/database/velogrimpe.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/vite.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/map-bundle.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/arrets_lib.php";
$config = require $_SERVER["DOCUMENT_ROOT"] . "/../config.php";

// Formulaire ouvert à tous (lignes BTT, Lot 7) ; seule la suppression exige le
// jeton admin (Bearer, cf. api/delete_ligne.php) : le bouton n'apparaît que
// si ?admin= est valide.
$admin = hash_equals((string) $config["admin_token"], (string) ($_GET["admin"] ?? ""));
$ligne_id = (int) ($_GET["ligne_id"] ?? 0) ?: null;

// Falaises (repères sur la carte : point vert puis icône, non cliquables pour le tracé)
$falaises = [];
$res = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_latlng FROM falaises WHERE falaise_latlng LIKE '%,%' ORDER BY falaise_nom");
while ($row = $res->fetch_assoc()) {
  [$lat, $lng] = array_map("floatval", explode(",", $row["falaise_latlng"]));
  $falaises[] = ["id" => (int) $row["falaise_id"], "nom" => $row["falaise_nom"], "lat" => $lat, "lng" => $lng];
}

// Arrêts « feuilles » (carte + autocomplete + popup « lier à la ligne » + rapprochement GTFS) : orphelins et enfants,
// jamais les parents (une ligne dessert les arrêts enfants). `gtfsRefs` : stop_id GTFS déjà mémorisés sur l'arrêt.
$gtfs_refs_par_arret = [];
$resR = $mysqli->query("SELECT arret_id, ref FROM references_externes WHERE source = 'gtfs' ORDER BY id");
while ($r = $resR->fetch_assoc()) $gtfs_refs_par_arret[(int) $r["arret_id"]][] = $r["ref"];
$arrets = [];
$res = $mysqli->query(
  "SELECT a.id, a.nom, a.ter, a.tgv, a.bus, a.tram, a.teleph, ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng
   FROM arrets a
   WHERE a.deleted = 0 AND NOT EXISTS (SELECT 1 FROM arrets c WHERE c.parent_id = a.id AND c.deleted = 0)
   ORDER BY a.nom",
);
while ($row = $res->fetch_assoc()) {
  $arrets[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "types" => arret_types($row),
    "lat" => (float) $row["lat"],
    "lng" => (float) $row["lng"],
    "gtfsRefs" => $gtfs_refs_par_arret[(int) $row["id"]] ?? [],
  ];
}

// Lignes existantes (sélecteur « modifier une ligne »)
$lignes = [];
$res = $mysqli->query("SELECT id, nom, numero FROM lignes_transport ORDER BY nom");
while ($row = $res->fetch_assoc()) {
  $lignes[] = ["id" => (int) $row["id"], "label" => trim(($row["numero"] ? $row["numero"] . " — " : "") . $row["nom"])];
}
$adminQuery = $admin ? "admin=" . urlencode($config["admin_token"]) : "";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= $ligne_id ? "Modifier" : "Ajouter" ?> une ligne - Vélogrimpe.fr</title>
  <?php map_bundle_js("map"); ?>
  <?php map_bundle_css("map"); ?>
  <?php vite_css("main"); ?>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
  <?php vite_css("ajout-ligne"); ?>
  <style>
    /* Arrêt relié à la ligne : fond et bordure vert Vélogrimpe (couleur primaire du thème) ;
       !important car le fond (par type d'arrêt) est posé en style inline. */
    .vg-ligne-arret.linked-arret > div {
      background: var(--color-primary) !important;
    }

    /* Pendant le tracé à la main, les marqueurs (arrêts, falaises) laissent passer les clics :
       un clic sur un arrêt pose un sommet (accroché à l'arrêt) au lieu d'ouvrir son popup. */
    .vg-drawing .leaflet-marker-icon:not(.marker-icon) {
      pointer-events: none !important;
    }
  </style>
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER["DOCUMENT_ROOT"] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4 prose-pre:my-0">
    <h1 class="text-4xl font-bold text-wrap text-center">
      <?= $ligne_id ? "Modifier" : "Ajouter" ?> une ligne<?php if ($admin): ?><span class='text-red-900'> (version admin)</span><?php endif; ?>
    </h1>

    <div id="saved-alert" role="alert" class="alert alert-success not-prose hidden">Ligne enregistrée.</div>

    <div class="flex flex-col gap-2 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
      <label class="form-control not-prose" for="select-ligne">
        <b class="text-sm">Modifier une ligne existante</b>
        <select id="select-ligne" class="select select-primary select-sm w-full"
          onchange="window.location.href = '/ajout/ajout_ligne.php?<?= $adminQuery ?>' + (this.value ? '<?= $admin ? "&" : "" ?>ligne_id=' + this.value : '')">
          <option value="">Nouvelle ligne…</option>
          <?php foreach ($lignes as $l): ?>
            <option value="<?= $l["id"] ?>" <?= $l["id"] === $ligne_id ? "selected" : "" ?>>
              <?= htmlspecialchars($l["label"], ENT_QUOTES, "UTF-8") ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <i class="text-sm">Choisir une ligne recharge la page : les saisies en cours seront perdues.</i>
    </div>

    <form id="form" class="flex flex-col gap-4" onsubmit="return false">
      <input type="hidden" id="ligne_id" value="<?= $ligne_id ?? "" ?>" />
      <input type="hidden" id="ligne_geometry" value="" />
      <input type="hidden" id="ligne_arret_ids" value="" />
      <input type="hidden" id="ligne_nouveaux_arrets" value="" />
      <input type="hidden" id="ligne_gtfs_refs" value="" />

      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <span class="px-2 text-primary italic">Import GTFS</span>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-2 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs not-prose">
        <label class="form-control" for="gtfs-file">
          <b class="text-sm">Archive GTFS (.zip)</b>
          <input class="file-input file-input-sm file-input-primary w-full" type="file" id="gtfs-file"
            accept=".zip,application/zip" />
        </label>
        <div id="gtfs-error" class="alert alert-error text-sm hidden" role="alert"></div>
        <div id="gtfs-choose" class="hidden flex flex-col gap-2">
          <label class="form-control" for="gtfs-routes">
            <b class="text-sm">Ligne à importer</b>
            <select id="gtfs-routes" class="select select-primary select-sm w-full"></select>
          </label>
          <button type="button" id="gtfs-import" class="btn btn-sm btn-primary">Importer cette ligne</button>
          <?php if ($ligne_id): ?>
            <button type="button" id="gtfs-compare" class="btn btn-sm btn-outline btn-primary" disabled>Comparer les arrêts avec ceux de la ligne</button>
          <?php endif; ?>
        </div>
        <div id="vue-gtfs-diff"></div>
        <i class="text-sm opacity-70">« Importer cette ligne » remplit les champs et la géométrie (shapes.txt).
          Les boutons « Montrer / Importer les arrêts », au-dessus de la carte, s'activent une fois une ligne choisie.</i>
      </div>

      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <span class="px-2 text-primary italic">Ligne</span>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="ligne_nom">
          <b>Nom officiel</b>
          <input class="input input-primary input-sm w-full" type="text" id="ligne_nom" required autocomplete="off"
            placeholder="ex: Chambéry - Saint-Alban de Montbel">
        </label>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
          <label class="form-control" for="ligne_numero">
            <b class="text-sm">Numéro</b>
            <input class="input input-primary input-sm w-full" type="text" id="ligne_numero" maxlength="64"
              placeholder="ex: S01" autocomplete="off">
          </label>
          <label class="form-control" for="ligne_couleur">
            <b class="text-sm">Couleur (CSS)</b>
            <input class="input input-primary input-sm w-full" type="text" id="ligne_couleur" maxlength="64"
              placeholder="#123456, gold…" autocomplete="off">
          </label>
          <label class="form-control" for="ligne_ref">
            <b class="text-sm">Référence officielle</b>
            <input class="input input-primary input-sm w-full" type="text" id="ligne_ref" autocomplete="off">
          </label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <label class="form-control" for="ligne_operateur">
            <b class="text-sm">Opérateur</b>
            <input class="input input-primary input-sm w-full" type="text" id="ligne_operateur" maxlength="64"
              autocomplete="off">
          </label>
          <label class="form-control" for="ligne_operateur_url">
            <b class="text-sm">Site de l'opérateur</b>
            <input class="input input-primary input-sm w-full" type="url" id="ligne_operateur_url" autocomplete="off">
          </label>
        </div>
        <label class="form-control" for="ligne_horaire_url">
          <b class="text-sm">Horaires (URL)</b>
          <input class="input input-primary input-sm w-full" type="url" id="ligne_horaire_url" autocomplete="off">
        </label>
      </div>

      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <span class="px-2 text-primary italic">Tracé et arrêts</span>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-2 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col gap-2 not-prose">
          <!-- Tracé -->
          <div class="flex items-center gap-2">
            <span class="text-sm font-semibold w-14">Tracé</span>
            <button type="button" id="btn-draw" class="btn btn-sm btn-outline btn-primary" aria-pressed="false"
              title="Tracer à la main" aria-label="Tracer à la main"><svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#plus"></use></svg><span class="text-xs">Tracer</span></button>
            <button type="button" id="btn-edit-geometry" class="btn btn-sm btn-outline btn-primary" aria-pressed="false"
              title="Modifier le tracé" aria-label="Modifier le tracé" disabled><svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#pencil"></use></svg><span class="text-xs">Modifier</span></button>
            <button type="button" id="btn-clear-geometry" class="btn btn-sm btn-ghost text-error"
              title="Effacer le tracé" aria-label="Effacer le tracé"><svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#trash"></use></svg><span class="text-xs">Effacer</span></button>
          </div>
          <!-- Arrêts de la ligne GTFS choisie (actifs une fois la ligne choisie) -->
          <div class="flex items-center gap-2">
            <span class="text-sm font-semibold w-14">Arrêts</span>
            <button type="button" id="gtfs-show-stops" class="btn btn-sm btn-outline btn-primary"
              title="Montrer les arrêts du GTFS" aria-label="Montrer les arrêts du GTFS" disabled><svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#eye"></use></svg><span class="text-xs">Voir</span></button>
            <button type="button" id="gtfs-import-stops" class="btn btn-sm btn-outline btn-primary"
              title="Importer les arrêts du GTFS" aria-label="Importer les arrêts du GTFS" disabled><svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#file-upload"></use></svg><span class="text-xs">Importer</span></button>
          </div>
        </div>
        <div id="ligne-map" class="w-full h-[36rem] rounded-lg relative not-prose"></div>
        <i class="text-slate-400 text-sm">Les tracés posés à la main s'ajoutent à la géométrie ; ils s'accrochent aux
          arrêts existants, et passer par un arrêt le lie à la ligne. Le crayon permet de déplacer
          (ou supprimer par clic droit) les sommets.
          L'œil affiche les arrêts du GTFS en suggestions, à ajouter un par un ;
          La flèche d'import les ajoute tous (retirables). Un arrêt GTFS à moins de 50 m d'un arrêt existant de même type (100 m si le nom est identique ; un arrêt de bus n'est jamais rapproché d'une gare)
          reprend cet arrêt s'il n'est pas déjà suivi sous une autre référence GTFS ; sinon (autre quai ou sens) il est créé et regroupé avec lui sous un arrêt parent. Les arrêts du GTFS
          d'une même station, ou de même nom à moins de 200 m, sont regroupés de la même façon. Tout est créé à l'envoi du formulaire — rien n'est écrit avant.
          Cliquez un arrêt pour le lier à la ligne.</i>

        <div id="vue-arrets-lies"></div>
      </div>

      <div class="flex gap-2 not-prose">
        <button type="submit" class="btn btn-primary grow"><?= $ligne_id ? "Modifier" : "Ajouter" ?> la ligne</button>
        <?php if ($ligne_id && $admin): ?>
          <button type="button" id="btn-delete" class="btn btn-error btn-outline">Supprimer</button>
        <?php endif; ?>
      </div>
    </form>
  </main>
  <?php include $_SERVER["DOCUMENT_ROOT"] . "/components/footer.php"; ?>

  <script type="module">
    import { createAjoutMap } from '/js/components/map/ajout-map.js';
    import { arretDivIcon, arretDotIcon, arretEstGare } from '/js/components/map/arret-icon.js';

    const arrets = <?= json_encode($arrets) ?>;
    const falaises = <?= json_encode($falaises) ?>;
    // Jusqu'à ce zoom (inclus), les gares ter/tgv et les falaises sont de simples points
    const DOT_MAX_ZOOM = 12;
    // Accrochage aux arrêts (tracé / édition) : seulement quand ils sont distinguables à l'écran,
    // sinon à z6 presque chaque clic tombe sur une gare voisine.
    const SNAP_MIN_ZOOM = 13;
    const SNAP_PX = 14;
    const snapOptions = () => ({ snappable: map.getZoom() >= SNAP_MIN_ZOOM, snapDistance: SNAP_PX });
    const dotMode = () => map.getZoom() <= DOT_MAX_ZOOM;
    const DEFAULT_COLOR = '#2563eb';
    const { map } = createAjoutMap('ligne-map');
    window.ligneMap = map;

    const escapeHtml = (s) => String(s ?? '')
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
    const geometryInput = document.getElementById('ligne_geometry');
    const colorInput = document.getElementById('ligne_couleur');

    // --- Géométrie de la ligne (MultiLineString GeoJSON dans #ligne_geometry) ---
    // Deux couches superposées, comme « Voir la ligne » : halo blanc dessous, trait coloré
    // dessus. Seul le trait est éditable (geoman) ; le halo le suit.
    let haloLayer = null;
    let geometryLine = null;
    const editBtn = document.getElementById('btn-edit-geometry');
    function lineColor() {
      const c = colorInput.value.trim();
      return c && CSS.supports('color', c) ? c : DEFAULT_COLOR;
    }
    function currentGeometry() {
      return geometryInput.value ? JSON.parse(geometryInput.value) : null;
    }
    function removeGeometryLayers() {
      geometryLine?.pm.disable();
      [haloLayer, geometryLine].forEach((l) => l && map.removeLayer(l));
      haloLayer = geometryLine = null;
    }
    function renderGeometry(fit) {
      setEditing(false);
      removeGeometryLayers();
      const g = currentGeometry();
      editBtn.disabled = !(g && g.coordinates.length);
      if (editBtn.disabled) return;
      // GeoJSON [lng, lat] -> Leaflet [lat, lng]
      const latlngs = g.coordinates.map((line) => line.map(([lng, lat]) => [lat, lng]));
      const common = { lineCap: 'round', lineJoin: 'round' };
      haloLayer = L.polyline(latlngs, {
        ...common, color: '#fff', weight: 10, opacity: 0.95, interactive: false, pmIgnore: true, className: 'vg-ligne-halo',
      }).addTo(map);
      geometryLine = L.polyline(latlngs, {
        ...common, color: lineColor(), weight: 4, opacity: 0.9, className: 'vg-ligne-geometry',
      }).addTo(map);
      // Édition : le halo suit, la géométrie du formulaire aussi
      geometryLine.on('pm:edit pm:vertexremoved', syncGeometryFromLine);
      geometryLine.on('pm:markerdragend', (e) => linkArretNear(e.markerEvent?.target?.getLatLng?.()));
      if (fit) map.fitBounds(geometryLine.getBounds(), { padding: [30, 30] });
    }
    // Relit le tracé édité : parties à moins de 2 sommets écartées, halo et champ caché mis à jour
    function syncGeometryFromLine() {
      if (!geometryLine) return;
      const lines = geometryLine.getLatLngs()
        .map((line) => line.map((ll) => [Number(ll.lng.toFixed(7)), Number(ll.lat.toFixed(7))]))
        .filter((line) => line.length >= 2);
      geometryInput.value = lines.length ? JSON.stringify({ type: 'MultiLineString', coordinates: lines }) : '';
      haloLayer?.setLatLngs(geometryLine.getLatLngs());
    }
    function setGeometry(g, fit) {
      geometryInput.value = g && g.coordinates.length ? JSON.stringify(g) : '';
      renderGeometry(fit);
    }
    // Remplacement de la géométrie par le formulaire (import GTFS / shapes.txt / prefill)
    document.addEventListener('velogrimpe:ligne-geometry-set', (e) => setGeometry(e.detail?.geometry ?? null, true));
    colorInput.addEventListener('input', () => geometryLine?.setStyle({ color: lineColor() }));
    document.getElementById('btn-clear-geometry').addEventListener('click', () => setGeometry(null, false));

    // --- Modifier le tracé (geoman, mode édition de la couche) : déplacer / supprimer (clic droit) les sommets ---
    let editing = false;
    function setEditing(on) {
      on = on && !!geometryLine;
      if (on) setDrawing(false);
      editing = on;
      editBtn.setAttribute('aria-pressed', String(on));
      editBtn.classList.toggle('btn-active', on);
      if (!geometryLine) return;
      if (on) {
        geometryLine.pm.enable({
          allowSelfIntersection: true, ...snapOptions(), removeLayerBelowMinVertexCount: false,
        });
      } else {
        geometryLine.pm.disable();
      }
    }
    editBtn.addEventListener('click', () => setEditing(!editing));

    // --- Arrêts (marqueurs + liaison à la ligne) ---
    const linked = new Set();
    const linkedInput = document.getElementById('ligne_arret_ids');
    const markers = new Map();
    // Gares ter/tgv : point jusqu'au zoom 12 ; autres arrêts : toujours l'icône
    function iconFor(a, isLinked, extra = '') {
      const extraClassName = 'vg-ligne-arret' + (isLinked ? ' linked-arret' : '') + extra;
      return dotMode() && arretEstGare(a.types)
        ? arretDotIcon(a.types, 12, { extraClassName })
        : arretDivIcon(a.types, 24, { extraClassName });
    }
    // Rafraîchit les icônes au franchissement du seuil de zoom
    let wasDot = null;
    function refreshIconsForZoom() {
      const dot = dotMode();
      if (dot === wasDot) return;
      wasDot = dot;
      for (const { marker, arret } of markers.values()) {
        marker.setIcon(iconFor(arret, linked.has(arret.id)));
        marker.getElement()?.setAttribute('data-arret-id', String(arret.id));
      }
      for (const n of nouveaux.values()) {
        n.marker.setIcon(iconFor({ types: n.types }, true, ' vg-ligne-arret-new'));
      }
      falaiseMarkers.forEach((m) => m.setIcon(falaiseIcon()));
    }
    function setArretLinked(id, isLinked) {
      id = Number(id);
      if (!markers.has(id)) return;
      if (isLinked) linked.add(id); else linked.delete(id);
      const { marker, arret } = markers.get(id);
      marker.setIcon(iconFor(arret, isLinked));
      marker.getElement()?.setAttribute('data-arret-id', String(id));
      notifyArrets();
    }
    arrets.forEach((a) => {
      const marker = L.marker([a.lat, a.lng], { icon: iconFor(a, false) }).addTo(map);
      marker.on('add', () => marker.getElement()?.setAttribute('data-arret-id', String(a.id)));
      marker.getElement()?.setAttribute('data-arret-id', String(a.id));
      marker.bindPopup('');
      marker.on('popupopen', () => {
        const isLinked = linked.has(a.id);
        const div = document.createElement('div');
        div.className = 'flex flex-col gap-1 text-base-content';
        div.innerHTML = `<b>${escapeHtml(a.nom)}</b>`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs ' + (isLinked ? 'btn-error' : 'btn-primary');
        btn.textContent = isLinked ? 'Retirer de la ligne' : 'Lier à la ligne';
        btn.addEventListener('click', () => { setArretLinked(a.id, !linked.has(a.id)); marker.closePopup(); });
        div.appendChild(btn);
        marker.setPopupContent(div);
      });
      markers.set(a.id, { marker, arret: a });
    });
    // --- Arrêts à créer (import GTFS) : n'existent pas encore en base, créés à l'envoi ---
    const nouveaux = new Map(); // key -> { key, nom, lat, lng, types, marker }
    const nouveauxInput = document.getElementById('ligne_nouveaux_arrets');
    // Identifiants GTFS des arrêts existants liés : arret_id -> { stop_id, nom } (écrits à l'envoi)
    const gtfsRefs = new Map();
    const gtfsRefsInput = document.getElementById('ligne_gtfs_refs');
    // `pos` {lat, lng} (arrêt déjà suivi sous cette ref) : l'arrêt est repositionné à l'envoi (sauf position manuelle)
    function recordGtfsRef(arretId, stopId, nom, pos) {
      gtfsRefs.set(Number(arretId), { stop_id: String(stopId), nom, ...(pos ? { lat: pos.lat, lng: pos.lng } : {}) });
      notifyArrets();
    }
    const GTFS_KEY = 'gtfs:';
    function notifyArrets() {
      linkedInput.value = Array.from(linked).join(',');
      nouveauxInput.value = nouveaux.size
        ? JSON.stringify(Array.from(nouveaux.values()).map(({ key, nom, lat, lng, types, extra }) => ({
          nom, lat, lng, types, ...(key.startsWith(GTFS_KEY) ? { stop_id: key.slice(GTFS_KEY.length) } : {}), ...extra,
        })))
        : '';
      const refs = Array.from(gtfsRefs, ([arret_id, r]) => ({ arret_id, ...r })).filter((r) => linked.has(r.arret_id));
      gtfsRefsInput.value = refs.length ? JSON.stringify(refs) : '';
      document.dispatchEvent(new CustomEvent('velogrimpe:ligne-arrets-changed', {
        detail: {
          ids: Array.from(linked),
          nouveaux: Array.from(nouveaux.values()).map(({ key, nom }) => ({ key, nom })),
        },
      }));
    }
    // `extra` : regroupement (regrouper_avec / groupe / groupe_stop_id), transmis tel quel à add_ligne.php
    function addNouvelArret(key, nom, lat, lng, types, extra = {}) {
      if (nouveaux.has(key)) return;
      const arret = { nom, types };
      const marker = L.marker([lat, lng], {
        icon: iconFor({ types }, true, ' vg-ligne-arret-new'),
      }).addTo(map);
      marker.bindPopup('');
      marker.on('popupopen', () => {
        const div = document.createElement('div');
        div.className = 'flex flex-col gap-1 text-base-content';
        div.innerHTML = `<b>${escapeHtml(nom)}</b><i class="text-xs">Nouvel arrêt, créé à l'envoi du formulaire</i>`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs btn-error';
        btn.textContent = 'Retirer de la ligne';
        btn.addEventListener('click', () => { removeNouvelArret(key); });
        div.appendChild(btn);
        marker.setPopupContent(div);
      });
      nouveaux.set(key, { key, nom, lat, lng, types: arret.types, extra, marker });
      notifyArrets();
    }
    function removeNouvelArret(key) {
      const n = nouveaux.get(key);
      if (!n) return;
      map.removeLayer(n.marker);
      nouveaux.delete(key);
      notifyArrets();
    }

    // --- Arrêts du GTFS : le plan d'import (rapprochement, regroupements) est calculé par ajout-ligne.ts ; ici le rendu ---
    let gtfsLayer = null;
    window.ligneClearGtfsStops = () => {
      if (gtfsLayer) { map.removeLayer(gtfsLayer); gtfsLayer = null; }
      window.ligneGtfsStops = [];
    };
    window.ligneFitStops = (stops) => {
      if (stops.length) map.fitBounds(L.latLngBounds(stops.map((s) => [s.lat, s.lng])), { padding: [30, 30] });
    };
    // items : [{ stop, label }] (label : ce que deviendra l'arrêt) ; onAdd(item) : bouton « Ajouter à la ligne »
    window.ligneShowGtfsStops = (items, onAdd) => {
      window.ligneClearGtfsStops();
      window.ligneGtfsStops = items.map((i) => i.stop);
      gtfsLayer = L.layerGroup().addTo(map);
      items.forEach((item) => {
        const s = item.stop;
        const m = L.circleMarker([s.lat, s.lng], {
          radius: 7, weight: 2, color: '#2563eb', fillColor: '#60a5fa', fillOpacity: 0.7, className: 'vg-gtfs-stop', pmIgnore: true,
        }).addTo(gtfsLayer);
        m.bindPopup('');
        m.on('popupopen', () => {
          const div = document.createElement('div');
          div.className = 'flex flex-col gap-1 text-base-content';
          div.innerHTML = `<div class="text-xs opacity-70">Arrêt du GTFS</div><b>${escapeHtml(s.nom)}</b>` +
            `<div class="text-xs">${escapeHtml(item.label)}</div>`;
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn-xs btn-primary mt-1';
          btn.textContent = 'Ajouter à la ligne';
          btn.addEventListener('click', () => { onAdd(item); m.closePopup(); });
          div.appendChild(btn);
          m.setPopupContent(div);
        });
      });
      window.ligneFitStops(window.ligneGtfsStops);
    };
    window.ligneRemoveNouvelArret = removeNouvelArret;
    window.ligneAddNouvelArret = addNouvelArret;
    window.ligneRecordGtfsRef = recordGtfsRef;

    // --- Falaises : repères (point vert jusqu'au zoom 12, puis icône de falaise) ---
    const falaiseIcon = () => dotMode()
      ? L.divIcon({
        className: 'vg-ligne-falaise-dot',
        html: '<div style="width:10px;height:10px;background:#16a34a;" class="rounded-full border-2 border-white shadow"></div>',
        iconSize: [10, 10],
        iconAnchor: [5, 5],
        tooltipAnchor: [5, 0],
      })
      : L.icon({
        iconUrl: '/images/map/icone_falaise_carte.png',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        tooltipAnchor: [11, 0],
        className: 'vg-ligne-falaise-icon',
      });
    const falaiseMarkers = falaises.map((f) => {
      // pmIgnore : pas d'accrochage geoman sur les falaises (seuls les arrêts accrochent)
      const m = L.marker([f.lat, f.lng], { icon: falaiseIcon(), pmIgnore: true, zIndexOffset: -500 }).addTo(map);
      m.bindTooltip(escapeHtml(f.nom), { direction: 'right', className: 'p-px' });
      return m;
    });
    window.ligneFalaises = falaises;
    map.on('zoomend', refreshIconsForZoom);
    refreshIconsForZoom();

    // Pont vers le script Vue (ajout-ligne.ts) et les tests
    window.ligneArrets = arrets;
    window.ligneSetArretLinked = setArretLinked;
    window.ligneFocusArret = (id) => {
      const m = markers.get(Number(id))?.marker;
      if (m) map.setView(m.getLatLng(), 16);
    };

    // --- Tracé à la main (leaflet-geoman, lignes directes) ---
    const drawBtn = document.getElementById('btn-draw');
    function setDrawing(on) {
      drawBtn.setAttribute('aria-pressed', String(on));
      drawBtn.classList.toggle('btn-active', on);
      map.getContainer().classList.toggle('vg-drawing', on);
      if (on) {
        if (editing) setEditing(false);
        map.pm.enableDraw('Line', { ...snapOptions(), tooltips: true });
      } else {
        map.pm.disableDraw();
      }
    }
    // L'accrochage suit le zoom en direct, y compris pendant un tracé ou une édition
    // (options mutées en place : setOptions() relancerait le tracé et perdrait les sommets déjà posés)
    map.on('zoomend', () => {
      if (map.pm.globalDrawModeEnabled()) Object.assign(map.pm.Draw.Line.options, snapOptions());
      if (editing && geometryLine) Object.assign(geometryLine.pm.options, snapOptions());
    });
    // Un sommet posé / déplacé sur un arrêt le lie à la ligne.
    function linkArretNear(latlng) {
      if (!latlng || map.getZoom() < SNAP_MIN_ZOOM) return;
      const p = map.latLngToLayerPoint(latlng);
      for (const { marker, arret } of markers.values()) {
        if (map.latLngToLayerPoint(marker.getLatLng()).distanceTo(p) <= SNAP_PX) {
          setArretLinked(arret.id, true);
          break;
        }
      }
    }
    drawBtn.addEventListener('click', () => setDrawing(drawBtn.getAttribute('aria-pressed') !== 'true'));
    // Passer par un arrêt (sommet accroché) le lie à la ligne.
    map.on('pm:drawstart', ({ workingLayer }) => {
      workingLayer.on('pm:vertexadded', (e) => linkArretNear(e.latlng));
    });
    map.on('pm:create', (e) => {
      const line = e.layer.toGeoJSON().geometry.coordinates;
      map.removeLayer(e.layer);
      const g = currentGeometry() || { type: 'MultiLineString', coordinates: [] };
      g.coordinates.push(line);
      setGeometry(g, false);
      setDrawing(false);
    });
  </script>
  <script type="module" src="/dist/ajout-ligne.js"></script>
</body>

</html>
