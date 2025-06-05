/**
 * Fetches a route from the Valhalla routing service and decodes the polyline shape.
 *
 * @param {Array<{lat: number, lon: number}>} locations - An array of location objects, each containing latitude and longitude.
 * @returns {Promise<Array<Object>|null>} A promise that resolves to an array of decoded polyline points or null if the request fails.
 *
 * @example
 * const locations = [
 *   { lat: 40.748817, lon: -73.985428 }, // New York
 *   { lat: 34.052235, lon: -118.243683 } // Los Angeles
 * ];
 * const route = await getValhallaRoute(locations);
 * console.log(route);
 *
 * @throws {Error} Logs an error to the console if the routing request fails.
 */
export async function getValhallaRoute(locations, costing = "pedestrian") {
  const url = "https://valhalla1.openstreetmap.de/route";
  const body = {
    locations,
    costing, //: "pedestrian", // or 'auto', 'bicycle', etc.
    costing_options: {
      pedestrian: {
        // speed: 1.4, // Average walking speed in km/h
        max_hiking_difficulty: 6,
      },
      // bicycle: {
      //   speed: 5.56, // Average cycling speed in km/h
      // }
    },
    units: "kilometers",
    directions_options: { units: "kilometers" },
  };

  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });

    const json = await res.json();
    const shape = decodePolyline(json.trip.legs[0].shape);
    return shape;
  } catch (err) {
    console.error("Routing failed", err);
    return null;
  }
}

function decodePolyline(str) {
  let index = 0,
    lat = 0,
    lng = 0,
    coordinates = [],
    shift,
    result,
    byte;

  while (index < str.length) {
    shift = result = 0;
    do {
      byte = str.charCodeAt(index++) - 63;
      result |= (byte & 0x1f) << shift;
      shift += 5;
    } while (byte >= 0x20);
    lat += result & 1 ? ~(result >> 1) : result >> 1;

    shift = result = 0;
    do {
      byte = str.charCodeAt(index++) - 63;
      result |= (byte & 0x1f) << shift;
      shift += 5;
    } while (byte >= 0x20);
    lng += result & 1 ? ~(result >> 1) : result >> 1;

    coordinates.push([lat / 1e6, lng / 1e6]);
  }
  return coordinates;
}
