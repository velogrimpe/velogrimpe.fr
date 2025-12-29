import { parseList } from "/js/components/utils/lists.js";
import Element from "/js/components/map/element.js";

export default class BusStop extends Element {
  /**
   * Creates an instance of BusStop.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} busStopFeature - The GeoJSON feature representing the object.
   * @param {Object} [busStopFeature.geometry] - The geometry of the object.
   * @param {Array} [busStopFeature.geometry.coordinates] - The coordinates of the object.
   * @param {Object} [busStopFeature.properties] - The properties of the object.
   * @param {string} [busStopFeature.properties.name] - The name of the object.
   * @param {string} [busStopFeature.properties.description] - The description of the object.
   * @param {Object} [busStopFeature.properties.itineraire_acces] - The bicycle access associated with the object.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, busStopFeature, options = {}) {
    const visibility = options.visibility || { from: 12 };
    const layer = buildBusStopMarker(busStopFeature, options);
    layer.properties = busStopFeature.properties;
    const name = layer.properties?.name || "Arrêt de bus";
    const desc = layer.properties?.description || "";
    const html =
      `<div class=\"max-w-[260px]\">` +
      `  <div class=\"font-bold\">${name}</div>` +
      `  ${desc ? `<div class=\"text-sm\">${desc}</div>` : ""}` +
      `</div>`;
    super(map, layer, "bus_stop", {
      ...options,
      visibility,
      tooltipContent: html,
      tooltipOptions: {
        direction: "right",
        offset: [BusStop.iconSize / 2, BusStop.iconSize / 2],
        maxWidth: 260,
        className: "vg-tooltip vg-bus-stop-tooltip",
      },
    });
    this.setupHighlight();
    this.approches = [];
    this.secteurs = [];
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const busStopFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new BusStop(map, busStopFeature);
  }

  static iconSize = 24;
  static busStopIcon(size) {
    return L.icon({
      iconUrl: "/images/map/bus.png",
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
      tooltipAnchor: [0, -size / 2],
      popupAnchor: [0, -size / 2],
      className: "vg-bus-stop-icon border border-white border-2 rounded-lg",
    });
  }

  highlight(event, propagate) {
    this.layer.setIcon(BusStop.busStopIcon(Math.round(BusStop.iconSize * 1.2)));
    super.highlight(event, propagate);
  }
  unhighlight(propagate) {
    this.layer.setIcon(BusStop.busStopIcon(BusStop.iconSize));
    super.unhighlight(propagate);
  }

  getDependencies() {
    return [this.secteurs, this.approches];
  }

  updateAssociations(features) {
    const busName = this.layer.properties.name;
    // Approaches that reference this bus stop via their 'bus_stop' list
    this.approches = features.filter(
      (feature) =>
        feature.type === "approche" &&
        parseList(feature.layer.properties.bus_stop).includes(busName)
    );
    // Sectors linked to those approaches (sectors list approach names)
    const approcheNames = this.approches
      .map((ap) => ap.layer.properties.name)
      .filter(Boolean);
    this.secteurs = features.filter(
      (feature) =>
        feature.type === "secteur" &&
        approcheNames.some((n) =>
          parseList(feature.layer.properties.approche).includes(n)
        )
    );
    // No bicycle access associations for bus stops.
  }
}

const iconBusStop = BusStop.busStopIcon(BusStop.iconSize);
const buildBusStopMarker = (busStopFeature, options = {}) => {
  const marker = L.marker(
    [
      busStopFeature.geometry.coordinates[1],
      busStopFeature.geometry.coordinates[0],
    ],
    {
      icon: iconBusStop,
    }
  );
  return marker;
};
