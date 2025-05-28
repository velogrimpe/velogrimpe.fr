import Element from "/js/components/map/element.js";
import { gpx_path } from "/js/components/utils/paths.js";
import { format_time, calculate_time } from "/js/components/utils/times.js";

export default class Velo extends Element {
  constructor(map, velo, options = {}) {
    const layer = renderGpx(velo, options);
    super(map, layer, "velo", options);
  }
}

const baseLineWeight = 5;
const highlightedLineWeight = 10;
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
    weight: baseLineWeight,
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
      e.target.bindTooltip(format_time(calculate_time(velo)), {
        className: `p-[1px] bg-[${color}] text-white border-[${color}] font-bold`,
        permanent: true,
        direction: "center",
      });
      e.target.on("mouseover", (e) => {
        e.originalEvent.target.ownerSVGElement.appendChild(
          e.originalEvent.target
        );
        e.target.eachLayer((l) =>
          l.setStyle({ weight: highlightedLineWeight, color })
        );
      });
      e.target.on("mouseout", (e) => {
        e.target.eachLayer((l) => l.setStyle(lopts));
      });
      e.target.on("click", (e) => {
        L.DomEvent.stopPropagation(e);
      });
    }
  );
}
