import Element from "/js/components/map/element.js";
import { parseList } from "/js/components/utils/lists.js";

export default class AccesVelo extends Element {
  static style = {
    color: "indianRed",
    weight: 3,
  };
  static highlightStyle = { ...this.style, weight: 5 };

  /**
   * Creates an instance of AccesVelo.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} accesVeloFeature - The GeoJSON feature representing the object.
   * @param {Object} [accesVeloFeature.geometry] - The geometry of the object.
   * @param {Array} [accesVeloFeature.geometry.coordinates] - The coordinates of the object.
   * @param {Object} [accesVeloFeature.properties] - The properties of the object.
   * @param {string} [accesVeloFeature.properties.name] - The name of the object.
   * @param {string} [accesVeloFeature.properties.description] - The description of the object.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, accesVeloFeature, options = {}) {
    const visibility = options.visibility || { from: 12 };
    const layer = buildAccesVeloLayer(accesVeloFeature, options);
    layer.properties = accesVeloFeature.properties;
    super(map, layer, "acces_velo", { ...options, visibility });
    this.setupHighlight();
    this.approches = [];
    this.parkings = [];
    this.secteurs = [];
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const accesVeloFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new AccesVelo(map, accesVeloFeature);
  }

  getDependencies() {
    return [this.secteurs, this.parkings, this.approches];
  }

  updateAssociations(features) {
    const name = this.layer.properties.name;
    this.parkings = features.filter(
      (feature) =>
        feature.type === "parking" &&
        parseList(feature.layer.properties.itineraire_acces).includes(name)
    );
    this.approches = this.parkings.flatMap((parking) =>
      features.filter(
        (feature) =>
          feature.type === "approche" &&
          parseList(feature.layer.properties.parking).includes(
            parking.layer.properties.name
          )
      )
    );
    this.secteurs = this.parkings.flatMap((parking) =>
      features.filter(
        (feature) =>
          feature.type === "secteur" &&
          parseList(feature.layer.properties.parking).includes(
            parking.layer.properties.name
          )
      )
    );
  }
}

const buildAccesVeloLayer = (accesVelo, options = {}) => {
  if (accesVelo.geometry.type === "LineString") {
    return L.polyline(
      accesVelo.geometry.coordinates.map((coord) => [coord[1], coord[0]]),
      AccesVelo.style
    );
  } else if (accesVelo.geometry.type === "MultiLineString") {
    return L.polyline(
      accesVelo.geometry.coordinates.map((line) =>
        line.map((coord) => [coord[1], coord[0]])
      ),
      AccesVelo.style
    );
  }
};
