import Element from "/js/components/map/element.js";
import SecteurLabel from "/js/components/map/secteur-label.js";

export default class Secteur extends Element {
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

  static style = {
    color: "#333",
    weight: 6,
  };
  static highlightStyle = {
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
    return Secteur.style;
  };
  getHighlightStyle = () => {
    const isPolygon = this.layer instanceof L.Polygon;
    if (isPolygon) {
      return Secteur.polygonHighlightStyle;
    }
    return Secteur.highlightStyle;
  };

  highlight(e) {
    this.layer.setStyle(this.getHighlightStyle());
  }
  unhighlight() {
    this.layer.setStyle(this.getStyle());
  }

  cleanUp() {
    if (this.label) {
      this.label.cleanUp();
    }
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
      Secteur.style
    );
    layer.setText("-", textPathOptions);
  }
  return layer;
};
