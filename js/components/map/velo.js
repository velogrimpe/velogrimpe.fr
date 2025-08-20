import Element from "/js/components/map/element.js";
import { gpx_path } from "/js/components/utils/paths.js";
import { format_time, calculate_time } from "/js/components/utils/times.js";

export default class Velo extends Element {
  static baseLineWeight = 5;
  static highlightedLineWeight = 10;

  constructor(map, velo, options = {}) {
    const layer = renderGpx(velo, options);
    super(map, layer, "velo", options);
    this.color = colors[options.index % colors.length] || "black";
    this.setupHighlight();
  }

  highlight(e, propagate = true) {
    this.layer.eachLayer((l) =>
      l.setStyle({
        weight: Velo.highlightedLineWeight,
        color: this.color,
      })
    );
    super.highlight(e, propagate);
  }

  unhighlight(propagate = true) {
    this.layer.eachLayer((l) =>
      l.setStyle({
        weight: Velo.baseLineWeight,
        color: this.color,
      })
    );
    super.unhighlight(propagate);
  }
}

const colors = [
  "indianRed",
  "tomato",
  "teal",
  "paleVioletRed",
  "mediumSlateBlue",
  "lightSalmon",
  "fireBrick",
  "crimson",
  "purple",
  "hotPink",
  "mediumOrchid",
];

function renderGpx(velo, options = {}) {
  const { index } = options;
  const color = colors[index % colors.length] || "black";
  const lopts = {
    weight: Velo.baseLineWeight,
    color,
  };
  const gpxOptions = {
    async: true,
    markers: {
      startIcon: null,
      endIcon: null,
    },
    polyline_options: lopts,
  };
  return new L.GPX("/bdd/gpx/" + gpx_path(velo), gpxOptions).on(
    "loaded",
    (e) => {
      e.target.bindTooltip(
        format_time(calculate_time(velo)) +
          (velo.velo_apieduniquement
            ? '<svg class="w-4 h-4 fill-current inline"><use xlink:href="/symbols/icons.svg#ri-footprint-fill"></use></svg>'
            : ""),
        {
          className: `p-[1px] bg-[${color}] text-white border-[${color}] font-bold`,
          permanent: true,
          direction: "center",
        }
      );
      e.target.on("click", (e) => {
        L.DomEvent.stopPropagation(e);
      });
    }
  );
}
