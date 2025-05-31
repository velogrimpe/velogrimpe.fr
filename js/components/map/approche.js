import Element from "/js/components/map/element.js";
import { parseList } from "/js/components/utils/lists.js";

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

  /**
   * Creates an instance of Approche.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} approcheFeature - The GeoJSON feature representing the object.
   * @param {Object} [approcheFeature.geometry] - The geometry of the object.
   * @param {Array} [approcheFeature.geometry.coordinates] - The coordinates of the object.
   * @param {Object} [approcheFeature.properties] - The properties of the object.
   * @param {string} [approcheFeature.properties.name] - The name of the object.
   * @param {string} [accesVeloFeature.properties.description] - The description of the object.
   * @param {Object} [approcheFeature.properties.parking] - The parking associated with the object.
   * @param {Object} [approcheFeature.properties.accessVelos] - The bicycle access associated with the object.
   * @param {Object} [approcheFeature.properties.approche] - the object associated with the feature.
   * @param {Object} [approcheFeature.properties.secteur] - The sector associated with the object.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, approcheFeature, options = {}) {
    const visibility = options.visibility || { from: 12 };
    const layer = buildApprocheLayer(approcheFeature, options);
    layer.properties = approcheFeature.properties;
    super(map, layer, "approche", { ...options, visibility });
    this.setupHighlight();
    this.secteurs = [];
    this.parkings = [];
    this.accessVelos = [];
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const approcheFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new Approche(map, approcheFeature);
  }

  getDependencies() {
    return [this.secteurs, this.parkings, this.accessVelos];
  }

  updateAssociations(features) {
    const name = this.layer.properties.name;
    const parkings = parseList(this.layer.properties.parking);
    this.secteurs = features.filter(
      (feature) =>
        feature.type === "secteur" &&
        parseList(feature.layer.properties.approche).includes(name)
    );
    this.parkings = features.filter(
      (feature) =>
        feature.type === "parking" &&
        parkings.includes(feature.layer.properties.name)
    );
    const accessVelos = this.parkings.flatMap((pk) =>
      parseList(pk.layer.properties.accessVelo)
    );
    this.accessVelos = features.filter(
      (feature) =>
        feature.type === "acces_velo" &&
        accessVelos.includes(feature.layer.properties.name)
    );
  }
}

const buildApprocheLayer = (approche, options = {}) => {
  return L.polyline(
    approche.geometry.coordinates.map((coord) => [coord[1], coord[0]]),
    Approche.style
  );
};
