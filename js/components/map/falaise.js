import Element from "/js/components/map/element.js";

export default class Falaise extends Element {
  constructor(map, falaise, options = {}) {
    const visibility = options.visibility || { to: 14 };
    const layer = buildFalaiseMarker(falaise, options);
    super(map, layer, "falaise", { ...options, visibility });
  }

  static iconSize = 24;
  static falaiseIcon(size, className) {
    return L.icon({
      iconUrl: "/images/icone_falaise_carte.png",
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
      className,
    });
  }
}

const iconFalaise = Falaise.falaiseIcon(Falaise.iconSize);
const buildFalaiseMarker = (falaise, options = {}) => {
  const marker = L.marker(falaise.falaise_latlng.split(",").map(parseFloat), {
    icon: iconFalaise,
    pmIgnore: true,
  });
  return marker;
};
