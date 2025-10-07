# Process mise à jour des données Biodiv

## Procédure

### Téléchargement du geojson

[Téléchargement](https://biodiv-sports.fr/api/v2/sensitivearea/?format=geojson)

### Renommer

```
mv biodiv-sports.fr.geojson biodiv.geojson
```

### Création des pmtiles

```
tippecanoe -zg -o biodiv.pmtiles --minimum-zoom=8 --drop-densest-as-needed --extend-zooms-if-still-dropping --force biodiv.geojson
```

### Upload de la donnée

Avec Filezilla, fichier non tracé par git
