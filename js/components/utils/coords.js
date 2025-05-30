export const reverse = (lnglat) => {
  const [lng, lat, ...rest] = lnglat;
  return [lat, lng, ...rest];
};
export const toGeoJSON = (feature) => ({
  type: "FeatureCollection",
  features: [feature],
});
