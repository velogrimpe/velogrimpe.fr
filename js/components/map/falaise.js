import Element from "/js/components/map/element.js";

export default class Falaise extends Element {
  constructor(map, falaise, options = {}) {
    const visibility = options.visibility || { to: 14 };
    const layer = buildFalaiseMarker(falaise, options);
    super(map, layer, "falaise", { ...options, visibility });
    this.falaise = falaise;
  }

  static iconSize = 24;
  static falaiseIcon(size, closed, bloc, className) {
    return L.icon({
      iconUrl: closed
        ? "/images/map/icone_falaisefermee_carte.png"
        : bloc === 1
        ? "/images/map/icone_falaise_carte_bloc.png"
        : bloc === 2
        ? "/images/map/icone_falaise_carte_psychobloc.png"
        : "/images/map/icone_falaise_carte.png",
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
      className,
    });
  }
}

const buildFalaiseMarker = (falaise, options = {}) => {
  const marker = L.marker(falaise.falaise_latlng.split(",").map(parseFloat), {
    icon: Falaise.falaiseIcon(
      Falaise.iconSize,
      falaise.falaise_fermee === "1",
      falaise.falaise_bloc,
      options.className || "falaise-icon"
    ),
    pmIgnore: true,
  });
  return marker;
};
