/**
 * Calque de tous les arrêts multimodaux (gares TER/TGV, bus, tram, téléphérique)
 * issus de la DB pour l'éditeur de détails falaise.
 *
 * - Affiche les arrêts existants : avec au moins un accès à la falaise (icône colorée par type) vs sans
 *   (icône grisée, même symbole).
 * - Clic sur un arrêt : le popup liste les accès DÉJÀ décrits (vélo / sans vélo, variante, chiffres) et propose
 *   TOUJOURS d'en ajouter un autre : « + Ajouter un accès sans vélo » ou « + Ajouter un accès à vélo », chacun
 *   déplie un mini-formulaire (variante, description, km, D+, D-, trace GPX ; km, D+, D- et GPX obligatoires à
 *   vélo) validé par « Envoyer » (api/link_arret_falaise.php). Une corbeille à côté de chaque accès sans vélo
 *   le supprime, après confirmation (les accès à vélo ne se suppriment pas d'ici).
 * - Recharge sur déplacement de carte (debouncé) ; les arrêts liés sont toujours inclus.
 *
 * @module editor-bus-stops
 */
import { arretDivIcon } from "/js/components/map/arret-icon.js";

const MIN_ZOOM = 11;
const ICON_SIZE = 26;
const UNLINKED_COLOR = "#9ca3af"; // gris

const escapeHtml = (str) =>
  String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

/**
 * @param {L.Map} map
 * @param {Object} opts
 * @param {number} opts.falaiseId
 * @param {function(): Promise<{nom:string,email:string}|null>} opts.ensureContrib
 *        Retourne les infos contributeur (en demandant via modal si besoin), ou null si annulé.
 * @returns {{ refresh: function, destroy: function }}
 */
export function initEditorBusStops(map, { falaiseId, ensureContrib }) {
  const layer = L.layerGroup().addTo(map);

  const ICONES = { velo: "🚲", pied: "🚶" };
  const libelleTexte = (a) => {
    const nom = a.variante || (a.type === "velo" ? "Itinéraire à vélo" : "Accès sans vélo");
    return `${nom}`;
  };
  const libelleAcces = (a) => {
    const nom = a.variante || (a.type === "velo" ? "Itinéraire à vélo" : "Accès sans vélo");
    const chiffres = [
      a.km != null ? `${a.km} km` : null,
      a.dplus != null ? `${a.dplus} D+` : null,
      a.dmoins != null ? `${a.dmoins} D-` : null,
    ]
      .filter(Boolean)
      .join(", ");
    return (
      `${ICONES[a.type] ?? ""} ${escapeHtml(nom)}` +
      (chiffres ? ` <span class="opacity-70">(${chiffres})</span>` : "") +
      (a.public === 2 ? ' <span class="badge badge-warning badge-xs">à valider</span>' : "")
    );
  };

  // Mini-formulaire d'ajout d'un accès (replié) : `type` = "pied" | "velo"
  const formulaire = (type) => {
    const velo = type === "velo";
    return `
      <details class="acces-form text-xs" data-type="${type}" data-testid="acces-form-${type}">
        <summary class="btn btn-xs btn-primary w-full list-none">+ Ajouter un accès ${velo ? "à vélo" : "sans vélo"}</summary>
        <div class="flex flex-col gap-1 mt-2">
          <input class="acces-variante input input-xs w-full" type="text" maxlength="100"
            placeholder="Nom de la variante (optionnel)" aria-label="Nom de la variante">
          <textarea class="acces-description textarea textarea-xs w-full" rows="2"
            placeholder="Description : ${velo ? "surface, trafic, montées…" : "durée, sentier, passages délicats…"}"></textarea>
          <div class="flex gap-1">
            <input class="acces-km input input-xs w-16" type="number" step="0.1" min="0"
              placeholder="km${velo ? " *" : ""}" aria-label="Distance (km)">
            <input class="acces-dplus input input-xs w-16" type="number" min="0"
              placeholder="D+${velo ? " *" : ""}" aria-label="D+ (m)">
            <input class="acces-dmoins input input-xs w-16" type="number" min="0"
              placeholder="D-${velo ? " *" : ""}" aria-label="D- (m)">
          </div>
          <label class="flex flex-col gap-1">
            <span>Trace GPS ${velo ? "(obligatoire)" : "(optionnel)"}, au format GPX</span>
            <input class="acces-gpx file-input file-input-xs w-full" type="file" accept=".gpx">
          </label>
          <button class="acces-envoyer btn btn-xs btn-primary" type="button">Envoyer</button>
        </div>
      </details>`;
  };

  const buildPopup = (arret) => {
    // arret.description est du HTML déjà assaini côté serveur (rt_sanitize_html).
    const desc = arret.description
      ? `<div class="text-sm">${arret.description}</div>`
      : "";
    const acces = Array.isArray(arret.acces) ? arret.acces : [];
    const existants = acces.length
      ? `<div class="text-xs" data-testid="acces-existants">
           <div class="font-semibold">Accès déjà décrits :</div>
           <ul class="list-none pl-0 my-0 flex flex-col gap-0.5">${acces
             .map(
               (a) => `<li class="flex items-start gap-1" data-acces-id="${a.acces_id}">
                 <span class="grow">${libelleAcces(a)}</span>${
                   a.type === "pied"
                     ? `<button type="button" class="supprimer-acces btn btn-ghost btn-xs btn-circle text-error shrink-0"
                          data-acces-id="${a.acces_id}" title="Supprimer cet accès sans vélo"
                          aria-label="Supprimer l'accès sans vélo ${escapeHtml(libelleTexte(a))}">
                          <svg class="w-4 h-4 fill-none stroke-current"><use href="#trash"></use></svg>
                        </button>`
                     : ""
                 }
               </li>`,
             )
             .join("")}</ul>
         </div>`
      : `<div class="text-xs opacity-70" data-testid="acces-existants">Aucun accès décrit entre cet arrêt et cette falaise.</div>`;
    return `
      <div class="flex flex-col gap-2 w-[260px]">
        <div class="font-bold">${escapeHtml(arret.nom)}</div>
        ${desc}
        ${existants}
        ${formulaire("pied")}
        ${formulaire("velo")}
        <a class="link text-xs" target="_blank" rel="noopener"
          href="/ajout/ajout_velo.php?arret_id=${arret.id}&falaise_id=${falaiseId}">Formulaire complet, avec carte</a>
      </div>`;
  };

  // Ajout d'un accès (type "pied" | "velo", lu dans le mini-formulaire `form`) ou suppression d'un accès sans vélo
  // (action "unlink" + `accesId`).
  const doLink = async (arret, action, form = null, type = "pied", accesId = null) => {
    const champ = (cls) => form?.querySelector?.(`.${cls}`)?.value?.trim() ?? "";
    const gpx = action === "link" ? form?.querySelector?.(".acces-gpx")?.files?.[0] : null;
    if (action === "link" && type === "velo") {
      // Mêmes champs obligatoires que le formulaire d'ajout d'accès (le serveur revérifie)
      if (!champ("acces-km") || !champ("acces-dplus") || !champ("acces-dmoins") || !gpx) {
        alert("Accès à vélo : la distance, le D+, le D- et la trace GPX sont obligatoires.");
        return;
      }
    }
    const contrib = await ensureContrib();
    if (!contrib) return;
    try {
      const base = {
        arret_id: arret.id,
        falaise_id: falaiseId,
        action,
        nom_prenom: contrib.nom,
        email: contrib.email,
        ...(accesId ? { acces_id: accesId } : {}),
      };
      const details =
        action === "link"
          ? {
              type,
              // Clé toujours envoyée (même vide) : c'est cette variante exactement, jamais « n'importe laquelle »
              variante: champ("acces-variante"),
              description: champ("acces-description"),
              km: champ("acces-km"),
              dplus: champ("acces-dplus"),
              dmoins: champ("acces-dmoins"),
            }
          : {};
      let init;
      if (gpx) {
        // Avec une trace GPX : multipart (le JSON ne peut pas porter de fichier)
        const fd = new FormData();
        Object.entries({ ...base, ...details }).forEach(([k, v]) => fd.append(k, v));
        fd.append("gpx_file", gpx);
        init = { method: "POST", body: fd };
      } else {
        init = {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ ...base, ...details }),
        };
      }
      const res = await fetch("/api/link_arret_falaise.php", init);
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
    // Coloré dès qu'un accès existe (vélo ou sans vélo), grisé sinon
    const aUnAcces = arret.linked || (Array.isArray(arret.acces) && arret.acces.length > 0);
    const icon = aUnAcces
      ? arretDivIcon(arret.types, ICON_SIZE)
      : arretDivIcon(arret.types, ICON_SIZE, {
          color: UNLINKED_COLOR,
          extraClassName: "vg-arret-unlinked",
        });
    const marker = L.marker([arret.lat, arret.lng], { icon });
    marker.bindTooltip(escapeHtml(arret.nom), {
      direction: "right",
      offset: [ICON_SIZE / 2, 0],
    });
    // Contenu du popup : un ÉLÉMENT créé une fois (et non une chaîne), que Leaflet réutilise tel quel à chaque
    // ouverture et à chaque popup.update() ; avec une chaîne, update() régénérerait le contenu (détail refermé,
    // écouteurs perdus). Les écouteurs sont donc posés ici, une seule fois.
    const root = document.createElement("div");
    root.innerHTML = buildPopup(arret);
    marker.bindPopup(root, { minWidth: 260, maxWidth: 300 });

    const forms = [...root.querySelectorAll(".acces-form")];
    forms.forEach((form) => {
      // Un seul mini-formulaire ouvert à la fois ; la fenêtre change de taille : recadrage du popup
      form.addEventListener("toggle", () => {
        if (form.open) forms.filter((f) => f !== form).forEach((f) => (f.open = false));
        marker.getPopup()?.update();
      });
      form
        .querySelector(".acces-envoyer")
        ?.addEventListener("click", () => doLink(arret, "link", form, form.dataset.type));
    });
    // Corbeille d'un accès sans vélo : confirmation nécessaire
    root.querySelectorAll(".supprimer-acces").forEach((btn) => {
      btn.addEventListener("click", () => {
        const a = (arret.acces ?? []).find((x) => String(x.acces_id) === btn.dataset.accesId);
        const nom = a ? libelleTexte(a) : "cet accès";
        if (!window.confirm(`Supprimer l'accès sans vélo « ${nom} » entre « ${arret.nom} » et cette falaise ?`)) return;
        doLink(arret, "unlink", null, "pied", Number(btn.dataset.accesId));
      });
    });
    layer.addLayer(marker);
  };

  // Tant qu'un popup est ouvert, on ne recrée pas les marqueurs : le recadrage qui suit l'ouverture du popup
  // (autopan) déclencherait sinon un rafraîchissement qui le détruit, et la saisie en cours avec lui.
  let popupOuvert = false;
  map.on("popupopen", () => (popupOuvert = true));
  map.on("popupclose", () => {
    popupOuvert = false;
    onMoveEnd(); // rattrape les déplacements survenus pendant que le popup était ouvert
  });

  async function refresh() {
    if (popupOuvert) return;
    let url = `/api/fetch_arrets.php?falaise_id=${falaiseId}`;
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
