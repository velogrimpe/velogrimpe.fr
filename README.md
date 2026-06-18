# Velogrimpe.fr

Ce dépôt git contient le code du site [Velogrimpe.fr](https://velogrimpe.fr).
Il contient tout le code nécessaire pour faire fonctionner le site sur un hébergement PHP comme l'offre Single Web Hosting de Hostinger (qui déploie un serveur PHP servant les fichiers du site.). Le site ne nécessite pas de phase de build.

## Organisation du code

Toutes les pages principales sont à la racine du dépôt:

- `index.php` est la page d'accueil avec la carte interactive
- `tableau.php` est la page qui présente le tableau des falaises accessibles depuis une ville donnée.
- `falaise.php` est le template de la page falaise.
- `logistique.php` est la page de guide logistique pour se lancer dans le velogrimpe.
- `infos.php`, `contribuer.php` et `communaute.php` sont des pages annexes.
- `header.html` est le code pour la barre de navigation, importé par toutes les autres pages.
- `robots.txt`et `sitemap.php` sont les fichiers qui liste l'ensemble des page et qui expliquent aux robots d'indexation des moteurs de recherche comment trouver nos pages.
- Le dossier `ajout/` contient les différentes pages et routes API de contribution de données.
- Le dossier `js/` contient les quelques scripts utilisés sur le site.
- Le dossier `symbols/` contient les icones utilisés sur le site.
- Le dossier `images/` contient les images statiques, hors contenus falaises.
- Le dossier `bdd/` contient, une fois peuplé, les images des falaises, les gpx, les geojson des barres et le dossier `bdd/trains` contient le geojson des lignes de train françaises ainsi que la version convertie en tuiles (le .pmtiles) pour permettre de charger seulement la partie visible.

## Mise en place d'un environnement de développement

### Pré-requis

- Installer `docker`

### Procédure

1. créer un dossier `velo-grimpe` qui servira de dossier racine.
1. Dans ce dossier, cloner ce dépôt `git clone https://github.com/velogrimpe/velogrimpe.fr.git -d public_html` (ou via ssh)
1. Dans le dossier racine, créer un fichier nommé `config.php` contenant les lignes suivantes:

```php
<?php
return [
  'db_name' => 'velogrimpe',
  'db_user' => 'velogrimpe',
  'db_pass' => 'velogrimpe',
  'sncf_db_name' => 'sncf',
  'sncf_db_user' => 'sncf',
  'sncf_db_pass' => 'sncf',
  'admin_token' => "admin",
  'contact_mail' => "votre.email@club-internet.fr",
];
```

4. Démarrez un conteneur `docker-xampp`:

```bash
export ROOTPARENT=/chemin/vers/dossier/velo-grimpe
docker run --platform linux/x86_64 --name myXampp -p 4003:22 -p 4002:80 -d -v $ROOTPARENT/velo-grimpe/public_html:/opt/lampp/htdocs --mount type=bind,source=$ROOTPARENT$/velo-grimpe/config.php,target=/opt/lampp/config.php,readonly tomsik68/xampp:8
```

Une fois lancé, ce conteneur est synchronisé avec votre dossier local et sert :

- Sur le port 4002, le site déployé en local (http://localhost:4002)
- Sur http://localhost:4002/phpmyadmin l'interface pour administrer la base de donnée locale (éphémère, supprimée à chaque re-création du conteneur)

À partir de là, tout ce que vous changez dans votre éditeur de code est répecuté sur le serveur local (pas de refresh automatique, il faut faire un Cmd/Ctrl+R pour voir les changements).

5. Sur l'interface de phpmyadmin, créez deux nouvelles bases de données : `velogrimpe` et `sncf` ainsi que deux utilisateurs portant les même noms et ayant accès à ces bases de données.
6. Demandez nous un export de la base ou au moins du schéma et importez les dans les bases respectives.

## Partage et réutilisation

### Code

Velogrimpe.fr est un site communautaire visant à promouvoir la mobilité douce pour aller en falaise. Les personnes derrière Velogrimpe.fr sont des bénévoles sans intérêts commerciaux liés au site ou à ses données. Afin de péréniser l'existence du site et des idées qui le sous-tendent, et de permettre des réutilisation pour appliquer le principe à d'autres pratiques outdoor, nous avons décidé de publier le code du site. Ce code est mis à disposition publiquement sous [licence](./LICENCE) `CC BY-NC-SA 4.0`, qui impose la citation, la non-commercialisation et le partage avec la même licence (copyleft).

### Données

Dans une démarche de partage de notre code et du contenu du site, nous avons choisi de diffuser les contenus du site sous des licences libres compatibles avec les autres ressources de références du domaine (OSM, Camp2Camp). Concrètement, cela autorise tout contributeur à copier/coller le contenu d'une fiche falaise issue de C2C (licence CC BY-SA) dans vélogrimpe et inversement.

Les contenus publiés sur velogrimpe.fr sont diffusés sous les licences suivantes :

- **Textes** : [CC BY-SA](https://creativecommons.org/licenses/by-sa/4.0/deed.fr) → Permet de copier et réutiliser les textes du site sur d'autres supports à licence compatible (ex: C2C) (sauf mention contraire par l'auteur dans la fiche falaise)
- **Images** : [CC BY-NC-ND](https://creativecommons.org/licenses/by-nc-nd/4.0/deed.fr) → Utilisation commerciale interdite. Diffusion à l'identique et en citant la source. (sauf licence spécifique précisée par l'auteur dans la légende ou sur l'image)
- **Données** : [ODbL](https://opendatacommons.org/licenses/odbl/1-0/) et [CC BY-SA](https://creativecommons.org/licenses/by-sa/4.0/deed.fr) → Compatible OpenStreetMap et C2C, permet de réutiliser les données pour enrichir OSM.

Trois exports GeoJSON sont disponibles, regénérés quotidiennement au cours de la nuit et exploitables par exemple directement dans [UMAP](https://umap.openstreetmap.fr/) :

- [falaises.geojson](https://velogrimpe.fr/open-data/falaises.geojson) : les falaises (points) et, embarqués dans leurs propriétés, leurs itinéraires vélo, liens externes et arrêts de bus.
- [itineraires-velo.geojson](https://velogrimpe.fr/open-data/itineraires-velo.geojson) : les tracés complets des itinéraires vélo (LineString) reconstruits à partir des fichiers GPX, avec distance, dénivelés et gare de départ.
- [gares.geojson](https://velogrimpe.fr/open-data/gares.geojson) : les gares (points, hors gares supprimées), avec commune, département, codes UIC/OSM et flag TGV.
- [falaises-details.geojson](https://velogrimpe.fr/open-data/falaises-details.geojson) : les détails géométriques agrégés de toutes les falaises (secteurs, parkings, approches…).
- [complet.geojson](https://velogrimpe.fr/open-data/complet.geojson) : les collections ci-dessus réunies en un seul fichier. Chaque entité porte une propriété `vg_type` (`falaise`, `itineraire_velo`, `gare` ou `detail`) pour pouvoir les re-filtrer.
