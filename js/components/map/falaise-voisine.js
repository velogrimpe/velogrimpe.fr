import { parseList } from "/js/components/utils/lists.js";
import Element from "/js/components/map/element.js";
import FalaiseVoisineLabel from "/js/components/map/falaise-voisine-label.js";

export default class FalaiseVoisine extends Element {
  /**
   * Creates an instance of FalaiseVoisine.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} zoneFeature - The GeoJSON feature representing the object.
   * @param {Object} [zoneFeature.geometry] - The geometry of the object.
   * @param {Array} [zoneFeature.geometry.coordinates] - The coordinates of the object.
   * @param {Object} [zoneFeature.properties] - The properties of the object.
   * @param {string} [zoneFeature.properties.name] - The name of the object.
   * @param {string} [zoneFeature.properties.id] - The name of the object.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, zoneFeature, options = {}) {
    const visibility = options.visibility || { from: 13 };
    const labelVisibility = options.labelVisibility || { from: 14 };
    const layer = buildLayer(zoneFeature, options);
    layer.properties = zoneFeature.properties;
    super(map, layer, "falaise_voisine", { ...options, visibility });
    this.options = options;
    if (zoneFeature.properties.name) {
      this.label = new FalaiseVoisineLabel(map, zoneFeature, this, {
        ...options,
        visibility: labelVisibility,
      });
    }
    this.setupHighlight();
  }

  static style = {
    color: "#2e8b57",
    weight: 1,
  };
  static highlightStyle = {
    color: "darkgreen",
    weight: 2,
  };

  cleanUp() {
    if (this.label) {
      this.label.cleanUp();
    }
  }

  getDependencies() {
    return [];
  }

  updateLabel() {
    console.debug(
      "FalaiseVoisine.updateLabel",
      this.label,
      this.layer.properties.name
    );
    if (this.label && this.layer.properties.name) {
      this.label.updateLabel();
    } else {
      const feature = {
        ...this.layer.toGeoJSON(),
        properties: this.layer.properties,
      };
      if (!feature.properties.name) {
        if (this.label) {
          this.label.cleanUp();
        }
        this.label = undefined;
        return;
      }
      this.label = new FalaiseVoisineLabel(this.map, feature, this, {
        ...this.options,
      });
    }
  }

  updateAssociations(features) {}

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const zoneFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new FalaiseVoisine(map, zoneFeature);
  }
}

const textPathText = "- ";
const textPathOptions = {
  repeat: true,
  offset: 10,
  below: false,
  attributes: {
    "font-size": "14px",
    "font-weight": "bold",
    fill: "#2e8b57",
  },
};

const buildLayer = (zoneFeature, options = {}) => {
  let layer;
  layer = L.polygon(
    zoneFeature.geometry.coordinates.map((ring) => {
      if (turf.booleanClockwise(turf.lineString(ring))) {
        ring = ring.reverse();
      }
      return ring.map((coord) => [coord[1], coord[0]]);
    }),
    FalaiseVoisine.style
  );
  layer.setText(textPathText, textPathOptions);
  return layer;
};
