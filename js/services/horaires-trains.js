"use strict";

/**
 *
 * @module horairesTrains
 */

var horairesTrains = {};

const url = "https://api.transitous.org/api/";
const ua =
  "Vélogrimpe.fr (https://velogrimpe.fr) - v0.1 - contact@velogrimpe.fr";
const routingEndpoint = "v3/plan";
const geocodeEndpoint = "v1/geocode";
const transitModes = [
  // "RAIL",
  "LONG_DISTANCE",
  "REGIONAL_FAST_RAIL",
  "REGIONAL_RAIL",
  "HIGHSPEED_RAIL",
  "SUBURBAN",
].join(",");

const toMinutes = (seconds) => Math.round(seconds / 60);

function toHM(minutes) {
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return `${hours}h ${mins < 10 ? "0" : ""}${mins}m`;
}

function computeStats(data) {
  const itineraries = data.itineraries || [];
  if (itineraries.length === 0) {
    throw new Error("Aucun itinéraire trouvé");
  }

  const brief = itineraries.map((it) => {
    const { transfers, legs } = it;
    const transitLegs = legs.filter((leg) => leg.mode !== "WALK");
    const startTime = transitLegs[0]?.startTime || it.startTime;
    const endTime = transitLegs[transitLegs.length - 1]?.endTime || it.endTime;
    const duration =
      (new Date(endTime).getTime() - new Date(startTime).getTime()) / 1000;
    const segments = transitLegs.map((leg) => {
      const stopId = leg.from.stopId || "";
      return {
        from: leg.from.name,
        to: leg.to.name,
        duration:
          (new Date(leg.endTime).getTime() -
            new Date(leg.startTime).getTime()) /
          1000,
        line: leg.routeId,
        agency: leg.agencyName,
        mode: leg.mode,
        tgv:
          stopId.includes("TGV INOUI") ||
          stopId.includes("TGVINOUI") ||
          stopId.includes("OCELyria") ||
          stopId.includes("OUIGO") ||
          leg.agencyName === "Trenitalia" ||
          (leg.agencyName || "").includes("RENFE") ||
          leg.mode === "HIGHSPEED_RAIL",
      };
    });
    // const geoms = transitLegs.map((leg) => {
    //   const geom = leg.legGeometry.points; // TODO add a map to display all routes
    //   return geom;
    // });
    return { duration, transfers, startTime, endTime, segments };
  });
  // dedup trips on start and end times
  const uniqueTrips = Array.from(
    new Map(
      brief.map((item) => [`${item.startTime}-${item.endTime}`, item])
    ).values()
  );
  // exclude trips spanning two days
  const filteredTrips = uniqueTrips.filter((item) => {
    const start = new Date(item.startTime);
    const end = new Date(item.endTime);
    return start.getDate() === end.getDate();
  });
  // compute max/min transfers, max/min duration
  const maxTransfers = Math.max(
    ...filteredTrips.map((item) => item.transfers),
    0
  );
  const minTransfers = Math.min(
    ...filteredTrips.map((item) => item.transfers),
    Infinity
  );
  const maxDuration = Math.max(
    ...filteredTrips.map((item) => item.duration),
    0
  );
  const minDuration = Math.min(
    ...filteredTrips.map((item) => item.duration),
    Infinity
  );
  const transferStations = itineraries
    .filter((item) => item.transfers > 0)
    .map((item) =>
      item.legs
        .filter((leg) => leg.mode !== "WALK")
        .slice(0, -1)
        .map((leg) => leg.to.name)
        .join(" et ")
    );
  const transferStationsSet = new Set(transferStations);
  const ratioDirects =
    filteredTrips.length > 0
      ? (filteredTrips.filter((item) => item.transfers === 0).length /
          filteredTrips.length) *
        100
      : 0;

  return {
    uniqueTrips: filteredTrips,
    minDuration: toMinutes(minDuration),
    maxDuration: toMinutes(maxDuration),
    minTransfers,
    maxTransfers,
    ratioDirects,
    transferStations: Array.from(transferStationsSet),
    nTrips: filteredTrips.length,
    geoms: filteredTrips.flatMap((item) => item.geoms),
  };
}

const transformToTrainFields = (stats) => {
  return {
    train_temps: stats.minDuration,
    train_correspmin: stats.minTransfers,
    train_correspmax: stats.maxTransfers,
    train_nbtrains: stats.nTrips,
    train_descr:
      `- Environ ${stats.nTrips} trains / jours.\n` +
      `- De ${toHM(stats.minDuration)} à ${toHM(stats.maxDuration)}.\n` +
      (stats.ratioDirects >= 50
        ? "- Majorité de directs.\n"
        : `- Majorité de trajets avec correspondances.\n`) +
      (stats.transferStations.length > 0
        ? `- Correspondances possibles : ${stats.transferStations.join(
            ", "
          )}.\n`
        : ""),
  };
};

async function fetchGares(ville) {
  const query = encodeURIComponent(ville);
  const res = await fetch(`${url}${geocodeEndpoint}?text=${query}&type=STOP`, {
    headers: { "X-Client-Identification": ua },
  }).then((res) => res.json());
  return res.filter((r) => r.type === "STOP");
}
horairesTrains.fetchGares = fetchGares;

async function fetchRoute(fromValue, toValue) {
  const from = encodeURIComponent(fromValue);
  const to = encodeURIComponent(toValue);
  const fromRes = await fetch(`${url}${geocodeEndpoint}?text=${from}`, {
    headers: { "X-Client-Identification": ua },
  }).then((res) => res.json());
  const { lat: fromLat, lon: fromLon } = fromRes[0];
  const toRes = await fetch(`${url}${geocodeEndpoint}?text=${to}`, {
    headers: { "X-Client-Identification": ua },
  }).then((res) => res.json());
  const { lat: toLat, lon: toLon } = toRes[0];
  return fetchRouteByCoords(fromLat, fromLon, toLat, toLon);
}
horairesTrains.fetchRoute = fetchRoute;

async function fetchRouteByCoords(fromLat, fromLon, toLat, toLon) {
  const nextSaturday = new Date();
  nextSaturday.setDate(
    nextSaturday.getDate() + ((6 - nextSaturday.getDay()) % 7)
  );
  nextSaturday.setHours(0, 0, 0, 0);
  const fromPlace = `${fromLat},${fromLon}`;
  const toPlace = `${toLat},${toLon}`;
  const fullUrl =
    `${url}${routingEndpoint}?1=1` +
    `&fromPlace=${fromPlace}` +
    `&toPlace=${toPlace}` +
    `&transitModes=${transitModes}` +
    `&time=${nextSaturday.toISOString()}` +
    `&withFares=${true}` +
    `&passengers=${1}` +
    // `&preTransitModes=${"BIKE,WALK"}` +
    // `&maxPreTransitTime=${3600}` + // returns too many duplicated results, requires to dedup
    `&searchWindow=${86400}`;

  try {
    const response = await fetch(fullUrl, {
      headers: { "X-Client-Identification": ua },
    });
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();
    const stats = computeStats(data);
    const fields = transformToTrainFields(stats);
    return { stats, fields, data };
  } catch (error) {
    console.error("Error fetching route:", error);
    return { stats: {}, fields: {}, data: { uniqueTrips: [] } };
  }
}
horairesTrains.fetchRouteByCoords = fetchRouteByCoords;

if (typeof module === "object" && module.exports) {
  module.exports = horairesTrains;
}
