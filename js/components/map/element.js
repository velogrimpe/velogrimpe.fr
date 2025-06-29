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

  highlight(event, propagate = true) {
    console.log(
      "H1 : highlight [" + this.layer.properties.name + "] - propagate",
      propagate
    );
    if (
      event &&
      event.originalEvent &&
      event.originalEvent.target.ownerSVGElement &&
      // NOTE: check if target is the last child of the SVG element to avoid infinite loop on mouseover
      event.originalEvent.target.ownerSVGElement.lastChild !==
        event.originalEvent.target
    ) {
      console.log(
        "H2 : unhighlight [" +
          this.layer.properties.name +
          "] - set svg to last child"
      );
      event.originalEvent.target.ownerSVGElement.appendChild(
        event.originalEvent.target
      );
    }
    if (this.constructor.highlightStyle) {
      this.layer.setStyle(this.constructor.highlightStyle);
    }
    if (this.getDependencies && propagate) {
      this.getDependencies().forEach((dep) => {
        dep.forEach((d) => {
          d.highlight(event, false);
        });
      });
    }
  }

  unhighlight(propagate = true) {
    console.log(
      "U1 : unhighlight [" + this.layer.properties.name + "] - propagate",
      propagate
    );
    if (this.constructor.highlightStyle) {
      this.layer.setStyle(this.constructor.style);
    }
    if (this.getDependencies && propagate) {
      this.getDependencies().forEach((dep) => {
        dep.forEach((d) => {
          d.unhighlight(false);
        });
      });
    }
  }

  setupHighlight() {
    this.layer.on("mouseover focus click", (e) => {
      L.DomEvent.stopPropagation(e);
      console.log(
        "H0 : mouseover focus click [" + this.layer.properties.name + "]",
        e.type
      );
      this.highlight(e, true);
    });
    this.layer.on("mouseout", () => {
      console.log("U0 : mouseout [" + this.layer.properties.name + "]");
      this.unhighlight(true);
    });
    this.map.on("click", () => {
      console.log("U0 : map click [" + this.layer.properties.name + "]");
      this.unhighlight(true);
    });
  }

  cleanUp() {}
}
