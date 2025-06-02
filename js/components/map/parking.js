import { parseList } from "/js/components/utils/lists.js";
import Element from "/js/components/map/element.js";

export default class Parking extends Element {
  /**
   * Creates an instance of Parking.
   * @param {Object} map - The map instance where the object will be added.
   * @param {Object} parkingFeature - The GeoJSON feature representing the object.
   * @param {Object} [parkingFeature.geometry] - The geometry of the object.
   * @param {Array} [parkingFeature.geometry.coordinates] - The coordinates of the object.
   * @param {Object} [parkingFeature.properties] - The properties of the object.
   * @param {string} [parkingFeature.properties.name] - The name of the object.
   * @param {string} [accesVeloFeature.properties.description] - The description of the object.
   * @param {Object} [parkingFeature.properties.itineraire_acces] - The bicycle access associated with the object.
   * @param {Object} [options={}] - Optional parameters for the object.
   */
  constructor(map, parkingFeature, options = {}) {
    const visibility = options.visibility || { from: 12 };
    const layer = buildParkingMarker(parkingFeature, options);
    layer.properties = parkingFeature.properties;
    super(map, layer, "parking", { ...options, visibility });
    this.setupHighlight();
    this.approches = [];
    this.secteurs = [];
    this.accesVelos = [];
  }

  static fromLayer(map, layer) {
    map.removeLayer(layer);
    const parkingFeature = {
      ...layer.toGeoJSON(),
      properties: layer.properties || {},
    };
    return new Parking(map, parkingFeature);
  }

  highlight(event, propagate) {
    this.layer.setIcon(
      parkingIcon(iconSize * 1.2, this.layer.properties.name || "P")
    );
    super.highlight(event, propagate);
  }
  unhighlight(propagate) {
    this.layer.setIcon(
      parkingIcon(iconSize, this.layer.properties.name || "P")
    );
    super.unhighlight(propagate);
  }

  getDependencies() {
    return [this.secteurs, this.approches, this.accesVelos];
  }

  updateAssociations(features) {
    const name = this.layer.properties.name;
    this.secteurs = features.filter(
      (feature) =>
        feature.type === "secteur" &&
        parseList(feature.layer.properties.parking).includes(name)
    );
    const accesVelos = parseList(this.layer.properties.itineraire_acces);
    this.accesVelos = features.filter(
      (feature) =>
        feature.type === "acces_velo" &&
        accesVelos.includes(feature.layer.properties.name)
    );
    this.approches = features.filter(
      (feature) =>
        feature.type === "approche" &&
        parseList(feature.layer.properties.parking).includes(name)
    );
  }
}

const iconSize = 18;
const parkingIcon = (size, name) => {
  const pname = name
    ? name.length > 2
      ? name.substring(0, 1)
      : name
    : undefined;
  return L.divIcon({
    pmignore: true,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    className: "bg-none flex flex-row justify-center items-start",
    html: `<div class="text-white bg-blue-600 text-[${
      size / 2 + 1
    }px] rounded-full aspect-square w-[${size}px] h-[${size}px] flex justify-center items-center font-bold border border-white uppercase">${
      pname || "P"
    }</div>`,
  });
};
const iconParking = parkingIcon(iconSize);
const buildParkingMarker = (parkingFeature, options = {}) => {
  const marker = L.marker(
    [
      parkingFeature.geometry.coordinates[1],
      parkingFeature.geometry.coordinates[0],
    ],
    {
      icon: parkingFeature.properties.name
        ? parkingIcon(iconSize, parkingFeature.properties.name)
        : iconParking,
    }
  );
  return marker;
};
