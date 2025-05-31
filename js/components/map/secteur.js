import { parseList } from "/js/components/utils/lists.js";
import Element from "/js/components/map/element.js";
import SecteurLabel from "/js/components/map/secteur-label.js";

export default class Secteur extends Element {
  /**
   * Creates an instance of Secteur.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} secteurFeature - The GeoJSON feature representing the object.
   * @param {Object} [secteurFeature.geometry] - The geometry of the object.
   * @param {Array} [secteurFeature.geometry.coordinates] - The coordinates of the object.
   * @param {Object} [secteurFeature.properties] - The properties of the object.
   * @param {string} [secteurFeature.properties.name] - The name of the object.
   * @param {string} [accesVeloFeature.properties.description] - The description of the object.
   * @param {Object} [secteurFeature.properties.parking] - The parking associated with the object.
   * @param {Object} [secteurFeature.properties.approche] - The approach associated with the object.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, secteurFeature, options = {}) {
    const visibility = options.visibility || { from: 12 };
    const labelVisibility = options.labelVisibility || { from: 14 };
    const layer = buildSecteurLayer(secteurFeature, options);
    layer.properties = secteurFeature.properties;
    super(map, layer, "secteur", { ...options, visibility });
    if (secteurFeature.properties.name) {
      this.label = new SecteurLabel(map, secteurFeature, this, {
        ...options,
        visibility: labelVisibility,
      });
    }
    this.setupHighlight();
    this.approches = [];
    this.parkings = [];
    this.accessVelos = [];
  }

  static lineStyle = {
    color: "#333",
    weight: 6,
  };
  static lineHighlightStyle = {
    color: "darkred",
    weight: 8,
  };
  static polygonStyle = {
    color: "#333",
    weight: 1,
  };
  static polygonHighlightStyle = {
    color: "darkred",
    weight: 2,
  };

  getStyle = () => {
    const isPolygon = this.layer instanceof L.Polygon;
    if (isPolygon) {
      return Secteur.polygonStyle;
    }
    return Secteur.lineStyle;
  };
  getHighlightStyle = () => {
    const isPolygon = this.layer instanceof L.Polygon;
    if (isPolygon) {
      return Secteur.polygonHighlightStyle;
    }
    return Secteur.lineHighlightStyle;
  };

  highlight(e, propagate) {
    this.layer.setStyle(this.getHighlightStyle());
    super.highlight(e, propagate);
  }
  unhighlight(propagate) {
    this.layer.setStyle(this.getStyle());
    super.unhighlight(propagate);
  }

  cleanUp() {
    if (this.label) {
      this.label.cleanUp();
    }
  }

  getDependencies() {
    return [this.approches, this.parkings, this.accessVelos];
  }

  updateAssociations(features) {
    const parkings = parseList(this.layer.properties.parking);
    const approches = parseList(this.layer.properties.approche);
    this.parkings = features.filter(
      (feature) =>
        feature.type === "parking" &&
        parkings.includes(feature.layer.properties.name)
    );
    const accessVelos = this.parkings.flatMap((pk) =>
      parseList(pk.layer.properties.itineraire_acces)
    );
    this.accessVelos = features.filter(
      (feature) =>
        feature.type === "acces_velo" &&
        accessVelos.includes(feature.layer.properties.name)
    );
    this.approches = features.filter(
      (feature) =>
        feature.type === "approche" &&
        approches.includes(feature.layer.properties.name)
    );
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const secteurFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new Secteur(map, secteurFeature);
  }

  static isInvalidSecteur = (secteur) => {
    return (
      !secteur.geometry ||
      !["Polygon", "LineString"].includes(secteur.geometry.type) ||
      secteur.geometry.coordinates.length === 0 ||
      secteur.geometry.coordinates[0].length === 0
    );
  };
}

const textPathOptions = { repeat: true, offset: 8, below: false };

const buildSecteurLayer = (secteurFeature, options = {}) => {
  let layer;
  if (secteurFeature.geometry.type === "Polygon") {
    layer = L.polygon(
      secteurFeature.geometry.coordinates.map((rings) => {
        return rings.map((coord) => [coord[1], coord[0]]);
      }),
      Secteur.polygonStyle
    );
  } else if (
    secteurFeature.geometry.type === "LineString" ||
    secteurFeature.geometry.type === "MultiLineString"
  ) {
    layer = L.polyline(
      secteurFeature.geometry.coordinates.map((coord) => [coord[1], coord[0]]),
      Secteur.lineStyle
    );
    layer.setText("-", textPathOptions);
  }
  return layer;
};
