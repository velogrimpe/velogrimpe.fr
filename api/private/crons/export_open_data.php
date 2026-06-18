<?php
// Emit the shortest float representation that round-trips (e.g. 3.8, not
// 3.79999999999998) so coordinates and distances stay clean in the GeoJSON,
// regardless of the server's default serialize_precision.
ini_set('serialize_precision', '-1');

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$vg_token = $config["vg_token"];
// Check that Authorization header is and equal to config["admin_token"]

// Allow CORS from all origins
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: authorization, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;

// Replace this with your actual token
$validToken = 'Bearer ' . $vg_token;

if (!$authHeader || $authHeader !== $validToken) {
  http_response_code(401);
  echo json_encode([
    'error' => 'Unauthorized',
  ]);
  exit;
}

// add a pageview
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/pv.php';
sendEvent($_SERVER['REQUEST_URI'], "vg", "vg-crons", 'event: export-open-data');

// Cron logic
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Attribution embarquée sur chaque Feature : les membres racine du
// FeatureCollection ne sont lus par aucun client carto (Leaflet/MapLibre/uMap),
// alors qu'une propriété de feature survit aux popups uMap, à QGIS/ogr2ogr et
// aux ré-exports.
$ATTRIBUTION = '© velogrimpe.fr et contributeurs — CC BY-SA 4.0 / ODbL 1.0';

// Parse un fichier GPX en géométrie GeoJSON. Retourne une LineString (un seul
// segment) ou une MultiLineString (plusieurs segments), coordonnées au format
// [lon, lat, ele?] arrondies, ou null si aucun point de tracé exploitable.
//
// Robustesse vis-à-vis des formats hétérogènes du dossier (Openrunner, Komoot,
// GraphHopper, gpxpy…) :
//  - tracés <trk>/<trkseg>/<trkpt> et, à défaut, routes <rte>/<rtept> ;
//  - namespace par défaut GPX 1/0 ou 1/1 (SimpleXML lit les enfants par nom) ;
//  - point ignoré si lat/lon absents ;
//  - élévation incluse uniquement si TOUS les points en ont une : on évite de
//    mélanger des positions 2D et 3D dans une même géométrie (GeoJSON invalide
//    pour les parseurs stricts).
function gpx_to_geometry(string $path): ?array
{
  $xml = @simplexml_load_file($path);
  if ($xml === false) {
    return null;
  }

  // Collecte les points bruts (lat/lon/ele) par segment, sans décider encore de
  // la dimensionnalité.
  $rawSegments = [];
  $collect = function ($points) {
    $pts = [];
    foreach ($points as $pt) {
      if (!isset($pt['lat']) || !isset($pt['lon'])) {
        continue;
      }
      $hasEle = isset($pt->ele) && (string) $pt->ele !== '';
      $pts[] = [
        'lon' => round((float) $pt['lon'], 6),
        'lat' => round((float) $pt['lat'], 6),
        'ele' => $hasEle ? round((float) $pt->ele, 1) : null,
      ];
    }
    return $pts;
  };

  foreach ($xml->trk as $trk) {
    foreach ($trk->trkseg as $seg) {
      $pts = $collect($seg->trkpt);
      if (count($pts) >= 2) {
        $rawSegments[] = $pts;
      }
    }
  }
  if (empty($rawSegments)) {
    foreach ($xml->rte as $rte) {
      $pts = $collect($rte->rtept);
      if (count($pts) >= 2) {
        $rawSegments[] = $pts;
      }
    }
  }

  if (empty($rawSegments)) {
    return null;
  }

  // 3D seulement si chaque point de chaque segment porte une élévation.
  $includeEle = true;
  foreach ($rawSegments as $pts) {
    foreach ($pts as $p) {
      if ($p['ele'] === null) {
        $includeEle = false;
        break 2;
      }
    }
  }

  $segments = array_map(function ($pts) use ($includeEle) {
    return array_map(function ($p) use ($includeEle) {
      return $includeEle ? [$p['lon'], $p['lat'], $p['ele']] : [$p['lon'], $p['lat']];
    }, $pts);
  }, $rawSegments);

  if (count($segments) === 1) {
    return ['type' => 'LineString', 'coordinates' => $segments[0]];
  }
  return ['type' => 'MultiLineString', 'coordinates' => $segments];
}

// Falaises export to geojson
$falaises = $mysqli->query("SELECT
  falaise_id as id,
  falaise_nom as nom,
  falaise_nomformate as nomformate,
  falaise_latlng as latlng,
  falaise_zonename as zone,
  falaise_deptcode as dept,
  falaise_deptname as deptname,
  falaise_exposhort1 as exposhort1,
  falaise_exposhort2 as exposhort2,
  falaise_cotmin as cotmin,
  falaise_cotmax as cotmax,
  falaise_maa as maa,
  falaise_mar as mar,
  falaise_topo as topo,
  falaise_expotxt as expotxt,
  falaise_matxt as matxt,
  falaise_voies as voies,
  falaise_gvtxt as gvtxt,
  falaise_gvnb as gvnb,
  falaise_rq as rq,
  falaise_fermee as fermee,
  falaise_bloc as bloc,
  falaise_nbvoies as nbvoies
  from falaises")->fetch_all(MYSQLI_ASSOC);

// Pre-load all bike itineraries + their departure station, grouped by falaise.
// Reuse the velo/gares join from fetch_sortie_velos.php but without any public
// filter and for every crag (open data exports everything).
$velosByFalaise = [];
$veloResult = $mysqli->query("SELECT
  v.velo_id,
  v.falaise_id,
  v.velo_depart,
  v.velo_arrivee,
  v.velo_varianteformate,
  v.velo_km,
  v.velo_dplus,
  v.velo_dmoins,
  v.velo_descr,
  v.velo_openrunner,
  v.velo_variante,
  v.velo_apieduniquement,
  v.velo_apiedpossible,
  g.gare_nom,
  g.gare_commune,
  g.gare_departement,
  g.gare_latlng,
  g.gare_codeuic,
  g.gare_tgv
  FROM velo v
  LEFT JOIN gares g ON v.gare_id = g.gare_id
  ORDER BY v.falaise_id, v.velo_km ASC")->fetch_all(MYSQLI_ASSOC);

// Lookup falaise_id => nom pour étiqueter les tracés vélo sans relancer de requête.
$falaiseNomById = [];
foreach ($falaises as $f) {
  $falaiseNomById[$f['id']] = $f['nom'];
}

// Collection des tracés vélo (géométrie issue des GPX), à exporter à part : un
// fichier de Point (falaises) + un fichier de LineString (itinéraires) est plus
// digeste pour les clients carto qu'un GeoJSON mixant les types de géométrie.
$itinerairesGeojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];
$gpxMissing = 0; // tracés sans fichier GPX exploitable (compté pour le rapport)

foreach ($veloResult as $velo) {
  $itineraire = [
    'distance_km' => $velo['velo_km'] !== null ? round((float) $velo['velo_km'], 1) : null,
    'denivele_positif' => $velo['velo_dplus'] !== null ? (int) $velo['velo_dplus'] : null,
    'denivele_negatif' => $velo['velo_dmoins'] !== null ? (int) $velo['velo_dmoins'] : null,
    'a_pied_uniquement' => (bool) $velo['velo_apieduniquement'],
    'a_pied_possible' => (bool) $velo['velo_apiedpossible'],
    'variante' => $velo['velo_variante'],
    'description' => $velo['velo_descr'],
    'openrunner' => $velo['velo_openrunner'] ?: null,
  ];
  if (!empty($velo['gare_nom'])) {
    $gare = [
      'nom' => $velo['gare_nom'],
      'commune' => $velo['gare_commune'],
      'departement' => $velo['gare_departement'],
      'code_uic' => $velo['gare_codeuic'] ?: null,
      'tgv' => (bool) $velo['gare_tgv'],
    ];
    if (!empty($velo['gare_latlng']) && strpos($velo['gare_latlng'], ',') !== false) {
      [$glat, $glng] = explode(',', $velo['gare_latlng']);
      $gare['coordonnees'] = [round((float) $glng, 6), round((float) $glat, 6)];
    }
    $itineraire['gare_depart'] = $gare;
  }
  $velosByFalaise[$velo['falaise_id']][] = $itineraire;

  // Tracé GPX : même convention de nommage que falaise.php / paths.js
  // ({velo_id}_{depart}_{arrivee}_{varianteformate}.gpx). Les itinéraires sans
  // GPX (ou GPX vide) sont simplement omis de l'export géométrique.
  $gpxName = $velo['velo_id'] . '_' . $velo['velo_depart'] . '_' . $velo['velo_arrivee'] . '_' . $velo['velo_varianteformate'] . '.gpx';
  $gpxPath = $_SERVER['DOCUMENT_ROOT'] . '/bdd/gpx/' . $gpxName;
  $geometry = is_file($gpxPath) ? gpx_to_geometry($gpxPath) : null;
  if ($geometry === null) {
    $gpxMissing++;
    continue;
  }
  $itinerairesGeojson['features'][] = [
    'type' => 'Feature',
    'properties' => array_merge(
      [
        'velo_id' => (int) $velo['velo_id'],
        'falaise_id' => (int) $velo['falaise_id'],
        'falaise_nom' => $falaiseNomById[$velo['falaise_id']] ?? null,
        'attribution' => $ATTRIBUTION,
        'url' => 'https://velogrimpe.fr/falaise.php?falaise_id=' . (int) $velo['falaise_id'],
        'gpx_url' => 'https://velogrimpe.fr/bdd/gpx/' . $gpxName,
      ],
      $itineraire
    ),
    'geometry' => $geometry,
  ];
}

// Pre-load external partner links (oblyk, etc.), grouped by falaise.
$liensByFalaise = [];
$liensResult = $mysqli->query("SELECT falaise_id, site, site_url, site_name FROM falaises_liens")->fetch_all(MYSQLI_ASSOC);
foreach ($liensResult as $lien) {
  $liensByFalaise[$lien['falaise_id']][] = [
    'site' => $lien['site'],
    'url' => $lien['site_url'],
    'nom' => $lien['site_name'] ?: null,
  ];
}

// Pre-load bus stops linked to crags (bus_arrets_falaise), with their lines and
// liaisons (connections to other stops). Grouped by falaise. A stop can serve
// several crags: its entry (lines + liaisons) is built once then reused.
$arretEntryById = [];
$arretFalaises = []; // [ [falaise_id, arret_id], ... ]
$arretRows = $mysqli->query("SELECT baf.falaise_id, a.id, a.nom, a.description, a.osm_id,
    ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng
  FROM bus_arrets_falaise baf
  JOIN bus_arrets a ON a.id = baf.arret_id
  ORDER BY baf.falaise_id, a.nom")->fetch_all(MYSQLI_ASSOC);
foreach ($arretRows as $r) {
  $aid = (int) $r['id'];
  if (!isset($arretEntryById[$aid])) {
    $arretEntryById[$aid] = [
      'id' => $aid,
      'nom' => $r['nom'],
      'description' => $r['description'],
      'osm_id' => $r['osm_id'] ?: null,
      'coordonnees' => [round((float) $r['lng'], 6), round((float) $r['lat'], 6)],
      'lignes' => [],
      'liaisons' => [],
      '_ligneIds' => [],
    ];
  }
  $arretFalaises[] = [(int) $r['falaise_id'], $aid];
}
// Attach liaisons + distinct lines to each exported stop (edges are non-oriented :
// chaque liaison est vue depuis chacun de ses deux arrêts).
if (!empty($arretEntryById)) {
  $liaisonRows = $mysqli->query("SELECT l.arret_1_id, l.arret_2_id, l.description AS liaison_descr,
      li.id AS ligne_id, li.nom AS ligne_nom, li.description AS ligne_descr, li.lien AS ligne_lien,
      a1.nom AS arret_1_nom, a2.nom AS arret_2_nom
    FROM bus_liaisons l
    JOIN bus_lignes li ON li.id = l.ligne_id
    JOIN bus_arrets a1 ON a1.id = l.arret_1_id
    JOIN bus_arrets a2 ON a2.id = l.arret_2_id")->fetch_all(MYSQLI_ASSOC);
  foreach ($liaisonRows as $lr) {
    $endpoints = [
      [(int) $lr['arret_1_id'], $lr['arret_2_nom']],
      [(int) $lr['arret_2_id'], $lr['arret_1_nom']],
    ];
    foreach ($endpoints as [$selfId, $otherNom]) {
      if (!isset($arretEntryById[$selfId])) {
        continue;
      }
      $arretEntryById[$selfId]['liaisons'][] = [
        'arret_relie' => $otherNom,
        'ligne' => $lr['ligne_nom'],
        'description' => $lr['liaison_descr'],
      ];
      $arretEntryById[$selfId]['_ligneIds'][(int) $lr['ligne_id']] = [
        'nom' => $lr['ligne_nom'],
        'description' => $lr['ligne_descr'],
        'lien' => $lr['ligne_lien'] ?: null,
      ];
    }
  }
  foreach ($arretEntryById as $aid => $entry) {
    $arretEntryById[$aid]['lignes'] = array_values($entry['_ligneIds']);
    unset($arretEntryById[$aid]['_ligneIds']);
  }
}
$arretsByFalaise = [];
foreach ($arretFalaises as [$fid, $aid]) {
  $arretsByFalaise[$fid][] = $arretEntryById[$aid];
}

$geojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];

// Merged collection of every crag's geometric topo details (sectors, parkings,
// approaches, bike access…), each feature tagged with its falaise_id.
$detailsGeojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];
foreach ($falaises as $falaise) {
  $lat = explode(',', $falaise['latlng'])[0];
  $lng = explode(',', $falaise['latlng'])[1];
  $properties = [
    'id' => (int) $falaise['id'],
    'nom' => $falaise['nom'],
    'attribution' => $ATTRIBUTION,
    'url' => 'https://velogrimpe.fr/falaise.php?falaise_id=' . (int) $falaise['id'],
    'zone' => $falaise['zone'],
    'departement_code' => $falaise['dept'],
    'departement_nom' => $falaise['deptname'],
    'exposition' => $falaise['exposhort1'],
    'exposition_secondaire' => $falaise['exposhort2'],
    'cotation_min' => $falaise['cotmin'],
    'cotation_max' => $falaise['cotmax'],
    'code_nbvoies' => (int) $falaise['nbvoies'],
    'bloc_type' => (int) $falaise['bloc'],
    'nb_grandes_voies' => $falaise['gvnb'],
    'topo' => $falaise['topo'] !== '' ? $falaise['topo'] : null,
    'fermee' => $falaise['fermee'] !== '',
    'approche_aller' => $falaise['maa'],
    'approche_retour' => $falaise['mar'],
    'description_exposition' => $falaise['expotxt'],
    'description_approche' => $falaise['matxt'],
    'description_voies' => $falaise['voies'],
    'description_grandes_voies' => $falaise['gvtxt'],
    'remarques' => $falaise['rq'],
    'itineraires_velo' => $velosByFalaise[$falaise['id']] ?? [],
    'liens_externes' => $liensByFalaise[$falaise['id']] ?? [],
    'arrets_bus' => $arretsByFalaise[$falaise['id']] ?? [],
  ];

  // Link to the geometric topo details file only when it exists, and merge its
  // features into the global details collection (tagging each with falaise_id).
  $details_file = $_SERVER['DOCUMENT_ROOT'] . '/bdd/barres/' . $falaise['id'] . '_' . $falaise['nomformate'] . '.geojson';
  if (file_exists($details_file)) {
    $properties['details_url'] = 'https://velogrimpe.fr/bdd/barres/' . $falaise['id'] . '_' . $falaise['nomformate'] . '.geojson';

    $details_content = json_decode(file_get_contents($details_file), true);
    if (is_array($details_content) && !empty($details_content['features']) && is_array($details_content['features'])) {
      foreach ($details_content['features'] as $detail_feature) {
        if (!is_array($detail_feature)) {
          continue;
        }
        $detail_props = $detail_feature['properties'] ?? [];
        // "falaise_voisine" features carry their own falaise_id pointing to the
        // neighbouring crag; preserve it under a distinct key so the injected
        // parent falaise_id stays authoritative for traceability.
        if (isset($detail_props['falaise_id'])) {
          $detail_props['voisine_falaise_id'] = $detail_props['falaise_id'];
        }
        // Prepend the parent falaise_id / falaise_nom (authoritative) and the
        // attribution (overridable order: injected last to stay authoritative).
        $detail_feature['properties'] = array_merge(
          [
            'falaise_id' => (int) $falaise['id'],
            'falaise_nom' => $falaise['nom'],
            'attribution' => $ATTRIBUTION,
          ],
          $detail_props,
          [
            'falaise_id' => (int) $falaise['id'],
            'attribution' => $ATTRIBUTION,
          ]
        );
        $detailsGeojson['features'][] = $detail_feature;
      }
    }
  }

  $feature = [
    'type' => 'Feature',
    'properties' => $properties,
    'geometry' => [
      'type' => 'Point',
      // explode and cast to float rounded to 6 decimals and reverse order for geojson [lng, lat]
      'coordinates' => [
        round((float) $lng, 6),
        round((float) $lat, 6),
      ],
    ],
  ];
  $geojson['features'][] = $feature;
}

// Gares (référentiel complet hors gares supprimées) : un point par gare, avec
// commune/département, codes d'identification (UIC SNCF, OSM) et flag TGV.
$garesGeojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];
$garesResult = $mysqli->query("SELECT
  gare_id,
  gare_nom,
  gare_latlng,
  gare_departement,
  gare_commune,
  gare_codeuic,
  gare_codeosm,
  gare_tgv
  FROM gares
  WHERE deleted = 0
  ORDER BY gare_id")->fetch_all(MYSQLI_ASSOC);
foreach ($garesResult as $gare) {
  if (empty($gare['gare_latlng']) || strpos($gare['gare_latlng'], ',') === false) {
    continue;
  }
  [$glat, $glng] = explode(',', $gare['gare_latlng']);
  $garesGeojson['features'][] = [
    'type' => 'Feature',
    'properties' => [
      'id' => (int) $gare['gare_id'],
      'nom' => $gare['gare_nom'],
      'attribution' => $ATTRIBUTION,
      'commune' => $gare['gare_commune'],
      'departement' => $gare['gare_departement'],
      'code_uic' => $gare['gare_codeuic'] ?: null,
      'code_osm' => $gare['gare_codeosm'] ?: null,
      'tgv' => (bool) $gare['gare_tgv'],
    ],
    'geometry' => [
      'type' => 'Point',
      'coordinates' => [round((float) $glng, 6), round((float) $glat, 6)],
    ],
  ];
}

// Save to file
$file = $_SERVER['DOCUMENT_ROOT'] . '/open-data/falaises.geojson';
file_put_contents($file, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save the merged details collection
$details_export = $_SERVER['DOCUMENT_ROOT'] . '/open-data/falaises-details.geojson';
file_put_contents($details_export, json_encode($detailsGeojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save the bike itineraries collection (GPX tracks merged into one GeoJSON)
$itineraires_export = $_SERVER['DOCUMENT_ROOT'] . '/open-data/itineraires-velo.geojson';
file_put_contents($itineraires_export, json_encode($itinerairesGeojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save the gares collection
$gares_export = $_SERVER['DOCUMENT_ROOT'] . '/open-data/gares.geojson';
file_put_contents($gares_export, json_encode($garesGeojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Export complet : fusionne les collections en une seule, en taguant
// chaque feature d'un `vg_type` pour pouvoir les re-filtrer (les géométries sont
// mélangées : Point pour les falaises, LineString pour les itinéraires, types
// variés pour les détails). Les FeatureCollection sources restent disponibles
// séparément pour les clients qui ne veulent qu'une couche.
$completGeojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];
$sources = [
  'falaise' => $geojson['features'],
  'itineraire_velo' => $itinerairesGeojson['features'],
  'gare' => $garesGeojson['features'],
  'detail' => $detailsGeojson['features'],
];
foreach ($sources as $vgType => $features) {
  foreach ($features as $feature) {
    $feature['properties']['vg_type'] = $vgType;
    $completGeojson['features'][] = $feature;
  }
}
$complet_export = $_SERVER['DOCUMENT_ROOT'] . '/open-data/complet.geojson';
file_put_contents($complet_export, json_encode($completGeojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Respond with success
http_response_code(200);
echo json_encode([
  'status' => 'success',
  'message' => 'Open data exported successfully',
  'falaises' => count($geojson['features']),
  'itineraires_velo' => count($itinerairesGeojson['features']),
  'itineraires_velo_sans_gpx' => $gpxMissing,
  'gares' => count($garesGeojson['features']),
  'details' => count($detailsGeojson['features']),
  'complet' => count($completGeojson['features']),
]);