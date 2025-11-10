# Génération des tiles à partir du GEOJSON

## Génération des tuiles mbtiles avec `tippecanoe`

### Installation (Mac OS)

```
brew install tippecanoe
```

### Conversion GeoJSON --> PMTiles

```
tippecanoe -z16 -o tgv.pmtiles --drop-densest-as-needed --extend-zooms-if-still-dropping --force tgv.geojson
tippecanoe -z16 -o ter.pmtiles --drop-densest-as-needed --extend-zooms-if-still-dropping --force ter.geojson
```

La partie importante est le nom du fichier `fr.geojson` à changer si besoin

### Utilisation

```js
const paintRules = [
  {
    dataLayer: "ter", // NOTE: Nom du fichier GeoJSON
    symbolizer: new protomapsL.LineSymbolizer({
      color: "#000",
      width: 1.5,
    }),
  },
];
var layer = protomapsL.leafletLayer({
  url: "/bdd/trains/ter.pmtiles",
  paintRules,
}); // NOTE: Path vers le fichier PMTiles
layer.addTo(map);
```

# Création d'un serveur de tuile (déprécié, gardé pour mémoire)

## Création du serveur de tuiles TileServer GL

### Prérequis

- Docker
- maptiler/tileserver-gl

```
docker pull maptiler/tileserver-gl
```

### Démarrage

- en mode interactif

```
docker run -it -v $(pwd):/data -p 8080:8080 maptiler/tileserver-gl
```

- en mode démon

```
docker run -d --name tileserver -v $(pwd):/data -p 8080:8080 maptiler/tileserver-gl
```

## Création / Modification du style

Nécessite d'avoir déjà démarré le serveur de tuiles

- Visiter [Maputnik](https://maplibre.org/maputnik)
- `Sources de données` > `Ajouter une nouvelle source`
  - Id : rff_florent
  - Type de source : Vecteur
  - URL : http://localhost:8080/data/rff_florent.json
    - Alternative : Utiliser l'url du serveur une fois déployé
  - Valider
- Revenir dans l'écran principal > "Ajouter un calque"
  - ID: rff_florent
  - Type: Line
  - Source: rff_florent
  - Calque Source : rff_florent
- Personaliser l'apparence
- Enregistrer
- Dans le navigateur de fichier

## Test pour valider le bon fonctionnement

```
wget http://localhost:8080/styles/basic/512/9/265/185.png
```

# Déploiement serveur

- Copier les fichiers `config.json` (config tileserver-gl), `rff_florent.mbtiles` (tuiles), `rff_florent.style.json` (styles) sur le serveur dans un même dossier
