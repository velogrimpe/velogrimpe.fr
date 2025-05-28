export const gpx_path = (velo) => {
  return (
    [
      velo.velo_id,
      velo.velo_depart,
      velo.velo_arrivee,
      velo.velo_varianteformate || "",
    ].join("_") + ".gpx"
  );
};
