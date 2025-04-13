# Velogrimpe.fr

Ce dépôt git contient le code du site [Velogrimpe.fr](https://velogrimpe.fr).
Il contient tout le code nécessaire pour faire fonctionner le site sur un hébergement PHP comme l'offre Single Web Hosting de Hostinger (qui déploie un serveur PHP servant les fichiers du site.). Le site ne nécessite pas de phase de build.

Velogrimpe.fr est un site communautaire visant à promouvoir la mobilité douce pour aller en falaise. Les personnes derrière Velogrimpe.fr sont des bénévoles sans intérêts commerciaux liés au site ou à ses données. Afin de péréniser l'existence du site et des idées qui le sous-tendent, et de permettre des réutilisation pour appliquer le principe à d'autres pratiques outdoor, nous avons décidé de publier le code du site. Ce code est mis à disposition publiquement sous [licence](./LICENCE) `CC BY-NC-SA 4.0`, qui impose la citation, la non-commercialisation et le partage avec la même licence (copyleft).

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
docker run --platform linux/x86_64 --name myXampp -p 4001:22 -p 4000:80 -d -v $ROOTPARENT/velo-grimpe/public_html:/opt/lampp/htdocs --mount type=bind,source=$ROOTPARENT$/velo-grimpe/config.php,target=/opt/lampp/config.php,readonly tomsik68/xampp:8
```

Une fois lancé, ce conteneur est synchronisé avec votre dossier local et sert :

- Sur le port 4000, le site déployé en local (http://localhost:4000)
- Sur http://localhost:4000/phpmyadmin l'interface pour administrer la base de donnée locale (éphémère, supprimée à chaque re-création du conteneur)

À partir de là, tout ce que vous changez dans votre éditeur de code est répecuté sur le serveur local (pas de refresh automatique, il faut faire un Cmd/Ctrl+R pour voir les changements).

5. Sur l'interface de phpmyadmin, créez deux nouvelles bases de données : `velogrimpe` et `sncf` ainsi que deux utilisateurs portant les même noms et ayant accès à ces bases de données.
6. Demandez nous un export de la base ou au moins du schéma et importez les dans les bases respectives.
