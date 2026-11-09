import { overpassFetch } from "./overpass.js";

/**
 * Prédicats OSM par modalité BTT recherchable via Overpass (pas de gares
 * ter/tgv ici, cf. docs/conception-bus.md Étape 2 : les gares ne sont pas
 * recherchées via Overpass, elles proviennent de la base `gares`).
 * `route` : valeur du tag `route` des relations de ligne à associer (null si
 * cette modalité n'a pas de relation de ligne exploitable ici).
 */
const TYPE_DEFS = {
  bus: { key: "highway", value: "bus_stop", route: "bus" },
  tram: { key: "railway", value: "tram_stop", route: "tram" },
  telepherique: { key: "aerialway", value: "station", route: null },
};

function buildOverpassQuery(types) {
  const defs = types
    .map((t) => ({ type: t, ...TYPE_DEFS[t] }))
    .filter((d) => d.key);

  return (south, west, north, east) => {
    if (!defs.length) return null;
    const bbox = `${south},${west},${north},${east}`;
    const nodeWaySets = defs
      .map(
        (d, i) => `
node["${d.key}"="${d.value}"]["name"](${bbox})->.n${i};
way["${d.key}"="${d.value}"]["name"](${bbox})->.w${i};`,
      )
      .join("");
    const routeSets = defs
      .map((d, i) =>
        d.route
          ? `
  rel(bn.n${i})["type"="route"]["route"="${d.route}"];
  rel(bw.w${i})["type"="route"]["route"="${d.route}"];`
          : "",
      )
      .join("");
    const stopUnion = defs.map((d, i) => `\n  .n${i};\n  .w${i};`).join("");

    return `
[out:json][timeout:25];${nodeWaySets}
(${routeSets}
)->.routes;
(${stopUnion}
);
out body;
out center qt;
.routes out body;
`;
  };
}

function normalizeStr(s) {
  return (s ?? "").trim();
}

function routeKey(network, ref) {
  return `${normalizeStr(network)}|${normalizeStr(ref)}`;
}

/** Modalité BTT (bus/tram/telepherique) déduite des tags OSM d'un élément. */
function vgTypeOf(tags) {
  if (tags?.highway === "bus_stop") return "bus";
  if (tags?.railway === "tram_stop") return "tram";
  if (tags?.aerialway === "station") return "telepherique";
  return null;
}

function parseArretStops(overpassJson) {
  const elements = overpassJson.elements || [];

  // Index des routes par stop membre: "node/123" ou "way/456"
  const routesByMember = new Map();

  for (const el of elements) {
    if (el.type !== "relation") continue;
    const t = el.tags || {};
    if (t.type !== "route" || !["bus", "tram"].includes(t.route)) continue;

    const route = {
      network: normalizeStr(t.network),
      name: normalizeStr(t.name),
      ref: normalizeStr(t.ref),
    };

    for (const m of el.members || []) {
      if (m.type !== "node" && m.type !== "way") continue;
      const key = `${m.type}/${m.ref}`;
      if (!routesByMember.has(key)) routesByMember.set(key, []);
      routesByMember.get(key).push(route);
    }
  }

  // Construire la sortie stops
  const stops = [];

  for (const el of elements) {
    if (el.type !== "node" && el.type !== "way") continue;
    const vgType = vgTypeOf(el.tags);
    if (!vgType) continue;

    const name = normalizeStr(el.tags?.name);
    if (!name) continue; // vu que tu filtres ["name"] côté Overpass, c’est juste une sécurité

    const lat = el.type === "node" ? el.lat : el.center?.lat;
    const lon = el.type === "node" ? el.lon : el.center?.lon;
    const network = normalizeStr(el.tags?.network);
    if (typeof lat !== "number" || typeof lon !== "number") continue;

    const memberKey = `${el.type}/${el.id}`;
    const routesRaw = routesByMember.get(memberKey) || [];

    // Dédoublonner par network+ref (et garder un objet propre)
    const dedup = new Map(); // key -> route
    for (const r of routesRaw) {
      const k = routeKey(r.network, r.ref);
      if (!dedup.has(k)) {
        dedup.set(k, r);
      }
    }

    stops.push({
      id: el.id,
      osm_type: el.type,
      osm_id: `${el.type}/${el.id}`,
      vg_type: vgType,
      tags: el.tags || {},
      name,
      lat,
      lon,
      network,
      routes: Array.from(dedup.values()),
    });
  }

  return stops;
}

/**
 * Recherche Overpass des arrêts BTT (hors gares ter/tgv) dans la zone visible,
 * pour les modalités demandées (sous-ensemble de bus/tram/telepherique).
 */
export const fetchArretStops = async (mapOrBounds, types, options = {}) => {
  const overpassJson = await overpassFetch(mapOrBounds, buildOverpassQuery(types), options);
  return parseArretStops(overpassJson);
};

/** @deprecated Conservé pour compatibilité, préférer fetchArretStops(map, ['bus']). */
export const fetchBusStops = async (mapOrBounds, options = {}) =>
  fetchArretStops(mapOrBounds, ["bus"], options);
