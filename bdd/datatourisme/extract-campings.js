import * as csv from "csv";
import fs from "fs";
import ProgressBar from "progress";

// Tes catégories
const VELOGRIMPE_CATS = [
  "https://www.datatourisme.fr/ontology/core#NaturalCampingArea",
  "https://www.datatourisme.fr/ontology/core#Camping",
  "https://www.datatourisme.fr/ontology/core#FarmCamping",
  "https://www.datatourisme.fr/ontology/core#StopOverOrGroupLodge",
  "https://www.datatourisme.fr/ontology/core#CollectiveHostel",
];
const COLS = [
  { col: "Nom_du_POI", tgt: "name" },
  { col: "Categories_de_POI", tgt: "category" },
  { col: "Adresse_postale", tgt: "addr" },
  { col: "Code_postal_et_commune", tgt: "cp_city" },
  { col: "Date_de_mise_a_jour", tgt: "date_maj" },
  { col: "Classements_du_POI", tgt: "classement" },
  { col: "Latitude", tgt: "lat" },
  { col: "Longitude", tgt: "lon" },
  { col: "Covid19_mesures_specifiques", tgt: null },
  { col: "Createur_de_la_donnee", tgt: null },
  { col: "SIT_diffuseur", tgt: null },
  { col: "Contacts_du_POI", tgt: "contact" },
  { col: "Description", tgt: "desc" },
  { col: "URI_ID_du_POI", tgt: null },
];

function main() {
  const fileContent = fs.readFileSync("datatourisme-place.csv");
  const lines = fileContent.toString().split("\n").length - 1; // Soustraire l'en-tête
  let count = 0;
  let tot = 0;
  const bar = new ProgressBar(
    "Processing [:bar] :percent :etas | :count/:tot",
    {
      complete: "=",
      incomplete: " ",
      width: 40,
      total: lines,
    }
  );

  const parser = csv.parse(fileContent, {
    columns: true,
    skip_empty_lines: true,
  });

  let cats = new Set();
  let features = [];

  // transformer to filter and map the data
  const transformer = csv.transform((row, callback) => {
    const categories = row["Categories_de_POI"]?.split("|") || [];
    const intersection = [...categories].filter((cat) =>
      VELOGRIMPE_CATS.includes(cat)
    );
    if (intersection.length > 0) {
      row.Categories_de_POI = intersection
        .map((cat) => cat.split("#")[1])[0]
        .toLowerCase()
        .includes("camping")
        ? "Camping"
        : "Gite";
      const feature = {
        type: "Feature",
        properties: Object.fromEntries(
          COLS.map(({ col, tgt }) => [tgt, row[col]]).filter(([k]) => k)
        ),
        geometry: {
          type: "Point",
          coordinates: [parseFloat(row.Longitude), parseFloat(row.Latitude)],
        },
      };
      features.push(feature);
      tot++;
      count++;
      cats.add(
        intersection
          .map((cat) => cat.split("#")[1])
          .sort()
          .join("+")
      );
      callback(null, feature);
    } else {
      tot++;
      callback(null, null); // Sauter la ligne
    }
    bar.tick({ count, tot });
  });

  const stringifier = csv.stringify({ header: true });

  // after transformation, output a geojson file
  parser
    .on("error", (err) => console.error("Parser error:", err))
    .pipe(transformer)
    .on("error", (err) => console.error("Transformer error:", err))
    .pipe(stringifier)
    .on("error", (err) => console.error("Stringifier error:", err))
    .pipe(fs.createWriteStream("camping.csv"))
    .on("finish", () => {
      const geojson = {
        type: "FeatureCollection",
        features,
      };
      fs.writeFileSync("camping.geojson", JSON.stringify(geojson, null, 2));
      console.log("\nDone!");
      console.log("Categories trouvées :", Array.from(cats).join(", "));
    })
    .on("error", (err) => console.error("Write error:", err));
}

main();
