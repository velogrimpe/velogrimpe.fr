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
- Le dossier `ajout/` contient les différentes pages de contribution de données (ajout de falaise, d'itinéraire vélo, de train… et édition d'un itinéraire vélo via `ajout/edit_velo.php`).
- Le dossier `api/` contient les routes appelées par ces formulaires et par le front (`add_*.php`, `edit_velo.php`, `fetch_*.php`…) ; `api/private/` regroupe celles réservées à l'admin.
- Le dossier `lib/` contient le code PHP partagé entre plusieurs pages ou routes (ex. `velo_lib.php` pour la validation / nettoyage des GPX).
- Le dossier `js/` contient les quelques scripts utilisés sur le site.
- Le dossier `symbols/` contient les icones utilisés sur le site.
- Le dossier `images/` contient les images statiques, hors contenus falaises.
- Le lien symbolique `public/` pointe vers le dossier de données hors dépôt (`../public`) : images des falaises, GPX, GeoJSON des barres, exports open data, et `bdd/trains` avec le GeoJSON des lignes de train françaises et sa version en tuiles (`.pmtiles`, pour ne charger que la partie visible). Voir « Chemins de données ».

## Mise en place d'un environnement de développement

### Pré-requis

- Installer `docker`

### Procédure

1. Cloner ce dépôt : `git clone https://github.com/velogrimpe/velogrimpe.fr.git velo-grimpe` (ou via ssh). Le dossier obtenu est le dossier racine, et le code du site vit dans son sous-dossier `public_html/`.
1. À la racine, créer le dossier de données (hors web root, cible du symlink `public_html/public`) :

```bash
mkdir -p public/{bdd,images,open-data}
```

3. À la racine, créer un fichier nommé `config.php` en partant de `config.sample.php`. Le minimum pour démarrer :

```php
<?php
return [
  'db_name' => 'velogrimpe',
  'db_user' => 'velogrimpe',
  'db_pass' => 'velogrimpe',
  'admin_token' => "admin",
  'contact_mail' => "votre.email@club-internet.fr",
  'base_url' => 'http://localhost',
];
```

4. Démarrez un conteneur `docker-xampp`, **depuis le dossier racine** :

```bash
docker run --platform linux/x86_64 --name velogrimpe -p 4001:22 -p 4000:80 -d \
  -v $PWD/public_html:/opt/lampp/htdocs \
  -v $PWD/public:/opt/lampp/public \
  --mount type=bind,source=$PWD/config.php,target=/opt/lampp/config.php,readonly \
  --mount type=bind,source=$PWD/.htpasswd.dev,target=/home/u829510062/domains/velogrimpe.fr/.htpasswd,readonly \
  tomsik68/xampp:8
```

`public/` est monté en lecture/écriture : c'est la cible du symlink `public_html/public`, et c'est là que le site lit et écrit les contenus téléversés (images de falaises, GPX) et les GeoJSON générés. Voir « Chemins de données » ci-dessous.

Une fois lancé, ce conteneur est synchronisé avec votre dossier local et sert :

- Sur le port 4000, le site déployé en local (http://localhost:4000)
- Sur http://localhost:4000/phpmyadmin l'interface pour administrer la base de donnée locale (éphémère, supprimée à chaque re-création du conteneur)

À partir de là, tout ce que vous changez dans votre éditeur de code est répecuté sur le serveur local (pas de refresh automatique, il faut faire un Cmd/Ctrl+R pour voir les changements).

**Accès à `/admin/` en local.** `public_html/admin/.htaccess` protège l'admin par Basic Auth et pointe vers un `AuthUserFile` au chemin absolu de la prod (`/home/u829510062/domains/velogrimpe.fr/.htpasswd`). Ne modifiez pas ce fichier : fournissez plutôt un `.htpasswd` de dev à ce même chemin dans le conteneur.

Le fichier `.htpasswd.dev` à la racine du repo (versionné, identifiants `dev` / `dev`, jamais servi ni déployé) est prévu pour ça.

```bash
# Option A — conteneur déjà lancé : copier le fichier dedans (persiste aux redémarrages, pas à une re-création)
docker exec velogrimpe mkdir -p /home/u829510062/domains/velogrimpe.fr
docker cp .htpasswd.dev velogrimpe:/home/u829510062/domains/velogrimpe.fr/.htpasswd

# Option B — à la création du conteneur : ajouter ce mount au `docker run` ci-dessus
#   --mount type=bind,source=$ROOTPARENT/velo-grimpe/.htpasswd.dev,target=/home/u829510062/domains/velogrimpe.fr/.htpasswd,readonly
```

http://localhost:4000/admin/ demande alors `dev` / `dev`.

5. Créez la base et son utilisateur, en reprenant les valeurs mises dans `config.php`. Soit depuis phpMyAdmin, soit en ligne de commande :

```bash
docker exec velogrimpe /opt/lampp/bin/mysql -uroot -e "
  CREATE DATABASE IF NOT EXISTS velogrimpe
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'velogrimpe'@'localhost' IDENTIFIED BY 'velogrimpe';
  GRANT ALL PRIVILEGES ON velogrimpe.* TO 'velogrimpe'@'localhost';
  FLUSH PRIVILEGES;"
```

6. Demandez nous un export de la base, ou au moins du schéma, et importez-le. L'export ne portant pas de `CREATE DATABASE`, le nom de la base se passe en argument :

```bash
docker exec -i velogrimpe /opt/lampp/bin/mysql -uroot velogrimpe < db_backup.sql
```

Vérifiez ensuite que le site répond avec des données :

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:4000/carte.php   # 200 attendu
```

L'export ne contient que les données SQL. Les fichiers associés (images de falaises, GPX, GeoJSON des barres, tuiles de lignes de train) se récupèrent séparément et se déposent dans `public/`.

## Chemins de données

Les contenus téléversés (images de falaises, images d'articles et de newsletters,
traces GPX) et les fichiers générés (GeoJSON de barres, exports open data) sont
des **données**, pas du code : ils ne sont pas versionnés et ne doivent pas vivre
dans le dossier déployé, qu'un déploiement peut effacer.

**Règle : aucun chemin de données ne se construit à la main.** Tout passe par
`lib/paths.php`, en lecture comme en écriture.

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';

vg_data_path('bdd/gpx/12_x_y_.gpx');   // chemin absolu sur disque
vg_data_url('bdd/gpx/12_x_y_.gpx');    // '/bdd/gpx/12_x_y_.gpx' — URL publique
vg_data_exists('bdd/barres/1_x.geojson');
vg_data_prepare('bdd/images_news/mon-slug');  // crée le dossier, vérifie l'écriture
```

Les chemins passés sont ceux de **l'espace d'URL** (`bdd/…`, `images/…`,
`open-data/…`), jamais des chemins disque. `vg_data_rel()` refuse les remontées
(`..`), les segments vides et les racines inconnues.

Deux points à respecter :

- **`vg_data_url()` ne dépend pas de l'emplacement des fichiers** et ne doit
  jamais en dépendre. Les URL produites partent en base (`newsletters.sections`,
  `pages.sections`, `pages.banner_img`), dans les exports open data et dans des
  mails déjà envoyés. Ne jamais reconstruire une URL par soustraction du
  `DOCUMENT_ROOT` d'un chemin disque : à travers le lien symbolique, la cible est
  hors `DOCUMENT_ROOT` et la soustraction ne matche plus.
- **`vg_data_prepare()` s'appelle avant la première mutation de la requête** —
  avant l'`INSERT`, avant `move_uploaded_file`. C'est le seul moment où un échec
  ne laisse pas de ligne en base sans son fichier.

### Où vivent les fichiers

Les données sont stockées **hors du dossier déployé**, dans `~/public` sur le
serveur et à la racine du dépôt en local (dossier git-ignoré). Elles y sont
atteintes par le lien symbolique versionné `public_html/public -> ../public`,
et `VG_DATA_MOUNT` (dans `lib/paths.php`) porte ce préfixe.

Les URL publiques, elles, restent `/bdd/…` et `/images/…`. Le `.htaccess` racine
fait le lien :

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(bdd|images)/(.+)$ /public/$1/$2 [L]
```

La condition `!-f` fait cohabiter les deux natures de fichiers : les **assets
versionnés** restés dans le dossier déployé (logos, icônes de carte, styles de
carte, `bdd/trains/gares.json`) sont trouvés sur place, les **données** sont
résolues vers le point de montage.

> **Corollaire à connaître** : une donnée qui traînerait dans le dossier déployé
> au même chemin relatif serait servie **à la place** de celle du point de
> montage, sans erreur. Règle : dans `public_html/bdd`, `public_html/images` et
> `public_html/open-data`, rien qui ne soit versionné.

Attention aussi aux fichiers **versionnés lus par PHP** à travers le helper :
eux sont cherchés dans le point de montage, sans repli possible (le repli est
une règle Apache, elle ne joue que pour les requêtes HTTP). C'est le cas de
`bdd/cartotrain/tableau.xlsx`, lu par `api/private/crons/ingest_cartotrain.php` :
il doit exister dans `~/public/bdd/cartotrain/`.

Le même `.htaccess` refuse toute exécution de script et tout listing sous
`/public/` (motif cherchant l'extension n'importe où dans le nom, pour couvrir
`shell.php.jpg`). Ces garanties sont posées à la racine et pas seulement dans le
`.htaccess` de la cible : derrière un lien symbolique pointant hors du
`DocumentRoot`, un `.htaccess` enfant peut être ignoré selon la portée
d'`AllowOverride`.

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
