import { reverse, toGeoJSON } from "/js/components/utils/coords.js";
import Element from "/js/components/map/element.js";

export default class FalaiseVoisine extends Element {
  constructor(map, zoneFeature, zone, options = {}) {
    const layer = buildFalaiseVoisineLayer(zoneFeature, options);
    layer.properties = zoneFeature.properties;
    super(map, layer, "falaise_voisine-label", {
      ...options,
    });
    this.zone = zone;
    this.setupHighlight();
  }
  highlight(e) {
    this.zone.highlight(e);
  }
  unhighlight() {
    this.zone.unhighlight();
  }
  updateLabel() {
    const name = this.zone.layer.properties.name;
    if (!name) {
      return;
    }
    this.layer.setIcon(buildIcon(name));
  }

  cleanUp() {
    if (this.isVisible) {
      this.map.removeLayer(this.layer);
    }
  }
}

const buildFalaiseVoisineLayer = (zoneFeature, options = {}) => {
  const center = reverse(
    turf.centerOfMass(toGeoJSON(zoneFeature)).geometry.coordinates
  );
  const name = zoneFeature.properties.name;
  return L.marker(center, {
    pmignore: true,
    icon: buildIcon(name),
  });
};

const buildIcon = (name) =>
  L.divIcon({
    iconSize: [0, 0],
    iconAnchor: [0, 0],
    className: "relative",
    html: `<div
            id="marker-${name.replace(/"/g, "")}"
            class="absolute z-1 top-0 left-1/2 w-fit text-nowrap -translate-x-1/2 bg-primary text-white text-xs p-[2px] leading-none rounded-md opacity-80">
              ${name}
            </div>`,
  });
