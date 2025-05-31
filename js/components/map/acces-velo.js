import Element from "/js/components/map/element.js";

export default class AccesVelo extends Element {
  static style = {
    color: "indianRed",
    weight: 3,
  };
  static highlightStyle = { ...this.style, weight: 5 };

  constructor(map, accesVeloFeature, options = {}) {
    const visibility = options.visibility || { from: 11 };
    const layer = buildAccesVeloLayer(accesVeloFeature, options);
    layer.properties = accesVeloFeature.properties;
    super(map, layer, "acces_velo", { ...options, visibility });
    this.setupHighlight();
    this.approches = [];
    this.parkings = [];
    this.accessVelos = [];
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const accesVeloFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new AccesVelo(map, accesVeloFeature);
  }
}

const buildAccesVeloLayer = (accesVelo, options = {}) => {
  const line = L.polyline(
    accesVelo.geometry.coordinates.map((coord) => [coord[1], coord[0]]),
    AccesVelo.style
  );
  return line;
};
