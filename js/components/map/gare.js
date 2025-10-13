import { parseList } from "/js/components/utils/lists.js";
import MultiScaleElement from "/js/components/map/multi-scale-element.js";

export default class Gare extends MultiScaleElement {
  /**
   * Creates an instance of Gare.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} feature - The GeoJSON feature representing the object.
   * @param {Object} [feature.gare_latlng] - LatLng as string
   * @param {Object} [feature.gare_name] - Name of the gare.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, feature, options = {}) {
    const dotMarker = buildDotMarker(feature, options);
    const iconMarker = buildGareMarker(feature, options);
    const layersWithScale = [
      {
        layer: dotMarker,
        visibility: { from: 0, to: 7 },
      },
      {
        layer: iconMarker,
        visibility: { from: 7 },
      },
    ];

    super(map, layersWithScale, "gare", { ...options });
  }

  static iconSize = 24;
  static circleRadius = 3;

  static gareIcon = (size = 24) => {
    return L.icon({
      iconUrl: "/images/map/icone_train_carte.png",
      className: "train-icon bgwhite",
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
    });
  };
  static gareCircleIcon(latlng, radius = 3) {
    return L.circleMarker(latlng, {
      radius,
      stroke: true,
      color: "#fff",
      weight: 1,
      fill: true,
      fillColor: "black",
      fillOpacity: 1,
    });
  }

  getDependencies() {
    return [];
  }

  updateAssociations(features) {}
}

const buildGareMarker = (feature, options = {}) => {
  const marker = L.marker(feature.gare_latlng.split(",").map(parseFloat), {
    icon: Gare.gareIcon(Gare.iconSize),
  });
  return marker;
};
const buildDotMarker = (feature, options = {}) => {
  const latlng = feature.gare_latlng.split(",").map(parseFloat);
  const marker = Gare.gareCircleIcon(latlng, Gare.circleRadius);
  return marker;
};
