# Process mise à jour des données camping

## Procédure

### Téléchargement du zip global

Page: [Base nationale des données du tourisme en open data](https://www.data.gouv.fr/datasets/datatourisme-la-base-nationale-des-donnees-du-tourisme-en-open-data/)
[Téléchargement Totalité "Place"](https://www.data.gouv.fr/api/1/datasets/r/cf247ad9-5bcd-4c8a-8f4d-f49f0803bca1)

### Transformation de la donnée

Dans velogrimpe.fr/bdd/datatourisme/ lancer le script `extract-campings.js`

```bash
bun i
bun run extract-campings.js
```

### Création des pmtiles

[Tippecanoe Command builder](https://maptiling.streamlit.app/Tippecanoe_Command_Generator)

```
# tippecanoe -zg -o camping.pmtiles --minimum-zoom=8 --drop-densest-as-needed --extend-zooms-if-still-dropping --force camping.geojson -r1 --cluster-distance=0
tippecanoe -o camping_2.pmtiles -f -z14 -Z6 -ae -d30 -m12 -pf -pk -r0.0 -K1 -k0 -L camping:camping.geojson
```

### Upload de la donnée

Avec Filezilla, fichier non tracé par git
