/**
 * Carte de saisie réutilisable pour les formulaires d'ajout (falaise, arrêt de bus...).
 * Utilise le bundle map global (variable globale `L`, chargée via map_bundle_js).
 *
 * Crée une carte Leaflet avec :
 * - fonds de carte (Landscape, OpenCycleMap, IGN, Satellite, Outdoors)
 * - contrôle de localisation + échelle
 * - un contrôle de recherche de localité via Nominatim (centre la carte, sans
 *   marqueur) : une cible compacte qui se déploie en champ au survol / au clic
 * - un bouton d'action optionnel (`opts.searchButton`), empilé sous la
 *   recherche : une icône (`opts.searchButton.html`, de préférence un <img> que
 *   l'appelant peut remplacer par un spinner) dont le libellé apparaît au survol
 *
 * Usage :
 *   const { map, layerControl, searchButton } = createAjoutMap("map", {
 *     searchButton: { html: "<img .../>", label: "Recherche Arrêt Overpass", title: "..." },
 *   });
 */
const CROSSHAIR_ICON =
  "data:image/svg+xml," +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2.5"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/></svg>',
  );

/** Icône « carte dépliée + loupe », utilisable comme `opts.searchButton.html`. */
export const MAP_SEARCH_ICON_HTML =
  '<img alt="" src="data:image/svg+xml,' +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 22V6l6-2.5 6 2.5 6-2.5v8"/><path d="M9 3.5v16M15 6.500v3"/><circle cx="16.5" cy="16.5" r="3.5"/><path d="m19 19 3 3"/></svg>',
  ) +
  '" style="width:20px;height:20px;" />';

function ensureControlStyles() {
  if (document.getElementById("vg-ajout-map-styles")) return;
  const style = document.createElement("style");
  style.id = "vg-ajout-map-styles";
  style.textContent = `
    .vg-ctl { background:#fff; display:flex; align-items:center; height:34px;
      color:#222; font-size:.75rem; position:relative; }
    .vg-ctl-btn { display:flex; align-items:center; justify-content:center;
      flex:none; min-width:30px; height:100%; padding:0; border:0;
      background:transparent; cursor:pointer; color:inherit; font:inherit; }
    .vg-ctl-btn:disabled { opacity:.5; cursor:not-allowed; }
    .vg-ctl-btn img { width:20px; height:20px; margin:0 5px; }
    .vg-ctl-btn > .loading { margin:0 7px; }
    .vg-ctl-reveal { max-width:0; opacity:0; overflow:hidden; white-space:nowrap;
      transition:max-width .2s ease, opacity .2s ease; }
    .vg-ctl-action { cursor:pointer; }
    .vg-ctl-action:hover .vg-ctl-reveal,
    .vg-ctl-action:focus-within .vg-ctl-reveal { max-width:220px; opacity:1; }
    .vg-ctl-action .vg-ctl-reveal { padding-left:0; }
    .vg-ctl-action:hover .vg-ctl-reveal,
    .vg-ctl-action:focus-within .vg-ctl-reveal { padding-left:.6rem; }
    .vg-ctl-search.is-open .vg-ctl-reveal { max-width:200px; opacity:1; }
    .vg-ctl-search .vg-ctl-reveal { position:relative; }
    .vg-ctl-search input { width:190px; height:100%; border:0; outline:0;
      background:transparent; padding:0 1.5rem 0 .6rem; font-size:.8rem; }
  `;
  document.head.appendChild(style);
}

export function createAjoutMap(elId, opts = {}) {
  ensureControlStyles();
  const ignTiles = L.tileLayer(
    "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution: "IGN-F/Geoportail",
      crossOrigin: true,
    },
  );
  const ignOrthoTiles = L.tileLayer(
    "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
    {
      maxZoom: 18,
      minZoom: 0,
      tileSize: 256,
      attribution: "IGN-F/Geoportail",
      crossOrigin: true,
    },
  );
  const landscapeTiles = L.tileLayer(
    "https://{s}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution:
        '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    },
  );
  const opencyclemapTiles = L.tileLayer(
    "https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution:
        '<a href="http://www.thunderforest.com/opencyclemap/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    },
  );
  const outdoorsTiles = L.tileLayer(
    "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution:
        '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    },
  );
  const transportTiles = L.tileLayer(
    "https://{s}.tile.thunderforest.com/transport/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution:
        '<a href="http://www.thunderforest.com/transport/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    },
  );

  const baseMaps = {
    Landscape: landscapeTiles,
    OpenCycleMap: opencyclemapTiles,
    IGNv2: ignTiles,
    Satellite: ignOrthoTiles,
    Outdoors: outdoorsTiles,
    Transports: transportTiles,
  };

  const map = L.map(elId, {
    layers: [landscapeTiles],
    center: [45.1234, 3.2355],
    zoom: 5,
    fullscreenControl: true,
  });
  L.control.locate().addTo(map);
  const layerControl = L.control
    .layers(baseMaps, undefined, { position: "topleft", size: 22 })
    .addTo(map);
  L.control
    .scale({
      position: "bottomright",
      metric: true,
      imperial: false,
      maxWidth: 125,
    })
    .addTo(map);

  // Contrôle de recherche d'une localité via Nominatim (instance publique OSM).
  let searchButtonEl = null;
  const SearchControl = L.Control.extend({
    options: { position: "topright" },
    onAdd: function () {
      const container = L.DomUtil.create(
        "div",
        "leaflet-bar vg-ctl vg-ctl-search not-prose",
      );
      // Marge supplémentaire pour laisser la place au badge d'aide
      // ("Cliquez sur la carte pour placer...") centré en haut de la carte.
      container.style.marginTop = "44px";

      container.innerHTML = `
        <div class="vg-ctl-reveal">
          <input type="text" autocomplete="off" placeholder="Centre la carte sur…"
            aria-label="Centre la carte sur" />
          <span data-role="spinner" class="text-slate-400"
            style="position:absolute;right:.4rem;top:50%;transform:translateY(-50%);display:none;">
            <span class="loading loading-spinner loading-xs"></span>
          </span>
        </div>
        <button type="button" class="vg-ctl-btn" title="Centrer la carte sur un lieu"
          aria-label="Centrer la carte sur un lieu">
          <img alt="" src="${CROSSHAIR_ICON}" />
        </button>
        <div data-role="results" class="bg-base-100 border border-base-300"
          style="position:absolute;left:0;right:0;top:100%;margin-top:.25rem;max-height:13rem;
            overflow-y:auto;overflow-x:hidden;z-index:11000;display:none;border-radius:.375rem;
            box-shadow:0 4px 12px rgba(0,0,0,.25);"></div>`;

      L.DomEvent.disableClickPropagation(container);
      L.DomEvent.disableScrollPropagation(container);

      const input = container.querySelector("input");
      const results = container.querySelector('[data-role="results"]');
      const spinner = container.querySelector('[data-role="spinner"]');
      let debounce = null;
      let lastController = null;
      const toggle = container.querySelector(".vg-ctl-btn");
      let activeIndex = -1;
      let hovered = false;

      // Déployé tant que la souris survole, que le champ a le focus ou qu'une
      // saisie / des résultats sont en cours.
      function syncOpen() {
        const keep =
          hovered ||
          document.activeElement === input ||
          input.value !== "" ||
          results.style.display !== "none";
        container.classList.toggle("is-open", keep);
      }
      container.addEventListener("mouseenter", () => {
        hovered = true;
        syncOpen();
      });
      container.addEventListener("mouseleave", () => {
        hovered = false;
        syncOpen();
      });
      input.addEventListener("focus", syncOpen);
      input.addEventListener("blur", () => setTimeout(syncOpen, 0));
      toggle.addEventListener("click", () => {
        hovered = true;
        syncOpen();
        input.focus();
      });

      function hideResults() {
        results.style.display = "none";
        results.innerHTML = "";
        activeIndex = -1;
        syncOpen();
      }
      function showResults() {
        results.style.display = "block";
        syncOpen();
      }
      function getOptions() {
        return Array.from(results.querySelectorAll("button"));
      }
      function setActive(idx) {
        const options = getOptions();
        if (options.length === 0) return;
        activeIndex = (idx + options.length) % options.length;
        options.forEach((opt, i) => {
          opt.style.backgroundColor =
            i === activeIndex ? "rgba(0,0,0,.08)" : "";
          if (i === activeIndex) opt.scrollIntoView({ block: "nearest" });
        });
      }
      function doSearch(query) {
        if (lastController) lastController.abort();
        lastController = new AbortController();
        spinner.style.display = "inline-block";
        const url =
          "https://nominatim.openstreetmap.org/search?format=jsonv2" +
          "&limit=6&countrycodes=fr&addressdetails=1&q=" +
          encodeURIComponent(query);
        fetch(url, {
          signal: lastController.signal,
          headers: { "Accept-Language": "fr" },
        })
          .then((r) =>
            r.ok ? r.json() : Promise.reject(new Error("nominatim failed")),
          )
          .then((items) => {
            spinner.style.display = "none";
            if (!Array.isArray(items) || items.length === 0) {
              results.innerHTML =
                '<div style="padding:.375rem .5rem;font-size:.75rem;" class="text-slate-400">Aucun résultat</div>';
              showResults();
              return;
            }
            results.innerHTML = "";
            activeIndex = -1;
            items.forEach((item) => {
              const a = document.createElement("button");
              a.type = "button";
              a.className = "text-left cursor-pointer";
              a.style.cssText =
                "display:block;width:100%;max-width:100%;padding:.375rem .5rem;" +
                "font-size:.75rem;line-height:1.25;white-space:nowrap;overflow:hidden;" +
                "text-overflow:ellipsis;border:0;background:transparent;";
              a.addEventListener("mouseenter", () => {
                a.style.backgroundColor = "rgba(0,0,0,.08)";
              });
              a.addEventListener("mouseleave", () => {
                a.style.backgroundColor = "";
              });
              a.textContent = item.display_name;
              a.title = item.display_name;
              a.addEventListener("click", function (ev) {
                // Empêche le clic de se propager à la carte (placement de marqueur)
                L.DomEvent.stop(ev);
                const lat = parseFloat(item.lat);
                const lng = parseFloat(item.lon);
                if (!isNaN(lat) && !isNaN(lng)) {
                  if (item.boundingbox && item.boundingbox.length === 4) {
                    const bb = item.boundingbox.map(parseFloat);
                    map.fitBounds(
                      [
                        [bb[0], bb[2]],
                        [bb[1], bb[3]],
                      ],
                      { maxZoom: 14 },
                    );
                  } else {
                    map.setView([lat, lng], 13);
                  }
                }
                input.value = "";
                hideResults();
              });
              results.appendChild(a);
            });
            showResults();
          })
          .catch((err) => {
            spinner.style.display = "none";
            if (err.name !== "AbortError") hideResults();
          });
      }

      input.addEventListener("input", function () {
        const query = input.value.trim();
        if (debounce) clearTimeout(debounce);
        if (query.length < 3) {
          hideResults();
          return;
        }
        debounce = setTimeout(() => doSearch(query), 600);
      });
      input.addEventListener("keydown", function (e) {
        const isOpen =
          results.style.display !== "none" && getOptions().length > 0;
        switch (e.key) {
          case "ArrowDown":
            if (!isOpen) return;
            e.preventDefault();
            setActive(activeIndex + 1);
            break;
          case "ArrowUp":
            if (!isOpen) return;
            e.preventDefault();
            setActive(activeIndex - 1);
            break;
          case "Enter":
            // Toujours empêcher la soumission du formulaire parent depuis ce champ
            e.preventDefault();
            if (!isOpen) return;
            getOptions()[activeIndex >= 0 ? activeIndex : 0].click();
            break;
          case "Escape":
            input.value = "";
            hideResults();
            break;
        }
      });

      return container;
    },
  });
  map.addControl(new SearchControl());

  // Bouton d'action optionnel (ex: recherche Overpass), rendu comme un
  // second contrôle Leaflet, empilé sous le champ de recherche.
  if (opts.searchButton) {
    const SearchButtonControl = L.Control.extend({
      options: { position: "topright" },
      onAdd: function () {
        const container = L.DomUtil.create(
          "div",
          "leaflet-bar vg-ctl vg-ctl-action not-prose",
        );

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "vg-ctl-btn";
        const baseTitle = opts.searchButton.title || "";
        btn.title = baseTitle;
        btn.setAttribute(
          "aria-label",
          opts.searchButton.ariaLabel || opts.searchButton.label || "",
        );
        btn.innerHTML =
          `<span class="vg-ctl-reveal">${opts.searchButton.label || ""}</span>` +
          (opts.searchButton.html || MAP_SEARCH_ICON_HTML);
        container.appendChild(btn);

        // Désactive le bouton tant que le zoom courant n'est pas suffisant
        // pour la recherche (trop de résultats / requête trop lourde), et
        // explique pourquoi via le title.
        if (typeof opts.searchButton.minZoom === "number") {
          const minZoom = opts.searchButton.minZoom;
          const updateZoomState = () => {
            const tooLow = map.getZoom() < minZoom;
            btn.disabled = tooLow;
            btn.title = tooLow
              ? `Zoomez davantage (niveau ${minZoom} minimum) pour activer cette recherche`
              : baseTitle;
          };
          map.on("zoomend", updateZoomState);
          updateZoomState();
        }

        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.disableScrollPropagation(container);
        // disableClickPropagation retrouve le conteneur en remontant depuis
        // e.target ; les appelants remplacent l'icône par un spinner pendant le
        // clic, la cible est alors détachée et le clic atteindrait la carte.
        L.DomEvent.on(container, "click", L.DomEvent.stopPropagation);

        searchButtonEl = btn;
        return container;
      },
    });
    map.addControl(new SearchButtonControl());
  }

  return { map, layerControl, searchButton: searchButtonEl };
}
