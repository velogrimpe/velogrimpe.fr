/**
 * Carte de saisie réutilisable pour les formulaires d'ajout (falaise, arrêt de bus...).
 * Utilise le bundle map global (variable globale `L`, chargée via map_bundle_js).
 *
 * Crée une carte Leaflet avec :
 * - fonds de carte (Landscape, OpenCycleMap, IGN, Satellite, Outdoors)
 * - contrôle de localisation + échelle
 * - un contrôle de recherche de localité via Nominatim (centre la carte, sans marqueur)
 *
 * Usage :
 *   const { map, layerControl } = createAjoutMap("map");
 */
export function createAjoutMap(elId) {
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

  const baseMaps = {
    Landscape: landscapeTiles,
    OpenCycleMap: opencyclemapTiles,
    IGNv2: ignTiles,
    Satellite: ignOrthoTiles,
    Outdoors: outdoorsTiles,
  };

  const map = L.map(elId, {
    layers: [landscapeTiles],
    center: [45.1234, 3.2355],
    zoom: 5,
    fullscreenControl: true,
    zoomSnap: 0.5,
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
  const SearchControl = L.Control.extend({
    options: { position: "topright" },
    onAdd: function () {
      const container = L.DomUtil.create(
        "div",
        "leaflet-bar bg-base-100 rounded-md p-1 not-prose border-0",
      );
      container.style.width = "230px";
      container.style.boxShadow = "0 1px 5px rgba(0,0,0,0.4)";
      container.innerHTML = `
        <div style="position:relative;">
          <input type="text" autocomplete="off" placeholder="Centre la carte sur…"
            class="input input-bordered input-xs w-full" style="padding-right:1.5rem;"
            aria-label="Centre la carte sur" />
          <span data-role="spinner" class="text-slate-400"
            style="position:absolute;right:.4rem;top:50%;transform:translateY(-50%);display:none;">
            <span class="loading loading-spinner loading-xs"></span>
          </span>
          <div data-role="results" class="bg-base-100 border border-base-300"
            style="position:absolute;left:0;right:0;top:100%;margin-top:.25rem;max-height:13rem;
              overflow-y:auto;overflow-x:hidden;z-index:11000;display:none;border-radius:.375rem;
              box-shadow:0 4px 12px rgba(0,0,0,.25);"></div>
        </div>`;
      L.DomEvent.disableClickPropagation(container);
      L.DomEvent.disableScrollPropagation(container);

      const input = container.querySelector("input");
      const results = container.querySelector('[data-role="results"]');
      const spinner = container.querySelector('[data-role="spinner"]');
      let debounce = null;
      let lastController = null;
      let activeIndex = -1;

      function hideResults() {
        results.style.display = "none";
        results.innerHTML = "";
        activeIndex = -1;
      }
      function showResults() {
        results.style.display = "block";
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

  return { map, layerControl };
}
