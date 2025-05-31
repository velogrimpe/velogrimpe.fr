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
  highlight(e) {
    this.secteur.highlight(e);
  }
  unhighlight() {
    this.secteur.unhighlight();
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
  return L.marker(center, {
    pmignore: true,
    pane: "tooltipPane",
    icon: L.divIcon({
      iconSize: [0, 0],
      iconAnchor: [0, 0],
      className: "relative",
      html:
        `<div id="marker-${secteurFeature.properties.name.replace(
          /"/g,
          ""
        )}" class="absolute top-0 left-1/2 w-fit text-nowrap -translate-x-1/2 text-black bg-white text-xs p-[1px] leading-none rounded-md opacity-80">` +
        secteurFeature.properties.name +
        `</div>`,
    }),
  });
};
