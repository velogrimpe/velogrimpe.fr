import { reverse, toGeoJSON } from "/js/components/utils/coords.js";
import Element from "/js/components/map/element.js";

export default class SecteurLabel extends Element {
  constructor(map, secteurFeature, secteur, options = {}) {
    const layer = buildSecteurLabelLayer(secteurFeature, options);
    layer.properties = secteurFeature.properties;
    super(map, layer, "secteur-label", {
      ...options,
    });
    this.secteur = secteur;
    this.setupHighlight();
  }
  static highlightStyle = {
    color: "darkred",
    weight: 2,
    opacity: 1,
  };
  highlight(e, propagate = true) {
    this.layer.setIcon(buildIcon(this.secteur.layer.properties.name, true));
    if (propagate) {
      this.secteur.highlight(e);
    }
  }
  unhighlight(propagate = true) {
    this.layer.setIcon(buildIcon(this.secteur.layer.properties.name));
    if (propagate) {
      this.secteur.unhighlight();
    }
  }
  updateLabel() {
    const name = this.secteur.layer.properties.name;
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

const buildSecteurLabelLayer = (secteurFeature, options = {}) => {
  const center = reverse(
    turf.centerOfMass(toGeoJSON(secteurFeature)).geometry.coordinates
  );
  const name = secteurFeature.properties.name;
  return L.marker(center, {
    pmignore: true,
    icon: buildIcon(name),
  });
};

const buildIcon = (name, highlighted = false) =>
  L.divIcon({
    iconSize: [0, 0],
    iconAnchor: [0, 0],
    className: "relative",
    html: `<div
            class="absolute z-1 top-0 left-1/2 w-fit text-nowrap -translate-x-1/2 ${
              highlighted ? "text-white" : "text-black"
            }
            ${
              highlighted ? "bg-[darkred]" : "bg-white"
            } text-xs p-[1px] leading-none rounded-md opacity-80">
              ${name}
            </div>`,
  });
