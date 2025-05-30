const isVisible = (visibility, map) => {
  return map.getZoom() > visibility.from && map.getZoom() <= visibility.to;
};

export default class Element {
  constructor(map, layer, type, options = {}) {
    const { visibility = {} } = options;
    const { from = 0, to = 30 } = visibility;
    this.map = map;
    this.visibility = { from, to };
    this.isVisible = isVisible(this.visibility, map);
    this.type = type;
    this.layer = layer;
    const { popupContent, popupOptions } = options;
    if (popupContent) {
      layer.bindPopup(popupContent, popupOptions);
    }
    const { tooltipContent, tooltipOptions } = options;
    if (tooltipContent) {
      layer.bindTooltip(tooltipContent, tooltipOptions);
    }
    this.layer.type = type;
    if (this.isVisible) {
      this.layer.addTo(map);
    }
    map.on("zoomend", () => {
      this.handleZoomChange();
    });
  }

  handleZoomChange() {
    const newIsVisible = isVisible(this.visibility, this.map);
    if (newIsVisible !== this.isVisible) {
      this.isVisible = newIsVisible;
      if (this.isVisible) {
        this.layer.addTo(this.map);
      } else {
        this.map.removeLayer(this.layer);
      }
    }
  }

  highlight(event) {
    event.originalEvent.target.ownerSVGElement.appendChild(
      event.originalEvent.target
    );
    if (this.constructor.highlightStyle) {
      this.layer.setStyle(this.constructor.highlightStyle);
    }
  }

  unhighlight() {
    if (this.constructor.highlightStyle) {
      this.layer.setStyle(this.constructor.style);
    }
  }

  setupHighlight() {
    this.layer.on("mouseover focus", (e) => this.highlight(e));
    this.layer.on("mouseout blur", () => this.unhighlight());
  }
}
