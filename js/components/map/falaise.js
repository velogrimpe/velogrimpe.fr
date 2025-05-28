import Element from "/js/components/map/element.js";

export default class Falaise extends Element {
  constructor(map, falaise, options = {}) {
    const layer = buildFalaiseMarker(falaise, options);
    super(map, layer, "falaise", options);
  }
}

const iconSize = 24;
const falaiseIcon = (size, className) =>
  L.icon({
    iconUrl: "/images/icone_falaise_carte.png",
    iconSize: [size, size],
    iconAnchor: [size / 2, size],
    className,
  });
const iconFalaise = falaiseIcon(iconSize);
const buildFalaiseMarker = (falaise, options = {}) => {
  const marker = L.marker(falaise.falaise_latlng.split(",").map(parseFloat), {
    icon: iconFalaise,
    pmIgnore: true,
  });
  return marker;
};
