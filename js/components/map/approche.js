import Element from "/js/components/map/element.js";

export default class Approche extends Element {
  static style = {
    color: "blue",
    weight: 2,
    dashArray: "5 5",
  };
  static highlightStyle = {
    color: "DodgerBlue",
    weight: 6,
    dashArray: "10",
  };

  constructor(map, approcheFeature, options = {}) {
    const visibility = options.visibility || { from: 12 };
    const layer = buildApprocheLayer(approcheFeature, options);
    layer.properties = approcheFeature.properties;
    super(map, layer, "approche", { ...options, visibility });
    this.setupHighlight();
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const approcheFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new Approche(map, approcheFeature);
  }
}

const buildApprocheLayer = (approche, options = {}) => {
  return L.polyline(
    approche.geometry.coordinates.map((coord) => [coord[1], coord[0]]),
    Approche.style
  );
};
