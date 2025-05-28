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
      marker.bindPopup(popupContent, popupOptions);
    }
    const { tooltipContent, tooltipOptions } = options;
    if (tooltipContent) {
      marker.bindTooltip(tooltipContent, tooltipOptions);
    }
    this.layer.type = type;
    if (this.isVisible) {
      this.layer.addTo(map);
    }
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

  highlight() {
    if (this.highlightStyle) {
      this.layer.setStyle(this.highlightStyle);
    }
  }

  unhighlight() {
    if (this.highlightStyle) {
      this.layer.setStyle(this.style);
    }
  }
}
