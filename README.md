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
- Le dossier `admin/` (Basic Auth) regroupe les pages d'administration ; `admin/arrets_lignes.php` liste les arrêts et les lignes (recherche par arrêt, commune, falaise reliée, n°…, filtres par type d'arrêt — TER/TGV masqués par défaut — et vue carte) avec un lien vers leur page de modification, un bouton de suppression des arrêts (suppression logique `deleted = 1`, via `api/delete_arret.php`, Bearer admin ; refusée pour un parent ou pour le dernier enfant d'un parent) et de **regroupement** de deux arrêts (voir « Arrêts parents et enfants » plus bas : `api/merge_arrets.php`, `api/detach_arret.php`, `api/unmerge_arret.php`, Bearer admin). Chaque ligne a un bouton de suppression (`api/delete_ligne.php`, Bearer admin) qui demande dans un dialogue s'il faut supprimer aussi ses arrêts : ils sont alors supprimés définitivement (références GTFS, liens aux lignes et exclusions de villes comprises), mais seulement ceux qui ne sont reliés à aucune falaise ni à aucun trajet train (ni eux ni leur parent) et ne desservent aucune autre ligne ; un parent laissé sans enfant disparaît aussi. La liste affiche les falaises reliées à chaque arrêt, avec un bouton « Voir sur la carte » par arrêt et par ligne. Sur la carte, « Déplacer » rend un arrêt déplaçable et fixe sa position à la main (`position_manuelle = 1`, `api/move_arret.php`, Bearer admin ; « Position auto » rend à un parent le barycentre de ses enfants), les arrêts regroupés sont entourés d'une enveloppe à fort zoom, l'affichage des falaises sur la carte et la liaison arrêt ↔ falaise (icône « lier » du popup, clic sur la falaise / l'arrêt à relier, puis formulaire ; via `api/link_arret_falaise.php` : accès à pied ou à vélo avec description, km, D+, D-, GPX ; contributeur « Admin »). Filtres, onglet et position de la carte sont synchronisés dans l'URL (`?map=zoom/lat/lng`, zoom MapLibre : ~1 niveau sous l'ancien zoom Leaflet, les anciens liens sont donc décalés). La carte est en MapLibre GL JS (bundle `map-maplibre`, comme `carte.php`) : arrêts sans accès falaise, lignes et falaises en couches GeoJSON, arrêts reliés à une falaise et noms en marqueurs DOM.
- Le dossier `api/` contient les routes appelées par ces formulaires et par le front (`add_*.php`, `edit_velo.php`, `fetch_*.php`…) ; `api/private/` regroupe celles réservées à l'admin.
- Le dossier `lib/` contient le code PHP partagé entre plusieurs pages ou routes (ex. `velo_lib.php` pour la validation / nettoyage des GPX).
- Le dossier `js/` contient les quelques scripts utilisés sur le site.
- Le dossier `symbols/` contient les icones utilisés sur le site.
- Le dossier `images/` contient les images statiques, hors contenus falaises.
- Le lien symbolique `public/` pointe vers le dossier de données hors dépôt (`../public`) : images des falaises, GPX, GeoJSON des barres, exports open data, et `trains/` avec le GeoJSON des lignes de train françaises et sa version en tuiles (`.pmtiles`, pour ne charger que la partie visible). Voir « Chemins de données ».

## Mise en place d'un environnement de développement

### Pré-requis

- Installer `docker`

### Procédure

1. Cloner ce dépôt : `git clone https://github.com/velogrimpe/velogrimpe.fr.git velo-grimpe` (ou via ssh). Le dossier obtenu est le dossier racine, et le code du site vit dans son sous-dossier `public_html/`.
1. À la racine, créer le dossier de données (hors web root, cible du symlink `public_html/public`) :

```bash
mkdir -p public/{gpx,barres,images_falaises,images,open-data}
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

Cet export suit le schéma de la prod (`sql_migrations/0_base_schema.sql`). Tant que la prod n'a pas migré, amenez-le au schéma actuel avec la migration unique :

```bash
docker exec -i velogrimpe /opt/lampp/bin/mysql -uroot velogrimpe < sql_migrations/1_unification_arrets.sql
```

(non rejouable : pour recommencer, réimportez d'abord l'export).

Vérifiez ensuite que le site répond avec des données :

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:4000/carte.php   # 200 attendu
```

L'export ne contient que les données SQL. Les fichiers associés (images de falaises, GPX, GeoJSON des barres, tuiles de lignes de train) se récupèrent séparément et se déposent dans `public/`.

7. Tests : E2E Playwright dans `tests/e2e/` (cf. `tests/e2e/README.md`), tests d'intégration API PHPUnit dans `tests/api/` (cf. `tests/api/README.md`, `composer install` puis `composer test`).

## Arrêts parents et enfants

Fusionner deux arrêts ne supprime plus rien : `arrets.parent_id` regroupe des arrêts sous un **parent** (deux niveaux au plus). Un arrêt est un parent s'il a des enfants, un enfant s'il a un `parent_id`, un orphelin sinon. Implémentation : `lib/arrets_parents_lib.php`.

- **Parent** : position = barycentre de ses enfants (sauf `position_manuelle = 1`, posé par un déplacement à la main), modalités (`ter`, `tgv`, `bus`, `tram`, `teleph`) = OU de celles des enfants. Il porte `acces_falaise` et `train` ; ses enfants gardent `arrets_lignes_transport` et `references_externes` (références GTFS). Un parent n'est pas sélectionnable pour une ligne (on lie ses enfants) et un enfant ne l'est ni pour un accès ni pour un trajet train (on choisit le parent) : formulaires, éditeur de falaise, `add_velo.php`, `add_train.php`, `add_falaise.php` le refusent.
- **Fusion manuelle** (`api/merge_arrets.php`, `{ source_id, cible_id }`) : deux orphelins → nouveau parent (nom et attributs de `cible_id`) ; parent + orphelin → l'orphelin rejoint le parent ; deux parents → `cible_id` absorbe les enfants de `source_id` (aplatissement). Refus 409 : enfant (le détacher d'abord, ou fusionner son parent), arrêt supprimé, modes de transport sans famille commune (TER et TGV vont ensemble, les autres modes sont distincts), accès identique (même falaise, type, variante).
- **Détacher / défusionner** : `api/detach_arret.php` (un enfant redevient orphelin, sauf s'il est le dernier) ; `api/unmerge_arret.php` (les enfants redeviennent orphelins, le parent est supprimé logiquement ; `heritier_id` reçoit alors ses accès et trajets).
- **Import GTFS** (`ajout/ajout_ligne.php`, politique dans `frontend/src/utils/gtfs-match.ts`, détail dans `docs/conception-bus.md`) : chaque `stop_id` du GTFS (quai ou sens) devient un arrêt ; un arrêt existant proche est repris (identité) ou regroupé selon ses références GTFS ; les arrêts d'une même station GTFS ou de même nom à 200 m ou moins sont regroupés.
- **Carte** (`carte.php`) : `?sync=true` synchronise la vue dans le hash de l'URL (`#zoom/lat/lng`, zoom MapLibre), relue au chargement. Désactivé par défaut.
- **Carte** (`carte.php`) : un parent seul (et nommé) à bas zoom ; à partir du zoom MapLibre 14 il s'efface au profit de ses enfants, chacun avec son nom (collisions gérées par les symboles MapLibre) et entourés d'une enveloppe convexe élargie de 15 m dès deux enfants. `arretsBase` porte `enfants` ; les lignes des enfants remontent sur le parent (carte, fiche falaise, tableau, open data, `fetch_lignes.php?arret_id=`). Dans l'admin, parent et enfants sont tous deux représentés : le parent (au barycentre) plus gros, les enfants plus petits avec un halo très fin.
- **Open data** : les enfants ne sont pas exportés comme arrêts ; les lignes (`lignes.geojson`, `arrets.geojson`) sont rattachées au parent.
- Migration : `sql_migrations/2_arrets_parents.sql` (à jouer après `1_unification_arrets.sql`) ; pas de reprise de données : les arrêts déjà fusionnés restent des orphelins à plusieurs références GTFS (réimporter les lignes pour obtenir des enfants).

## Chemins de données

Les contenus téléversés (images de falaises, images de newsletters et de pages,
traces GPX) et les fichiers générés (GeoJSON de barres, exports open data) sont
des **données**, pas du code : ils ne sont pas versionnés et ne vivent pas dans
le dossier déployé, qu'un déploiement peut effacer.

Ils sont stockés dans `public/` — à la racine du dépôt en local, `~/public` sur
le serveur — atteint par le lien symbolique versionné
`public_html/public -> ../public`. **Chemin disque et URL coïncident** : le
fichier `public/gpx/12_x_y_.gpx` est servi à `/public/gpx/12_x_y_.gpx`. Une
seule adresse par fichier.

**Règle : aucun chemin de données ne se construit à la main.** Tout passe par
`lib/paths.php`, en lecture comme en écriture.

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';

vg_data_path('gpx/12_x_y_.gpx');   // /…/public_html/public/gpx/12_x_y_.gpx
vg_data_url('gpx/12_x_y_.gpx');    // /public/gpx/12_x_y_.gpx — URL publique
vg_data_exists('barres/1_x.geojson');
vg_data_prepare('images_news/mon-slug');  // crée le dossier, vérifie l'écriture
```

Les chemins passés sont relatifs à la racine des données (`gpx/…`, `barres/…`,
`images_falaises/…`), jamais des chemins disque absolus. `vg_data_rel()` refuse
les segments vides et tout segment commençant par un point — ce qui couvre `.`
et `..`, et met `public/.htaccess` hors d'atteinte d'une écriture.

Il n'y a **pas** de liste blanche de dossiers : tout ce qui vit sous le point de
montage est de la donnée, et l'isolement tient entièrement aux contrôles
ci-dessus. Un nouveau dossier de données s'utilise directement, sans rien
déclarer.

Deux points à respecter :

- **Toujours produire les URL avec `vg_data_url()`.** Elles partent en base
  (`newsletters.sections`, `pages.sections`, `pages.banner_img`), dans les
  exports open data et dans les mails. Ne jamais reconstruire une URL par
  soustraction du `DOCUMENT_ROOT` d'un chemin disque : à travers le lien
  symbolique, la cible est hors `DOCUMENT_ROOT` et la soustraction ne matche pas.
- **`vg_data_prepare()` s'appelle avant la première mutation de la requête** —
  avant l'`INSERT`, avant `move_uploaded_file`. C'est le seul moment où un échec
  ne laisse pas de ligne en base sans son fichier.

### Les deux exceptions

Deux sous-arbres de `public/` ont une URL publique qui ne suit pas
`vg_data_url()`. Aucun appelant n'utilise le helper pour eux ; ne pas commencer.

| Dossier      | URL publique           | Pourquoi                                                                                                               |
| ------------ | ---------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `open-data/` | `/open-data/*.geojson` | servie par `open-data/download.php`, qui trace les téléchargements. Le `.htaccess` y réécrit en interne.               |
| `images/`    | `/images/…`            | encore dupliqué entre `public_html/images` (assets versionnés : logos, icônes de carte) et `public/images` (le reste). |

Pour `images/`, le `.htaccess` racine arbitre fichier par fichier :

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^images/(.+)$ /public/images/$1 [L]
```

L'asset versionné est trouvé dans le dossier déployé, le reste est résolu vers
les données. Le mélange étant au niveau des fichiers (`images/mw/` contient les
deux natures), cette duplication n'est pas encore tranchée.

### Anciennes URL

Les données vivaient auparavant sous `public_html/bdd/`, servies à `/bdd/…`. Ce
dossier intermédiaire a disparu, son contenu est remonté à la racine du point de
montage. Le `.htaccess` racine redirige :

```apache
RewriteRule ^bdd/(.+)$ /public/$1 [R=301,L]
```

301 et non réécriture interne : `/public/…` est la forme canonique. La
redirection couvre ce qui a déjà été publié — mails envoyés, exports open data
téléchargés par des tiers, index des moteurs.

### Sécurité

Le `.htaccess` racine refuse toute exécution de script et tout listing sous
`/public/` **et** sous l'ancien `/bdd/`, avec un motif qui cherche l'extension
n'importe où dans le nom (pour couvrir `shell.php.jpg`). Ces garanties sont
posées à la racine et pas seulement dans `public/.htaccess` : derrière un lien
symbolique pointant hors du `DocumentRoot`, un `.htaccess` enfant peut être
ignoré selon la portée d'`AllowOverride`.

## Partage et réutilisation

### Code

Velogrimpe.fr est un site communautaire visant à promouvoir la mobilité douce pour aller en falaise. Les personnes derrière Velogrimpe.fr sont des bénévoles sans intérêts commerciaux liés au site ou à ses données. Afin de péréniser l'existence du site et des idées qui le sous-tendent, et de permettre des réutilisation pour appliquer le principe à d'autres pratiques outdoor, nous avons décidé de publier le code du site. Ce code est mis à disposition publiquement sous [licence](./LICENCE) `CC BY-NC-SA 4.0`, qui impose la citation, la non-commercialisation et le partage avec la même licence (copyleft).

### Données

Dans une démarche de partage de notre code et du contenu du site, nous avons choisi de diffuser les contenus du site sous des licences libres compatibles avec les autres ressources de références du domaine (OSM, Camp2Camp). Concrètement, cela autorise tout contributeur à copier/coller le contenu d'une fiche falaise issue de C2C (licence CC BY-SA) dans vélogrimpe et inversement.

Les contenus publiés sur velogrimpe.fr sont diffusés sous les licences suivantes :

- **Textes** : [CC BY-SA](https://creativecommons.org/licenses/by-sa/4.0/deed.fr) → Permet de copier et réutiliser les textes du site sur d'autres supports à licence compatible (ex: C2C) (sauf mention contraire par l'auteur dans la fiche falaise)
- **Images** : [CC BY-NC-ND](https://creativecommons.org/licenses/by-nc-nd/4.0/deed.fr) → Utilisation commerciale interdite. Diffusion à l'identique et en citant la source. (sauf licence spécifique précisée par l'auteur dans la légende ou sur l'image)
- **Données** : [ODbL](https://opendatacommons.org/licenses/odbl/1-0/) et [CC BY-SA](https://creativecommons.org/licenses/by-sa/4.0/deed.fr) → Compatible OpenStreetMap et C2C, permet de réutiliser les données pour enrichir OSM.

Six exports GeoJSON sont disponibles, regénérés quotidiennement au cours de la nuit et exploitables par exemple directement dans [UMAP](https://umap.openstreetmap.fr/) :

- [falaises.geojson](https://velogrimpe.fr/open-data/falaises.geojson) : les falaises (points) et, embarqués dans leurs propriétés, leurs itinéraires vélo, liens externes et arrêts de bus.
- [itineraires-velo.geojson](https://velogrimpe.fr/open-data/itineraires-velo.geojson) : les tracés complets des itinéraires vélo (LineString) reconstruits à partir des fichiers GPX, avec distance, dénivelés et gare de départ. Chaque tracé porte `acces_id` (anciennement `velo_id`, renommé au Lot 8quater : même valeur, c'est aussi le préfixe du fichier GPX) et `a_pied_possible` (vrai pour un tracé de 5 km ou moins). Le champ `a_pied_uniquement` n'existe plus (Lot 8) : les accès à pied sont des liens arrêt → falaise (type `pied` de la table `acces_falaise`), pas des itinéraires.
- [arrets.geojson](https://velogrimpe.fr/open-data/arrets.geojson) : les arrêts (points : gares TER/TGV, bus, tram, téléphérique, hors arrêts supprimés), avec commune, code INSEE, codes SNCF/OSM et les lignes qui les desservent (propriété `lignes`, sans géométrie). Remplace l'ancien `gares.geojson`.
- [lignes.geojson](https://velogrimpe.fr/open-data/lignes.geojson) : les lignes de bus / BTT (géométrie MultiLineString, numéro, opérateur, horaires, couleur) et les ids des arrêts qu'elles desservent (`arret_ids`).
- [falaises-details.geojson](https://velogrimpe.fr/open-data/falaises-details.geojson) : les détails géométriques agrégés de toutes les falaises (secteurs, parkings, approches…).
- [complet.geojson](https://velogrimpe.fr/open-data/complet.geojson) : les collections ci-dessus réunies en un seul fichier. Chaque entité porte une propriété `vg_type` (`falaise`, `itineraire_velo`, `arret`, `ligne` ou `detail`) pour pouvoir les re-filtrer.
