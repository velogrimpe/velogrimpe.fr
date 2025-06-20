const isVisible = (visibility, map) => {
  return map.getZoom() > visibility.from && map.getZoom() <= visibility.to;
};

/**
 * MultiScaleElement is a base class for map elements that can be displayed
 * at multiple scales. It manages the visibility of layers based on the map's zoom level.
 * It also supports popups and tooltips for each layer.
 * @class
 * @param {Object} map - The map instance where the element will be added.
 * @param {Array} layersWithScale - An array of objects containing layers and their visibility settings.
 * @param {L.layer} layersWithScale.layer - The Leaflet layer to be managed.
 * @param {Object} [layersWithScale.visibility] - An object defining the visibility range for the layer.
 * @param {number} [layersWithScale.visibility.from=0] - The minimum zoom level for visibility.
 * @param {number} [layersWithScale.visibility.to=30] - The maximum zoom level for visibility.
 * @param {Object} [layersWithScale.highlightStyle] - Optional highlight style for the layer.
 * @param {string} type - The type of the element (e.g., "parking", "gare", etc.).
 * @param {Object} [options] - Optional parameters for the element.
 */
export default class MultiScaleElement {
  constructor(map, layersWithScale, type, options = {}) {
    this.map = map;
    this.type = type;
    this.layersWithScale = layersWithScale;
    const { popupContent, popupOptions } = options;
    if (popupContent) {
      layersWithScale.map((lws) =>
        lws.layer.bindPopup(popupContent, popupOptions)
      );
    }
    const { tooltipContent, tooltipOptions } = options;
    if (tooltipContent) {
      layersWithScale.map((lws) =>
        lws.layer.bindTooltip(tooltipContent, tooltipOptions)
      );
    }
    this.layersWithScale.forEach(({ layer, visibility = {} }) => {
      const { from = 0, to = 30 } = visibility;
      layer.visibility = { from, to };
      layer.isVisible = isVisible(layer.visibility, map);
      if (layer.isVisible) {
        layer.addTo(map);
      }
    });
    map.on("zoomend", () => {
      this.handleZoomChange();
    });
  }

  handleZoomChange() {
    this.layersWithScale.forEach(({ layer = {} }) => {
      const newIsVisible = isVisible(layer.visibility, this.map);
      if (newIsVisible !== layer.isVisible) {
        layer.isVisible = newIsVisible;
        if (layer.isVisible) {
          layer.addTo(this.map);
        } else {
          this.map.removeLayer(layer);
        }
      }
    });
  }

  highlight(event, propagate = true) {
    this.layersWithScale.forEach(({ layer, highlightStyle }) => {
      if (highlightStyle) {
        layer.setStyle(highlightStyle);
      }
    });
    if (this.getDependencies && propagate) {
      this.getDependencies().forEach((dep) => {
        dep.forEach((d) => {
          d.highlight(event, false);
        });
      });
    }
  }

  unhighlight(propagate = true) {
    this.layersWithScale.forEach(({ layer, style }) => {
      layer.setStyle(style);
    });
    if (this.getDependencies && propagate) {
      this.getDependencies().forEach((dep) => {
        dep.forEach((d) => {
          d.unhighlight(false);
        });
      });
    }
  }

  setupHighlight() {
    this.layer.on("mouseover focus", (e) => this.highlight(e));
    this.layer.on("mouseout blur", () => this.unhighlight());
  }

  cleanUp() {}
}
