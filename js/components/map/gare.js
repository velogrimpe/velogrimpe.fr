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
        visibility: { from: 7, to: Gare.labelFromZoom - 1 },
      },
      {
        // même icône, avec le nom de la gare affiché en permanence
        layer: buildGareMarker(feature, options, true),
        visibility: { from: Gare.labelFromZoom - 1 },
      },
    ];

    super(map, layersWithScale, "gare", { ...options });
  }

  static iconSize = 24;
  static labelFromZoom = 11; // aligné sur les noms des falaises (falaise-voisine.js : from: 10, soit zoom >= 11)
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

const buildGareMarker = (feature, options = {}, withLabel = false) => {
  const marker = L.marker(feature.gare_latlng.split(",").map(parseFloat), {
    icon: Gare.gareIcon(Gare.iconSize),
  });
  if (withLabel && feature.gare_nom) {
    marker.bindTooltip(feature.gare_nom, {
      permanent: true,
      direction: "bottom",
      offset: [0, Gare.iconSize / 2],
      className: "vg-gare-label",
    });
  }
  return marker;
};
const buildDotMarker = (feature, options = {}) => {
  const latlng = feature.gare_latlng.split(",").map(parseFloat);
  const marker = Gare.gareCircleIcon(latlng, Gare.circleRadius);
  return marker;
};
