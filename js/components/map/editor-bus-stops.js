/**
 * Calque des arrêts de bus issus de la DB pour l'éditeur de détails falaise.
 *
 * - Affiche les arrêts existants : liés à la falaise (pleins) vs non liés (estompés).
 * - Clic sur un arrêt non lié : « Cet arrêt est pertinent pour cette falaise » → Lier.
 * - Clic sur un arrêt lié : Délier.
 * - Recharge sur déplacement de carte (debouncé) ; les arrêts liés sont toujours inclus.
 *
 * @module editor-bus-stops
 */
import BusStop from "/js/components/map/bus-stop.js";

const MIN_ZOOM = 11;

const escapeHtml = (str) =>
  String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

// Style « non lié » injecté une seule fois.
let styleInjected = false;
function injectStyle() {
  if (styleInjected) return;
  styleInjected = true;
  const style = document.createElement("style");
  style.textContent = `.vg-bus-unlinked { filter: grayscale(1); opacity: .55; }`;
  document.head.appendChild(style);
}

function unlinkedIcon() {
  const size = BusStop.iconSize;
  return L.icon({
    iconUrl: "/images/map/bus.png",
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    tooltipAnchor: [0, -size / 2],
    popupAnchor: [0, -size / 2],
    className:
      "vg-bus-stop-icon vg-bus-unlinked border border-white border-2 rounded-lg",
  });
}

/**
 * @param {L.Map} map
 * @param {Object} opts
 * @param {number} opts.falaiseId
 * @param {function(): Promise<{nom:string,email:string}|null>} opts.ensureContrib
 *        Retourne les infos contributeur (en demandant via modal si besoin), ou null si annulé.
 * @returns {{ refresh: function, destroy: function }}
 */
export function initEditorBusStops(map, { falaiseId, ensureContrib }) {
  injectStyle();
  const layer = L.layerGroup().addTo(map);

  const buildPopup = (arret) => {
    const lignes =
      Array.isArray(arret.lignes) && arret.lignes.length
        ? `<div class="text-xs opacity-70">${escapeHtml(arret.lignes.join(", "))}</div>`
        : "";
    // arret.description est du HTML déjà assaini côté serveur (rt_sanitize_html).
    const desc = arret.description
      ? `<div class="text-sm">${arret.description}</div>`
      : "";

    if (arret.linked) {
      return `
        <div class="flex flex-col gap-2 w-[260px]">
          <div class="font-bold">${escapeHtml(arret.nom)} <span class="badge badge-success badge-xs">lié</span></div>
          ${lignes}
          ${desc}
          <button class="btn btn-xs btn-error unlink-bus-btn" type="button">Délier de cette falaise</button>
        </div>`;
    }
    return `
      <div class="flex flex-col gap-2 w-[260px]">
        <div class="font-bold">${escapeHtml(arret.nom)}</div>
        ${lignes}
        ${desc}
        <div class="text-sm font-semibold mt-1">Cet arrêt est pertinent pour cette falaise ?</div>
        <div class="text-xs opacity-70">Pertinent = accessible à pied ou à vélo depuis la falaise, et le vélo est transportable dans le bus qui dessert cet arrêt.</div>
        <button class="btn btn-xs btn-primary link-bus-btn" type="button">Lier cet arrêt à la falaise</button>
      </div>`;
  };

  const doLink = async (arret, action) => {
    const contrib = await ensureContrib();
    if (!contrib) return;
    try {
      const res = await fetch("/api/link_bus_falaise.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          arret_id: arret.id,
          falaise_id: falaiseId,
          action,
          nom_prenom: contrib.nom,
          email: contrib.email,
        }),
      });
      const result = await res.json().catch(() => ({}));
      if (!res.ok || !result.success) {
        alert("Erreur : " + (result.error || res.status));
        return;
      }
      map.closePopup();
      await refresh();
    } catch (e) {
      alert("Erreur réseau lors de la mise à jour du lien.");
    }
  };

  const addMarker = (arret) => {
    const marker = L.marker([arret.lat, arret.lng], {
      icon: arret.linked ? BusStop.busStopIcon(BusStop.iconSize) : unlinkedIcon(),
    });
    marker.bindTooltip(escapeHtml(arret.nom), {
      direction: "right",
      offset: [BusStop.iconSize / 2, 0],
    });
    marker.bindPopup(buildPopup(arret), { minWidth: 260, maxWidth: 300 });
    marker.on("popupopen", (e) => {
      const root = e?.popup?.getElement?.();
      const linkBtn = root?.querySelector?.(".link-bus-btn");
      const unlinkBtn = root?.querySelector?.(".unlink-bus-btn");
      linkBtn?.addEventListener("click", () => doLink(arret, "link"), { once: true });
      unlinkBtn?.addEventListener("click", () => doLink(arret, "unlink"), { once: true });
    });
    layer.addLayer(marker);
  };

  async function refresh() {
    let url = `/api/fetch_bus_arrets.php?falaise_id=${falaiseId}`;
    if (map.getZoom() >= MIN_ZOOM) {
      const b = map.getBounds();
      url += `&bbox=${b.getSouth()},${b.getWest()},${b.getNorth()},${b.getEast()}`;
    }
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error("fetch_bus_arrets failed");
      const data = await res.json();
      layer.clearLayers();
      (data.arrets || []).forEach(addMarker);
    } catch (e) {
      console.error("[editor-bus-stops] refresh failed:", e);
    }
  }

  let debounce = null;
  const onMoveEnd = () => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(refresh, 400);
  };
  map.on("moveend", onMoveEnd);

  // Chargement initial
  refresh();

  return {
    refresh,
    destroy() {
      map.off("moveend", onMoveEnd);
      layer.clearLayers();
      map.removeLayer(layer);
    },
  };
}
