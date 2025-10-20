export const ignTiles = L.tileLayer(
  "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
  {
    maxZoom: 19,
    minZoom: 0,
    attribution: "IGN-F/Geoportail",
    crossOrigin: true,
  }
);
export const ignOrthoTiles = L.tileLayer(
  "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
  {
    maxZoom: 18,
    minZoom: 0,
    tileSize: 256,
    attribution: "IGN-F/Geoportail",
    crossOrigin: true,
  }
);
export const landscapeTiles = L.tileLayer(
  "https://{s}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
  {
    maxZoom: 19,
    minZoom: 0,
    attribution:
      '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
    crossOrigin: true,
  }
);
export const outdoorsTiles = L.tileLayer(
  "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
  {
    maxZoom: 19,
    minZoom: 0,
    attribution:
      '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
    crossOrigin: true,
  }
);
export const baseMaps = {
  Landscape: landscapeTiles,
  IGNv2: ignTiles,
  Satellite: ignOrthoTiles,
  Outdoors: outdoorsTiles,
};

export const initVGMap = (id, center = [44.9264, 6.6305], zoom = 13) => {
  const map = L.map(id, {
    layers: [landscapeTiles],
    center,
    zoom,
    fullscreenControl: true,
    zoomSnap: 1,
  });
  L.control
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
  return map;
};
