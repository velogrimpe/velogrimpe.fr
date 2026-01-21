import { reverse, toGeoJSON } from "/js/components/utils/coords.js";
import Element from "/js/components/map/element.js";

export default class SecteurLabel extends Element {
  constructor(map, secteurFeature, secteur, options = {}) {
    const id = Math.random().toString(36).substring(2, 15);
    const layer = buildSecteurLabelLayer(secteurFeature, id, options);
    layer.properties = secteurFeature.properties;
    super(map, layer, "secteur-label", {
      ...options,
    });
    this.secteur = secteur;
    this.setupHighlight();
    this.id = id;
  }
  static highlightStyle = {
    color: "darkred",
    weight: 2,
    opacity: 1,
  };
  highlight(e, propagate = true) {
    const el = document.getElementById(this.id);
    el?.classList.add("secteur-label-highlight");
    if (propagate) {
      this.secteur.highlight(e, propagate, false);
    }
  }
  unhighlight(propagate = true) {
    const el = document.getElementById(this.id);
    el?.classList.remove("secteur-label-highlight");
    if (propagate) {
      this.secteur.unhighlight(propagate, false);
    }
  }
  updateLabel() {
    const name = this.secteur.layer.properties.name;
    if (!name) {
      return;
    }
    this.layer.setIcon(buildIcon(name, this.id));
  }

  cleanUp() {
    if (this.isVisible) {
      this.map.removeLayer(this.layer);
    }
  }
}

const buildSecteurLabelLayer = (secteurFeature, id, options = {}) => {
  const center = reverse(
    turf.centerOfMass(toGeoJSON(secteurFeature)).geometry.coordinates
  );
  const name = secteurFeature.properties.name;
  return L.marker(center, {
    pmignore: true,
    icon: buildIcon(name, id),
  });
};

const buildIcon = (name, id) =>
  L.divIcon({
    iconSize: [0, 0],
    iconAnchor: [0, 0],
    className: "relative",
    html: `<div
            id="${id}"
            class="absolute z-10 top-0 left-1/2 w-fit text-nowrap -translate-x-1/2
                    text-black bg-white text-xs p-px leading-none rounded-md opacity-80">
              ${name}
            </div>`,
  });
