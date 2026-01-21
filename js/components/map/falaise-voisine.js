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
    const visibility = options.visibility || { from: 10 };
    const labelVisibility = options.labelVisibility || { from: 10 };
    const layer = buildLayer(zoneFeature, options);
    layer.properties = zoneFeature.properties;
    const popupContent = buildPopupContent(zoneFeature);
    const basePopupOptions = options.popupOptions || {};
    const popupOptions = {
      ...basePopupOptions,
      closeButton: false,
      className: `${
        basePopupOptions.className ? `${basePopupOptions.className} ` : ""
      }vg-popup`,
    };
    super(map, layer, "falaise_voisine", {
      ...options,
      visibility,
      popupContent,
      popupOptions,
    });
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
    color: "oklch(64.79% 0.1726 249.75)",
    dashArray: "5, 5",
    weight: 2,
  };
  static highlightStyle = {
    color: "oklch(64.79% 0.1726 249.75)",
    dashArray: "5, 5",
    weight: 3,
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
      this.layer.properties.name,
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

// const textPathText = ". ";
// const textPathOptions = {
//   repeat: true,
//   offset: 4,
//   below: false,
//   attributes: {
//     "font-size": "10px",
//     "font-weight": "bold",
//     fill: "#2e8b57",
//   },
// };

const buildLayer = (zoneFeature, options = {}) => {
  let layer;
  layer = L.polygon(
    zoneFeature.geometry.coordinates.map((ring) => {
      if (turf.booleanClockwise(turf.lineString(ring))) {
        ring = ring.reverse();
      }
      return ring.map((coord) => [coord[1], coord[0]]);
    }),
    FalaiseVoisine.style,
  );
  // layer = layer.setText(textPathText, textPathOptions);
  return layer;
};

const buildPopupContent = (zoneFeature) => {
  const properties = zoneFeature.properties || {};
  const falaiseId = properties.falaise_id;

  if (!falaiseId) {
    return undefined;
  }

  const url = `/falaise.php?falaise_id=${encodeURIComponent(falaiseId)}`;

  return `
    <a href="${url}" class="btn btn-sm btn-primary flex flex-row items-center gap-1">
      <span>Voir la fiche falaise
      <svg class="inline-block w-3 h-3 fill-none stroke-current" aria-hidden="true" focusable="false">
        <use href="#external-link"></use>
      </svg></span>
    </a>
  `;
};
