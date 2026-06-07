<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$admin = ($_GET['admin'] ?? false) == $config["admin_token"];
$arret_id = $_GET['arret_id'] ?? null;

// Falaises (toutes) pour la carte
$falaises = [];
$res = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_latlng, falaise_nomformate FROM falaises ORDER BY falaise_nom");
while ($row = $res->fetch_assoc()) {
  $falaises[] = [
    'id' => (int) $row['falaise_id'],
    'nom' => $row['falaise_nom'],
    'latlng' => $row['falaise_latlng'],
    'nomformate' => $row['falaise_nomformate'],
  ];
}

// Arrêts de bus existants (pour l'autocomplete des liaisons)
$arrets = [];
$res = $mysqli->query("SELECT id, nom FROM bus_arrets ORDER BY nom");
while ($row = $res->fetch_assoc()) {
  $arrets[] = ['id' => (int) $row['id'], 'nom' => $row['nom']];
}

// Lignes de bus existantes (pour l'autocomplete des lignes)
$lignes = [];
$res = $mysqli->query("SELECT id, nom, description, lien FROM bus_lignes ORDER BY nom");
while ($row = $res->fetch_assoc()) {
  $lignes[] = [
    'id' => (int) $row['id'],
    'nom' => $row['nom'],
    'description' => $row['description'],
    'lien' => $row['lien'],
  ];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= $arret_id ? "Modifier" : "Ajouter" ?> un arrêt de bus - Vélogrimpe.fr</title>
  <?php map_bundle_js('map'); ?>
  <?php map_bundle_css('map'); ?>
  <?php vite_css('main'); ?>
  <script async defer src="/js/pv.js"></script>
  <script src="/js/contrib-storage.js"></script>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
  <?php vite_css('ajout-bus'); ?>
  <style>
    .admin {
      <?= !$admin ? 'display: none !important;' : '' ?>
    }

    .linked-falaise {
      filter: drop-shadow(0 0 3px #16a34a) drop-shadow(0 0 2px #16a34a);
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($admin): ?>
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
        document.getElementById('nom_prenom').value = "<?= isset($_SERVER["REMOTE_USER"]) ? $_SERVER["REMOTE_USER"] : "Florent" ?>";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('admin').value = '0';
        if (window.contribStorage) {
          window.contribStorage.prefillContribInputs();
        }
      <?php endif; ?>
    });
  </script>
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4 prose-pre:my-0">
    <h1 class="text-4xl font-bold text-wrap text-center">
      <?= $arret_id ? "Modifier" : "Ajouter" ?> un arrêt de bus<span class='text-red-900 admin'> (version admin)</span>
    </h1>

    <form method="post" action="/api/add_bus.php" class="flex flex-col gap-4" id="form">
      <input type="hidden" id="admin" name="admin" value="0" />
      <input type="hidden" id="arret_id" name="arret_id" value="<?= $arret_id ? (int) $arret_id : '' ?>" />
      <input type="hidden" id="arret_falaise_ids" name="arret_falaise_ids" value="" />
      <input type="hidden" id="arret_osm_id" name="arret_osm_id" value="" />
      <input type="hidden" id="arret_osm_data" name="arret_osm_data" value="" />

      <!-- ===== Section Arrêt de bus ===== -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <span class="px-2 text-primary italic">Arrêt de bus</span>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>

      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="arret_nom">
          <b>Nom de l'arrêt</b>
          <input class="input input-primary input-sm" type="text" id="arret_nom" name="arret_nom"
            placeholder="ex: Buoux - Mairie" required autocomplete="off">
        </label>

        <div class="flex flex-col gap-2">
          <label class="form-control" for="arret_loc">
            <b>Coordonnées GPS <span class="text-error">(format : "latitude,longitude")</span></b>
            <input class="input input-primary input-sm" type="text" id="arret_loc" name="arret_loc"
              placeholder="ex: 43.8270,5.3830" required autocomplete="off">
          </label>
          <div id="map" class="w-full h-72 rounded-lg relative" title="Cliquez pour placer l'arrêt">
            <div id="mapinstructions" class="h-full w-full bg-[#3333] flex items-center justify-center
              pointer-events-none z-[10000] absolute top-0 left-0 rounded-lg text-black text-xl">
              <span class="bg-[#fff8] rounded-lg px-2 py-1 max-w-50 sm:max-w-full">Cliquez pour placer l'arrêt</span>
            </div>
          </div>
          <i class="text-slate-400 text-sm">Cliquez sur la carte pour placer l'arrêt, ou utilisez le bouton
            « Arrêts OSM de la zone » pour récupérer les arrêts existants via OpenStreetMap. Vous pouvez aussi cliquer
            sur une falaise pour la lier à cet arrêt.</i>
          <div id="arret_osm_props_wrap" class="hidden">
            <b class="text-sm">Données OpenStreetMap de l'arrêt :</b>
            <pre
              class="bg-base-200 text-base-content rounded-lg text-xs overflow-auto max-h-48 p-2"><code id="arret_osm_props" class="text-base-content"></code></pre>
          </div>
        </div>

        <details class="border border-base-200 rounded-lg p-2">
          <summary class="cursor-pointer font-bold text-sm">Commentaire sur l'arrêt <i
              class="opacity-50 font-normal">(optionnel)</i></summary>
          <div class="mt-2">
            <div class="vue-richtext" data-name="description"></div>
          </div>
        </details>
      </div>

      <!-- ===== Sections Liaisons + Lignes (Vue) ===== -->
      <div id="vue-ajout-bus" data-arrets="<?= htmlspecialchars(json_encode($arrets), ENT_QUOTES, 'UTF-8') ?>"
        data-lignes="<?= htmlspecialchars(json_encode($lignes), ENT_QUOTES, 'UTF-8') ?>" <?php if ($arret_id): ?>data-preset-arret-id="<?= (int) $arret_id ?>" <?php endif; ?>>
      </div>

      <!-- ===== Section contributeur ===== -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <span class="px-2 text-primary italic">Contributeur</span>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="form-control grow">
            <label for="nom_prenom">
              <?php if ($arret_id): ?>
                <div><b>Modification par :</b></div>
              <?php else: ?>
                <b>Arrêt ajouté par</b>
              <?php endif; ?>
              <input class="input input-primary input-sm w-full" type="text" id="nom_prenom" name="nom_prenom" required>
            </label>
          </div>
          <div class="form-control grow">
            <b>Mail</b>
            <input class="input input-primary input-sm w-full" type="email" id="email" name="email" required>
          </div>
        </div>
        <label class="form-control" for="message">
          <b>Message <span class="text-accent opacity-50">(optionnel)</span></b>
          <i>(si vous voulez commenter votre contribution)</i>
          <textarea class="textarea textarea-primary" id="message" name="message" rows="3"></textarea>
        </label>
        <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/contrib-licence-notice.php"; ?>
      </div>

      <button type="submit" class="btn btn-primary">
        <?= $arret_id ? "Modifier" : "Ajouter" ?> l'arrêt de bus
      </button>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>

  <script type="module">
    import { createAjoutMap } from '/js/components/map/ajout-map.js';
    import { fetchBusStops } from '/js/components/utils/fetch-bus-stops.js';

    const falaises = <?= json_encode($falaises) ?>;

    const { map } = createAjoutMap('map');
    const mapinstructions = document.getElementById('mapinstructions');
    const arretLocInput = document.getElementById('arret_loc');

    const escapeHtml = (s) => String(s ?? '')
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');

    // --- Marqueur de l'arrêt ---
    const ARRET_SIZE = 24;
    const busIcon = L.icon({
      iconUrl: '/images/map/bus.png',
      iconSize: [ARRET_SIZE, ARRET_SIZE],
      iconAnchor: [ARRET_SIZE / 2, ARRET_SIZE / 2],
      tooltipAnchor: [0, -ARRET_SIZE / 2],
      popupAnchor: [0, -ARRET_SIZE / 2],
      className: "vg-bus-stop-icon border border-white border-2 rounded-lg",
    });
    let arretMarker;
    function createArretMarker(lat, lng) {
      if (mapinstructions) mapinstructions.style.display = 'none';
      if (arretMarker) map.removeLayer(arretMarker);
      arretMarker = L.marker([lat, lng], { draggable: true, icon: busIcon }).addTo(map);
      arretMarker.on('dragend', () => {
        const ll = arretMarker.getLatLng();
        arretLocInput.value = ll.lat.toFixed(6) + ',' + ll.lng.toFixed(6);
      });
    }
    function updateArretMarker() {
      const coords = arretLocInput.value.split(',');
      if (coords.length === 2) {
        const lat = parseFloat(coords[0]), lng = parseFloat(coords[1]);
        if (!isNaN(lat) && !isNaN(lng)) {
          createArretMarker(lat, lng);
          map.setView([lat, lng], 13);
          return;
        }
      }
      if (arretMarker) { map.removeLayer(arretMarker); arretMarker = undefined; }
      if (mapinstructions) mapinstructions.style.display = 'flex';
    }
    map.on('click', (e) => {
      createArretMarker(e.latlng.lat, e.latlng.lng);
      arretLocInput.value = e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6);
    });
    arretLocInput.addEventListener('input', updateArretMarker);

    // --- Falaises (marqueurs + liaison) ---
    const linkedFalaises = new Set();
    const falaiseHidden = document.getElementById('arret_falaise_ids');
    const falaiseMarkers = new Map();
    const falaiseIcon = (linked) => L.icon({
      iconUrl: '/images/map/icone_falaise_carte.png',
      iconSize: [22, 22],
      iconAnchor: [11, 22],
      className: linked ? 'linked-falaise' : 'opacity-80',
    });
    function syncFalaiseHidden() {
      falaiseHidden.value = Array.from(linkedFalaises).join(',');
    }
    function setFalaiseLinked(id, linked) {
      if (linked) linkedFalaises.add(id); else linkedFalaises.delete(id);
      const entry = falaiseMarkers.get(id);
      if (entry) entry.marker.setIcon(falaiseIcon(linked));
      syncFalaiseHidden();
    }
    falaises.forEach((f) => {
      const coords = (f.latlng || '').split(',');
      if (coords.length !== 2) return;
      const lat = parseFloat(coords[0]), lng = parseFloat(coords[1]);
      if (isNaN(lat) || isNaN(lng)) return;
      const id = Number(f.id);
      const marker = L.marker([lat, lng], { icon: falaiseIcon(false) }).addTo(map);
      marker.bindPopup('');
      marker.on('popupopen', () => {
        const linked = linkedFalaises.has(id);
        const div = document.createElement('div');
        div.className = 'flex flex-col gap-1 text-base-content';
        div.innerHTML = `<b>${escapeHtml(f.nom)}</b>`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs ' + (linked ? 'btn-error' : 'btn-primary');
        btn.textContent = linked ? 'Délier cette falaise' : 'Lier cette falaise à cet arrêt';
        btn.addEventListener('click', () => {
          setFalaiseLinked(id, !linkedFalaises.has(id));
          marker.closePopup();
        });
        div.appendChild(btn);
        marker.setPopupContent(div);
      });
      falaiseMarkers.set(id, { marker });
    });
    // Exposé pour le prefill (ajout-bus.ts)
    window.busSetLinkedFalaises = (ids) => {
      (ids || []).forEach((id) => setFalaiseLinked(Number(id), true));
    };

    // --- Contrôle Overpass (style leaflet-bar carré, comme le zoom) ---
    const overpassLayer = L.layerGroup().addTo(map);
    const OverpassControl = L.Control.extend({
      options: { position: 'topleft' },
      onAdd: function () {
        const c = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
        const a = L.DomUtil.create('a', '', c);
        a.href = '#';
        a.role = 'button';
        a.title = 'Récupérer les arrêts de bus de la zone visible (OpenStreetMap)';
        a.setAttribute('aria-label', 'Arrêts de bus OpenStreetMap');
        a.style.cssText = 'display:flex;align-items:center;justify-content:center;';
        a.innerHTML = '<img src="/images/map/overpass-turbo.svg" alt="Overpass" style="width:18px;height:18px;" />';
        L.DomEvent.disableClickPropagation(c);
        L.DomEvent.on(a, 'click', (ev) => {
          L.DomEvent.stop(ev);
          loadOverpassStops();
        });
        return c;
      },
    });
    map.addControl(new OverpassControl());

    async function loadOverpassStops() {
      try {
        overpassLayer.clearLayers();
        const stops = await fetchBusStops(map);
        if (!stops.length) {
          alert('Aucun arrêt de bus trouvé dans la zone visible. Dézoomez ou déplacez la carte.');
          return;
        }
        stops.forEach((s) => {
          const m = L.circleMarker([s.lat, s.lon], {
            radius: 6, weight: 2, color: '#2563eb', fillColor: '#60a5fa', fillOpacity: 0.7,
          });
          const routes = (s.routes || []).map((r) => {
            const n = (r.network || '').trim(), ref = (r.ref || '').trim();
            if (n && ref) return `${n} : Ligne ${ref}`;
            if (ref) return `Ligne ${ref}`;
            return (r.name || '').trim();
          }).filter(Boolean);
          const popup = document.createElement('div');
          popup.className = 'flex flex-col gap-1 text-base-content';
          popup.style.minWidth = '220px';
          popup.innerHTML =
            `<div class="text-xs opacity-70">Arrêt OpenStreetMap</div><b>${escapeHtml(s.name)}</b>` +
            (s.network ? `<div class="text-xs">Réseau : ${escapeHtml(s.network)}</div>` : '') +
            (routes.length ? `<div class="text-xs">${routes.map(escapeHtml).join('<br>')}</div>` : '');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn-xs btn-primary mt-1';
          btn.textContent = 'Utiliser ces données';
          btn.addEventListener('click', () => useOverpassStop(s, m));
          popup.appendChild(btn);
          m.bindPopup(popup, { minWidth: 220 });
          overpassLayer.addLayer(m);
        });
      } catch (e) {
        console.error('Erreur Overpass:', e);
        alert('Impossible de récupérer les arrêts de bus pour la zone visible.');
      }
    }
    function useOverpassStop(s, m) {
      arretLocInput.value = s.lat.toFixed(6) + ',' + s.lon.toFixed(6);
      createArretMarker(s.lat, s.lon);
      map.setView([s.lat, s.lon], 16);
      const nomEl = document.getElementById('arret_nom');
      if (nomEl) nomEl.value = s.name || '';
      const osmIdEl = document.getElementById('arret_osm_id');
      if (osmIdEl) osmIdEl.value = s.osm_id || '';
      const osmDataEl = document.getElementById('arret_osm_data');
      if (osmDataEl) osmDataEl.value = JSON.stringify(s.tags || {});
      const props = document.getElementById('arret_osm_props');
      const wrap = document.getElementById('arret_osm_props_wrap');
      if (props) props.textContent = JSON.stringify(s.tags || {}, null, 2);
      if (wrap) wrap.classList.remove('hidden');
      try { m.closePopup(); } catch (_) { /* noop */ }
    }

    // Initialise le marqueur si des coordonnées sont déjà présentes
    updateArretMarker();
  </script>
  <script type="module" src="/dist/ajout-bus.js"></script>
</body>

</html>