import Element from "/js/components/map/element.js";

export default class Parking extends Element {
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

  highlight() {
    this.layer.setIcon(parkingIcon(iconSize * 1.2));
  }
  unhighlight() {
    this.layer.setIcon(iconParking);
  }
}

const iconSize = 18;
const parkingIcon = (size) =>
  L.divIcon({
    pmignore: true,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    className: "bg-none flex flex-row justify-center items-start",
    html: `<div class="text-white bg-blue-600 text-[${
      size / 2 + 1
    }px] rounded-full aspect-square w-[${size}px] h-[${size}px] flex justify-center items-center font-bold border border-white">P</div>`,
  });
const iconParking = parkingIcon(iconSize);
const buildParkingMarker = (parkingFeature, options = {}) => {
  const marker = L.marker(
    [
      parkingFeature.geometry.coordinates[1],
      parkingFeature.geometry.coordinates[0],
    ],
    { icon: iconParking }
  );
  return marker;
};
