/**
 * Convert a Leaflet map or bounds into an Overpass bbox tuple.
 * Accepts:
 * - Leaflet map instance (with `getBounds()`)
 * - Leaflet `LatLngBounds` (with `getSouth()/getWest()/getNorth()/getEast()`)
 * - Array `[south, west, north, east]`
 * - Object `{ south, west, north, east }`
 */
export function resolveBounds(mapOrBounds) {
  if (!mapOrBounds) {
    throw new Error("No map or bounds provided to resolveBounds()");
  }

  // Leaflet Map
  if (typeof mapOrBounds.getBounds === "function") {
    const b = mapOrBounds.getBounds();
    return [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()];
  }

  // Leaflet LatLngBounds
  if (
    typeof mapOrBounds.getSouth === "function" &&
    typeof mapOrBounds.getWest === "function" &&
    typeof mapOrBounds.getNorth === "function" &&
    typeof mapOrBounds.getEast === "function"
  ) {
    return [
      mapOrBounds.getSouth(),
      mapOrBounds.getWest(),
      mapOrBounds.getNorth(),
      mapOrBounds.getEast(),
    ];
  }

  // Plain array
  if (Array.isArray(mapOrBounds) && mapOrBounds.length === 4) {
    return mapOrBounds;
  }

  // Plain object
  if (
    typeof mapOrBounds === "object" &&
    mapOrBounds !== null &&
    ["south", "west", "north", "east"].every((k) => k in mapOrBounds)
  ) {
    const { south, west, north, east } = mapOrBounds;
    return [south, west, north, east];
  }

  throw new Error("Unsupported bounds input for resolveBounds()");
}

/**
 * Error thrown when Overpass answers with a non-2xx HTTP status.
 * `status` lets callers distinguish e.g. 429 (rate limited) from other failures.
 */
export class OverpassError extends Error {
  constructor(status, statusText, body) {
    super(`Overpass request failed: ${status} ${statusText} ${body}`);
    this.name = "OverpassError";
    this.status = status;
  }
}

/**
 * Build a user-facing French message for an Overpass failure.
 */
export function overpassErrorMessage(err) {
  if (err instanceof OverpassError && err.status === 429) {
    return "Service Overpass surchargé, ré-essayez dans 1 minute.";
  }
  if (err instanceof OverpassError && err.status === 504) {
    return "Service Overpass trop lent (timeout), ré-essayez dans quelques instants.";
  }
  return "Impossible de récupérer les arrêts de bus pour la zone visible.";
}

/**
 * Query Overpass API for the current map/bounds using a query builder.
 *
 * Usage with Leaflet map:
 *   const data = await overpassFetch(map, (s,w,n,e) => `...(${s},${w},${n},${e});out geom;`);
 *
 * You may also pass a bounds object/array instead of a map.
 * If `queryBuilder` is a string, it will be sent as-is.
 */
export async function overpassFetch(
  mapOrBounds,
  queryBuilder,
  { endpoint = "https://overpass-api.de/api/interpreter", signal } = {},
) {
  const [south, west, north, east] = resolveBounds(mapOrBounds);
  const query =
    typeof queryBuilder === "function"
      ? queryBuilder(south, west, north, east)
      : String(queryBuilder);

  if (!query || typeof query !== "string") {
    throw new Error("Invalid Overpass query provided");
  }

  const body = new URLSearchParams({ data: query }).toString();

  const res = await fetch(endpoint, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
    signal,
  });

  if (!res.ok) {
    const text = await res.text().catch(() => "");
    throw new OverpassError(res.status, res.statusText, text);
  }

  return res.json();
}

/**
 * Convenience helper to get a bounds object from a Leaflet map.
 */
export function boundsObject(map) {
  const [south, west, north, east] = resolveBounds(map);
  return { south, west, north, east };
}
