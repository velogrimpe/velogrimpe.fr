import { overpassFetch } from "./overpass.js";

const overpassQuery = (south, west, north, east) => `
[out:json][timeout:25];
node["highway"="bus_stop"]["name"](${south},${west},${north},${east})->.stop_nodes;
way["highway"="bus_stop"]["name"](${south},${west},${north},${east})->.stop_ways;
(
  rel(bn.stop_nodes)["type"="route"]["route"="bus"];
  rel(bw.stop_ways)["type"="route"]["route"="bus"];
)->.routes;
(
  .stop_nodes;
  .stop_ways;
);
out body;
out center qt;
.routes out body;
`;

function normalizeStr(s) {
  return (s ?? "").trim();
}

function routeKey(network, ref) {
  return `${normalizeStr(network)}|${normalizeStr(ref)}`;
}

function parseBusStopsWithRoutes(overpassJson) {
  const elements = overpassJson.elements || [];

  // Index des routes par stop membre: "node/123" ou "way/456"
  const routesByMember = new Map();

  for (const el of elements) {
    if (el.type !== "relation") continue;
    const t = el.tags || {};
    if (t.type !== "route" || t.route !== "bus") continue;

    const route = {
      network: normalizeStr(t.network),
      name: normalizeStr(t.name),
      ref: normalizeStr(t.ref),
    };

    // Si ref manque, tu peux décider de l'ignorer (souvent c'est le meilleur),
    // ou fallback sur name. Ici: on garde quand même (mais la dédup se fera moins bien).
    // Si tu préfères ignorer sans ref: décommente la ligne suivante :
    // if (!route.ref) continue;

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
    if (el.tags?.highway !== "bus_stop") continue;

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
      name,
      lat,
      lon,
      network,
      routes: Array.from(dedup.values()),
    });
  }

  return stops;
}

export const fetchBusStops = async (mapOrBounds, options = {}) => {
  const overpassJson = await overpassFetch(mapOrBounds, overpassQuery, options);
  return parseBusStopsWithRoutes(overpassJson);
};
