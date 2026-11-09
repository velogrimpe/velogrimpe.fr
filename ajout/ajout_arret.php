<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/database/velogrimpe.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/vite.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/map-bundle.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/arrets_lib.php";
$config = require $_SERVER["DOCUMENT_ROOT"] . "/../config.php";

$admin = ($_GET["admin"] ?? false) == $config["admin_token"];
$arret_id = $_GET["arret_id"] ?? null;

// Falaises à pré-lier à l'arrêt (param ?falaise_ids=12,34). Permet par ex. au
// bouton « ajouter un arrêt » de confirmation_falaise de pré-remplir le lien.
$preset_falaise_ids = array_values(
  array_filter(
    array_map("intval", explode(",", $_GET["falaise_ids"] ?? "")),
    fn($v) => $v > 0,
  ),
);

// Falaises (toutes) pour la carte
$falaises = [];
$res = $mysqli->query(
  "SELECT falaise_id, falaise_nom, falaise_latlng, falaise_nomformate FROM falaises ORDER BY falaise_nom",
);
while ($row = $res->fetch_assoc()) {
  $falaises[] = [
    "id" => (int) $row["falaise_id"],
    "nom" => $row["falaise_nom"],
    "latlng" => $row["falaise_latlng"],
    "nomformate" => $row["falaise_nomformate"],
  ];
}

// Tous les arrêts multimodaux (pour le sélecteur admin « modifier un arrêt
// existant » : les gares deviennent éditables via ce formulaire, Lot 3)
$tousArrets = [];
$res = $mysqli->query("SELECT id, nom FROM arrets ORDER BY nom");
while ($row = $res->fetch_assoc()) {
  $tousArrets[] = ["id" => (int) $row["id"], "nom" => $row["nom"]];
}

// Arrêts existants à afficher sur la carte (repères, pour éviter les doublons) ;
// l'arrêt en cours d'édition est exclu : il est représenté par le marqueur déplaçable.
$arretsCarte = [];
$res = $mysqli->query(
  "SELECT id, nom, ter, tgv, bus, tram, teleph, ST_Y(loc) AS lat, ST_X(loc) AS lng FROM arrets ORDER BY nom",
);
while ($row = $res->fetch_assoc()) {
  if ((int) $row["id"] === (int) $arret_id) continue;
  $arretsCarte[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "types" => arret_types($row),
    "lat" => (float) $row["lat"],
    "lng" => (float) $row["lng"],
  ];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= $arret_id
    ? "Modifier"
    : "Ajouter" ?> un arrêt - Vélogrimpe.fr</title>
  <?php map_bundle_js("map"); ?>
  <?php map_bundle_css("map"); ?>
  <?php vite_css("main"); ?>
  <script async defer src="/js/pv.js"></script>
  <script src="/js/contrib-storage.js"></script>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
  <?php vite_css("ajout-bus"); ?>
  <style>
    .admin {
      <?= !$admin ? "display: none !important;" : "" ?>
    }

    .linked-falaise {
      filter: drop-shadow(0 0 3px #16a34a) drop-shadow(0 0 2px #16a34a);
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($admin): ?>
        document.getElementById('admin').value = "<?= $config[
          "admin_token"
        ] ?>";
        document.getElementById('nom_prenom').value = "<?= isset(
          $_SERVER["REMOTE_USER"],
        )
          ? $_SERVER["REMOTE_USER"]
          : "Florent" ?>";
        document.getElementById('email').value = "<?= $config[
          "contact_mail"
        ] ?>";
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
  <?php include $_SERVER["DOCUMENT_ROOT"] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4 prose-pre:my-0">
    <h1 class="text-4xl font-bold text-wrap text-center">
      <?= $arret_id
        ? "Modifier"
        : "Ajouter" ?> un arrêt<span class='text-red-900 admin'> (version admin)</span>
    </h1>

    <?php if ($admin): ?>
      <!-- Admin : Sélecteur pour modifier un arrêt de bus -->
      <div class="relative flex items-center">
        <div class="flex items-center justify-center">
          <span class="px-2 text-red-900 italic bg-unset rounded-full">Modifier un arrêt existant</span>
        </div>
      </div>
      <div class="flex flex-col gap-2 bg-base-100 p-4 rounded-lg border border-red-900 shadow-xs">
        <label class="form-control not-prose" for="select-arret">
          <select id="select-arret" class="select select-primary select-sm w-full"
            onchange="if (this.value) window.location.href = '/ajout/ajout_arret.php?admin=<?= urlencode(
              $config["admin_token"],
            ) ?>&' + this.value">
            <option value="">Sélectionner un arrêt à modifier…</option>
            <?php foreach ($tousArrets as $a): ?>
              <option value="<?= http_build_query([
                "arret_id" => $a["id"],
              ]) ?>" <?= $a["id"] === (int) $arret_id ? "selected" : "" ?>>
                <?= htmlspecialchars($a["nom"], ENT_QUOTES, "UTF-8") ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <i class="text-sm">Choisir un arrêt recharge la page en mode édition : les saisies en cours seront perdues.</i>
      </div>
    <?php endif; ?>

    <form method="post" action="/api/add_arret.php" class="flex flex-col gap-4" id="form">
      <input type="hidden" id="admin" name="admin" value="0" />
      <input type="hidden" id="arret_id" name="arret_id" value="<?= $arret_id
        ? (int) $arret_id
        : "" ?>" />
      <input type="hidden" id="arret_falaise_ids" name="arret_falaise_ids" value="" />
      <input type="hidden" id="arret_osm_id" name="arret_osm_id" value="" />
      <input type="hidden" id="arret_osm_data" name="arret_osm_data" value="" />

      <!-- ===== Section Arrêt ===== -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <span class="px-2 text-primary italic">Arrêt</span>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>

      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="arret_nom">
          <b>Nom de l'arrêt</b>
          <input class="input input-primary input-sm" type="text" id="arret_nom" name="arret_nom"
            placeholder="ex: Buoux - Mairie" required autocomplete="off">
        </label>

        <div class="form-control" id="arret_types_group">
          <b class="text-sm">Modalités desservies <span class="text-error">(au moins une)</span></b>
          <div class="flex flex-wrap gap-4 mt-1">
            <?php foreach (
              [
                "ter" => "TER",
                "tgv" => "TGV",
                "bus" => "Bus",
                "tram" => "Tram",
                "telepherique" => "Téléphérique",
              ]
              as $typeValue => $typeLabel
            ): ?>
              <label class="label cursor-pointer gap-2">
                <input type="checkbox" class="checkbox checkbox-sm checkbox-primary arret-type-checkbox"
                  name="types[]" value="<?= $typeValue ?>" />
                <span class="label-text"><?= $typeLabel ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="form-control" for="arret_loc">
            <b>Coordonnées GPS <span class="text-error">(format : "latitude,longitude")</span></b>
            <input class="input input-primary input-sm" type="text" id="arret_loc" name="arret_loc"
              placeholder="ex: 43.8270,5.3830" required autocomplete="off">
          </label>
          <div id="map" class="w-full h-[36rem] rounded-lg relative not-prose">
            <div id="mapinstructions" class="pointer-events-none absolute top-2 left-1/2 -translate-x-1/2 z-[10000]">
              <span
                class="bg-base-100/90 border border-base-300 shadow rounded-full px-3 py-1 text-sm font-medium">Cliquez
                sur la carte pour placer l'arrêt</span>
            </div>
          </div>
          <i class="text-slate-400 text-sm">Cliquez sur la carte pour placer l'arrêt, ou utilisez le bouton
            « Interroger open-data » pour récupérer les arrêts existants via OpenStreetMap. Vous pouvez aussi
            cliquer sur une falaise pour la lier à cet arrêt.</i>
          <div id="arret_osm_props_wrap" class="hidden">
            <b class="text-sm">Données OpenStreetMap de l'arrêt :</b>
            <pre
              class="bg-base-200 text-base-content rounded-lg text-xs overflow-auto max-h-48 p-2"><code id="arret_osm_props" class="text-base-content"></code></pre>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
          <div class="form-control">
            <b class="text-sm">Commune</b>
            <input class="input input-disabled input-sm" type="text" id="arret_commune" name="commune"
              readonly tabindex="-1" placeholder=" " />
            <i class="text-slate-400 text-sm">Renseigné automatiquement à partir des coordonnées GPS.</i>
          </div>
          <div class="form-control">
            <b class="text-sm">Code INSEE</b>
            <input class="input input-disabled input-sm" type="text" id="arret_code_insee" name="code_insee"
              readonly tabindex="-1" placeholder=" " />
          </div>
          <div class="form-control">
            <b class="text-sm">Code postal</b>
            <input class="input input-disabled input-sm" type="text" id="arret_cp" name="cp"
              readonly tabindex="-1" placeholder=" " />
          </div>
        </div>

        <!-- Liste des falaises liées (Vue) : alternative à la carte pour lier/délier.
             Les données arrivent par window.busFalaises, posé par le script carte. -->
        <div id="vue-falaises-liees"></div>

        <details class="border border-base-200 rounded-lg p-2">
          <summary class="cursor-pointer font-bold text-sm">Commentaire sur l'arrêt <i
              class="opacity-50 font-normal">(optionnel)</i></summary>
          <div class="mt-2">
            <div class="vue-richtext" data-name="description"></div>
          </div>
        </details>
      </div>

      <!-- Porteur de l'id de l'arrêt édité, lu par ajout-bus.ts -->
      <div id="vue-ajout-bus" <?php if ($arret_id): ?>data-preset-arret-id="<?= (int) $arret_id ?>" <?php endif; ?>></div>

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
        <?php include $_SERVER["DOCUMENT_ROOT"] .
          "/components/contrib-licence-notice.php"; ?>
      </div>

      <button type="submit" class="btn btn-primary">
        <?= $arret_id ? "Modifier" : "Ajouter" ?> l'arrêt
      </button>
    </form>
  </main>
  <?php include $_SERVER["DOCUMENT_ROOT"] . "/components/footer.php"; ?>

  <script type="module">
    import { createAjoutMap } from '/js/components/map/ajout-map.js';
    import { fetchArretStops } from '/js/components/utils/fetch-bus-stops.js';
    import { overpassErrorMessage } from '/js/components/utils/overpass.js';
    import { showToast } from '/js/components/utils/toast.js';
    import { arretDivIcon, arretDotIcon } from '/js/components/map/arret-icon.js';

    const falaises = <?= json_encode($falaises) ?>;
    const arretsExistants = <?= json_encode($arretsCarte) ?>;
    const presetFalaiseIds = <?= json_encode($preset_falaise_ids) ?>;
    const OVERPASS_MIN_ZOOM = 11;
    // Modalités recherchables via Overpass (pas de gares ter/tgv, cf.
    // docs/conception-bus.md Étape 2).
    const OVERPASS_TYPES = ['bus', 'tram', 'telepherique'];

    function getCheckedTypes() {
      return Array.from(document.querySelectorAll('.arret-type-checkbox:checked')).map((cb) => cb.value);
    }
    function setTypeChecked(type, checked) {
      const cb = document.querySelector(`.arret-type-checkbox[value="${type}"]`);
      if (cb) cb.checked = checked;
    }

    const { map, searchButton: overpassBtn } = createAjoutMap('map', {
      searchButton: {
        label: 'Interroger open-data',
        title: 'Récupérer les arrêts (bus/tram/téléphérique) de la zone visible (OpenStreetMap)',
        ariaLabel: 'Interroger open-data',
        minZoom: OVERPASS_MIN_ZOOM,
      },
    });
    const mapinstructions = document.getElementById('mapinstructions');
    const arretLocInput = document.getElementById('arret_loc');

    const escapeHtml = (s) => String(s ?? '')
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');

    // --- Marqueur de l'arrêt (icône reflétant les modalités cochées) ---
    const ARRET_SIZE = 24;
    let arretMarker;
    function currentArretIcon() {
      return arretDivIcon(getCheckedTypes(), ARRET_SIZE);
    }
    function refreshArretIcon() {
      if (arretMarker) arretMarker.setIcon(currentArretIcon());
    }
    document.querySelectorAll('.arret-type-checkbox').forEach((cb) => {
      cb.addEventListener('change', refreshArretIcon);
    });
    function createArretMarker(lat, lng) {
      if (mapinstructions) mapinstructions.style.display = 'none';
      if (arretMarker) map.removeLayer(arretMarker);
      arretMarker = L.marker([lat, lng], { draggable: true, icon: currentArretIcon() }).addTo(map);
      arretMarker.on('dragend', () => {
        const ll = arretMarker.getLatLng();
        arretLocInput.value = ll.lat.toFixed(6) + ',' + ll.lng.toFixed(6);
        updateCommune(ll.lat, ll.lng);
      });
    }

    // --- Arrêts existants (repères) : point jusqu'au zoom 12, icône au-delà ---
    const EXISTANT_DOT_MAX_ZOOM = 12;
    const existantsDot = () => map.getZoom() <= EXISTANT_DOT_MAX_ZOOM;
    const existantIcon = (a) => existantsDot()
      ? arretDotIcon(a.types, 12, { extraClassName: 'vg-arret-existant' })
      : arretDivIcon(a.types, 20, { extraClassName: 'vg-arret-existant' });
    const existantMarkers = arretsExistants.map((a) => {
      const marker = L.marker([a.lat, a.lng], { icon: existantIcon(a), zIndexOffset: -500, keyboard: false })
        .bindTooltip(escapeHtml(a.nom)).addTo(map);
      return { marker, arret: a };
    });
    let existantsWasDot = existantsDot();
    map.on('zoomend', () => {
      const dot = existantsDot();
      if (dot === existantsWasDot) return;
      existantsWasDot = dot;
      existantMarkers.forEach(({ marker, arret }) => marker.setIcon(existantIcon(arret)));
    });

    // --- Géocodage inverse (commune/code_insee/cp), silencieux + retour visuel ---
    function updateCommune(lat, lng) {
      fetch(`/api/reverse_geocode.php?lat=${lat}&lng=${lng}`)
        .then((r) => (r.ok ? r.json() : Promise.reject(new Error('reverse_geocode failed'))))
        .then((data) => {
          const communeEl = document.getElementById('arret_commune');
          const codeInseeEl = document.getElementById('arret_code_insee');
          const cpEl = document.getElementById('arret_cp');
          if (communeEl) communeEl.value = data?.commune || '';
          if (codeInseeEl) codeInseeEl.value = data?.code_insee || '';
          if (cpEl) cpEl.value = data?.cp || '';
        })
        .catch(() => { /* silencieux, cf. lib/geoplateforme_lib.php */ });
    }
    function updateArretMarker() {
      const coords = arretLocInput.value.split(',');
      if (coords.length === 2) {
        const lat = parseFloat(coords[0]), lng = parseFloat(coords[1]);
        if (!isNaN(lat) && !isNaN(lng)) {
          createArretMarker(lat, lng);
          map.setView([lat, lng], 13);
          updateCommune(lat, lng);
          return;
        }
      }
      if (arretMarker) { map.removeLayer(arretMarker); arretMarker = undefined; }
      if (mapinstructions) mapinstructions.style.display = 'block';
    }
    // Le clic sur la carte n'écrase pas directement la position : il ouvre une
    // popup de confirmation, pour éviter les déplacements accidentels.
    const placementPopup = L.popup({ closeButton: false, className: 'vg-placement-popup' });
    map.on('click', (e) => {
      const label = arretMarker ? "Déplacer l'arrêt ici" : "Placer l'arrêt ici";
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-xs btn-primary';
      btn.textContent = label;
      btn.addEventListener('click', () => {
        createArretMarker(e.latlng.lat, e.latlng.lng);
        arretLocInput.value = e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6);
        updateCommune(e.latlng.lat, e.latlng.lng);
        map.closePopup(placementPopup);
      });
      placementPopup.setLatLng(e.latlng).setContent(btn).openOn(map);
    });
    arretLocInput.addEventListener('input', updateArretMarker);

    // --- Falaises (marqueurs + liaison) ---
    const linkedFalaises = new Set();
    const falaiseHidden = document.getElementById('arret_falaise_ids');
    const falaiseMarkers = new Map();
    const falaiseIcon = (linked) => L.icon({
      iconUrl: '/images/map/icone_falaise_carte.png',
      iconSize: [22, 22],
      iconAnchor: [11, 11],
      popupAnchor: [0, -11],
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
      // Seul point de sortie : clic sur un marqueur, prefill et liste Vue passent
      // tous par ici, donc la liste reste synchro sans état parallèle.
      document.dispatchEvent(new CustomEvent('velogrimpe:bus-falaises-changed', {
        detail: { ids: Array.from(linkedFalaises) },
      }));
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
    // Pont vers la liste Vue des falaises liées (#vue-falaises-liees)
    window.busFalaises = falaises;
    window.busSetFalaiseLinked = (id, linked) => setFalaiseLinked(Number(id), !!linked);
    window.busFocusFalaise = (id) => {
      const ll = falaiseMarkers.get(Number(id))?.marker.getLatLng();
      if (ll) map.setView(ll, 14);
    };
    // Cadre sur l'ensemble « arrêt + falaises liées », pour qu'un ajout depuis la
    // liste montre la géographie complète plutôt que la seule falaise ajoutée.
    window.busFitLinkedFalaises = () => {
      const pts = Array.from(linkedFalaises)
        .map((id) => falaiseMarkers.get(Number(id))?.marker.getLatLng())
        .filter(Boolean);
      if (arretMarker) pts.push(arretMarker.getLatLng());
      if (!pts.length) return;
      map.fitBounds(L.latLngBounds(pts), { maxZoom: 16, padding: [30, 30] });
    };
    // Pré-liaison via le paramètre d'URL ?falaise_ids=...
    if (presetFalaiseIds && presetFalaiseIds.length) {
      window.busSetLinkedFalaises(presetFalaiseIds);
      const pts = presetFalaiseIds
        .map((id) => falaiseMarkers.get(Number(id))?.marker.getLatLng())
        .filter(Boolean);
      if (pts.length) map.setView(pts[0], 14);
    }

    // --- Récupération des arrêts via Overpass (bouton "Interroger open-data") ---
    const overpassLayer = L.layerGroup().addTo(map);
    if (overpassBtn) {
      overpassBtn.addEventListener('click', loadOverpassStops);
    }

    async function loadOverpassStops() {
      // Refuse les zones trop larges (trop de résultats, requête lourde)
      if (map.getZoom() < OVERPASS_MIN_ZOOM) {
        alert('Zone trop large : zoomez davantage (au moins niveau ' + OVERPASS_MIN_ZOOM + ') avant de récupérer les arrêts.');
        return;
      }
      if (overpassBtn?.disabled) return;
      // Etat loading : bouton désactivé + spinner à la place de l'icône
      const icon = overpassBtn?.querySelector('img');
      const spinner = document.createElement('span');
      spinner.className = 'loading loading-spinner loading-xs';
      if (overpassBtn) {
        overpassBtn.disabled = true;
        overpassBtn.style.opacity = '.6';
        overpassBtn.style.cursor = 'wait';
        icon?.replaceWith(spinner);
      }
      try {
        overpassLayer.clearLayers();
        const stops = await fetchArretStops(map, OVERPASS_TYPES);
        if (!stops.length) {
          alert('Aucun arrêt trouvé dans la zone visible. Dézoomez ou déplacez la carte.');
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
            `<div class="text-xs opacity-70">Arrêt OpenStreetMap (${escapeHtml(s.vg_type)})</div><b>${escapeHtml(s.name)}</b>` +
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
        showToast(overpassErrorMessage(e), 'error', 8000);
      } finally {
        if (icon) spinner.replaceWith(icon); else spinner.remove();
        if (overpassBtn) {
          overpassBtn.disabled = false;
          overpassBtn.style.opacity = '';
          overpassBtn.style.cursor = 'pointer';
        }
      }
    }
    function useOverpassStop(s, m) {
      arretLocInput.value = s.lat.toFixed(6) + ',' + s.lon.toFixed(6);
      if (s.vg_type) setTypeChecked(s.vg_type, true);
      createArretMarker(s.lat, s.lon);
      map.setView([s.lat, s.lon], 16);
      updateCommune(s.lat, s.lon);
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
