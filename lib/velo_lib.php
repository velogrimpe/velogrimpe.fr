<?php
/**
 * Fonctions partagées entre l'ajout (api/add_velo.php) et l'édition
 * (api/edit_velo.php) d'un itinéraire vélo (cf. docs/plans/DECISIONS.md D009).
 *
 * Toutes les fonctions de validation terminent la requête par die() avec un
 * message lisible, comme le faisait historiquement add_velo.php : ces endpoints
 * répondent à une soumission de formulaire classique (navigation), pas en JSON.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';

const GPX_TAILLE_MAX = 10 * 1024 * 1024;

/**
 * Lit les indicateurs (longueur, D+, D-) depuis $_POST.
 * Retourne [velo_km (float|null), velo_dplus (int|null), velo_dmoins (int|null)].
 */
function velo_lire_indicateurs(array $post): array
{
  $velo_km = (isset($post['velo_km']) && $post['velo_km'] !== '') ? floatval($post['velo_km']) : null;
  $velo_dplus = (isset($post['velo_dplus']) && $post['velo_dplus'] !== '') ? intval($post['velo_dplus']) : null;
  $velo_dmoins = (isset($post['velo_dmoins']) && $post['velo_dmoins'] !== '') ? intval($post['velo_dmoins']) : null;
  return [$velo_km, $velo_dplus, $velo_dmoins];
}

/**
 * Vérifie la présence des champs obligatoires ; une valeur numérique (y compris 0)
 * est acceptée.
 */
function velo_verifier_obligatoires(array $champs): void
{
  foreach ($champs as $champ => $valeur) {
    if (empty($valeur) && !is_numeric($valeur)) {
      die("Il manque une info obligatoire : " . $champ);
    }
  }
}

/**
 * Les slugs qui composent le nom du fichier GPX sont validés, pas reformatés
 * (D002) : falaise.php et gpx_path() côté JS reconstruisent ce chemin depuis les
 * valeurs stockées en base, les deux doivent rester identiques.
 */
function velo_verifier_slugs(array $slugs): void
{
  foreach ($slugs as $champ => $valeur) {
    if (!preg_match('/^[a-z0-9-]{0,255}$/', (string) $valeur)) {
      die("Champ $champ invalide : seuls les caractères a-z, 0-9 et le tiret sont acceptés.");
    }
  }
}

/**
 * Nom du fichier GPX d'un itinéraire, tel que reconstruit partout ailleurs
 * (falaise.php, js/components/utils/paths.js).
 */
function velo_gpx_nom_fichier(int $velo_id, string $velo_depart, string $velo_arrivee, string $velo_varianteformate): string
{
  return "{$velo_id}_{$velo_depart}_{$velo_arrivee}_{$velo_varianteformate}.gpx";
}

/** Dossier de données des traces GPX, au sens de lib/paths.php. */
const VELO_GPX_DIR = 'gpx';

/** Dossier de données des versions archivées. */
const VELO_GPX_ARCHIVE_DIR = 'gpx-historique';

/** Chemin de données du fichier GPX, relatif à la racine (cf. lib/paths.php). */
function velo_gpx_rel(int $velo_id, string $velo_depart, string $velo_arrivee, string $velo_varianteformate): string
{
  return VELO_GPX_DIR . '/' . velo_gpx_nom_fichier($velo_id, $velo_depart, $velo_arrivee, $velo_varianteformate);
}

/** Chemin absolu sur disque du fichier GPX. */
function velo_gpx_chemin(int $velo_id, string $velo_depart, string $velo_arrivee, string $velo_varianteformate): string
{
  return vg_data_path(velo_gpx_rel($velo_id, $velo_depart, $velo_arrivee, $velo_varianteformate));
}

/** URL publique du fichier GPX. */
function velo_gpx_url(int $velo_id, string $velo_depart, string $velo_arrivee, string $velo_varianteformate): string
{
  return vg_data_url(velo_gpx_rel($velo_id, $velo_depart, $velo_arrivee, $velo_varianteformate));
}

/**
 * Charge et nettoie le GPX téléversé dans $_FILES[$champ] (mêmes contrôles pour TOUS les formulaires
 * d'accès : ajout, édition, falaise, arrêt, éditeur de falaise).
 *
 * Retourne le DOMDocument nettoyé (waypoints <wpt> retirés, trace <trk> et routes <rte> conservées), ou
 * null si aucun fichier n'a été envoyé. Lève une InvalidArgumentException (message lisible) si le
 * fichier est trop gros, n'est pas un XML valide ou n'a pas <gpx> pour racine.
 */
function velo_lire_gpx_upload(string $champ = 'gpx_file'): ?DOMDocument
{
  if (empty($_FILES[$champ]['tmp_name']) || !is_uploaded_file($_FILES[$champ]['tmp_name'])) {
    return null;
  }
  // La plus grosse trace en base fait 553 Ko : ce plafond borne la mémoire du
  // parseur XML sans gêner une contribution légitime.
  if ($_FILES[$champ]['size'] > GPX_TAILLE_MAX) {
    throw new InvalidArgumentException("Le fichier GPX dépasse la taille maximale de " . (GPX_TAILLE_MAX / 1024 / 1024) . " Mo.");
  }

  $dom = new DOMDocument();
  // LIBXML_NONET : interdit tout accès réseau pendant l'analyse.
  if (!@$dom->loadXML(file_get_contents($_FILES[$champ]['tmp_name']), LIBXML_NONET)) {
    throw new InvalidArgumentException("Le fichier GPX n'est pas un XML valide.");
  }
  $has_gpx_root = ($dom->getElementsByTagName('gpx')->length > 0)
    && ($dom->getElementsByTagName('gpx')->item(0)->getNodePath() === '/*');
  if (!$has_gpx_root) {
    throw new InvalidArgumentException("Le fichier GPX n'est pas valide.");
  }
  // Nettoyage : retirer les waypoints <wpt> (marqueurs début/fin, points isolés).
  $wpts = $dom->getElementsByTagName('wpt');
  for ($i = $wpts->length - 1; $i >= 0; $i--) {
    $wpt = $wpts->item($i);
    $wpt->parentNode->removeChild($wpt);
  }
  return $dom;
}

/**
 * Comme velo_lire_gpx_upload(), mais termine la requête par die() avec le message : pour les endpoints
 * qui répondent à une soumission de formulaire classique (add_velo, edit_velo, add_falaise).
 */
function velo_charger_gpx_upload(string $champ = 'gpx_file'): ?DOMDocument
{
  try {
    return velo_lire_gpx_upload($champ);
  } catch (InvalidArgumentException $e) {
    die($e->getMessage());
  }
}

/**
 * Types d'accès de la table `acces_falaise` (Lot 8quater) : `velo` ou `pied`.
 */
const ACCES_TYPES = ['velo', 'pied'];

/** Distance (km) jusqu'à laquelle un accès est considéré comme faisable à pied. */
const ACCES_A_PIED_KM_MAX = 5;

/**
 * « Faisable à pied » : un accès de type `pied`, ou n'importe quel accès d'au
 * plus 5 km. Remplace l'ancienne colonne `velo_apiedpossible`. Équivalent SQL :
 * acces_a_pied_sql() — les lecteurs l'exposent au front sous l'ancienne clé JSON
 * `velo_apiedpossible`, calculée une seule fois ici (pas de miroir TS).
 */
function acces_a_pied_possible(string $type, $km): bool
{
  return $type === 'pied' || ($km !== null && $km !== '' && (float) $km <= ACCES_A_PIED_KM_MAX);
}

/** Condition SQL équivalente à acces_a_pied_possible(), pour la table (ou l'alias) donnée. */
function acces_a_pied_sql(string $alias = 'acces_falaise'): string
{
  // COALESCE : km inconnu => 0 (pas « à pied »), jamais NULL, y compris sous un NOT.
  return "({$alias}.type = 'pied' OR COALESCE({$alias}.km <= " . ACCES_A_PIED_KM_MAX . ", 0))";
}

/**
 * Crée l'accès `pied` (variante `''`) entre un arrêt et une falaise, ou complète
 * celui qui existe : seuls les champs encore VIDES sont renseignés, rien de ce qui
 * l'est déjà n'est écrasé (formulaires publics). Un accès à pied peut porter une
 * description, km / D+ / D- (tous facultatifs) et une trace GPX.
 *
 * $arret : ['id','nom','slug'] (cf. velo_trouver_arret), $falaise : ['falaise_id',
 * 'falaise_nom','falaise_nomformate'] (cf. velo_trouver_falaise).
 * $d : description (string), km (float|null), dplus (int|null), dmoins (int|null), variante (nom libre : plusieurs
 * accès sans vélo possibles entre un même arrêt et une même falaise, un par variante). Clé `variante` ABSENTE
 * (liaisons rapides : clic sur un arrêt, formulaires de falaise / d'arrêt) = « n'importe laquelle » : si un accès
 * sans vélo existe déjà pour ce couple (ex. migré avec un nom de variante), c'est lui qui est complété, aucun
 * doublon n'est créé. Clé présente, même vide (formulaire d'accès) = cette variante exactement.
 * $dom : GPX déjà nettoyé (velo_charger_gpx_upload) ou null. L'appelant a fait
 * vg_data_prepare(VELO_GPX_DIR) avant toute mutation s'il en fournit un.
 *
 * @return array{acces_id:int, cree:bool, remplis:string[], gpx:string} gpx :
 *   'ecrit' | 'echec' (écriture impossible) | 'present' (déjà là, conservé) | 'absent'
 */
function acces_pied_enregistrer(mysqli $mysqli, array $arret, array $falaise, array $d, ?DOMDocument $dom, string $contrib, string $contrib_mail): array
{
  $arret_id = (int) $arret['id'];
  $falaise_id = (int) $falaise['falaise_id'];
  $description = trim((string) ($d['description'] ?? ''));
  $km = $d['km'] ?? null;
  $dplus = $d['dplus'] ?? null;
  $dmoins = $d['dmoins'] ?? null;
  $variante = trim((string) ($d['variante'] ?? ''));
  $variante_formate = acces_formater_variante($variante);
  $variante_precisee = isset($d['variante']); // false : on complète n'importe quel accès du couple

  $stmt = $mysqli->prepare(
    "SELECT acces_id, description, km, dplus, dmoins, depart_slug, arrivee_slug, variante_formate
     FROM acces_falaise WHERE type = 'pied' AND arret_id = ? AND falaise_id = ?
       AND (? = 0 OR variante = ?)
     ORDER BY (variante = ?) DESC, acces_id LIMIT 1"
  );
  $precisee = $variante_precisee ? 1 : 0;
  $stmt->bind_param("iiiss", $arret_id, $falaise_id, $precisee, $variante, $variante);
  $stmt->execute();
  $existant = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $remplis = [];
  if ($existant === null) {
    $depart_slug = $arret['slug'];
    $arrivee_slug = $falaise['falaise_nomformate'];
    $stmt = $mysqli->prepare(
      "INSERT INTO acces_falaise (type, arret_id, falaise_id, depart_slug, arrivee_slug, description, km, dplus, dmoins, public, variante, variante_formate, contrib, contrib_mail)
       VALUES ('pied', ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iisssdiissss", $arret_id, $falaise_id, $depart_slug, $arrivee_slug, $description, $km, $dplus, $dmoins, $variante, $variante_formate, $contrib, $contrib_mail);
    $stmt->execute();
    $acces_id = (int) $stmt->insert_id;
    $stmt->close();
    $cree = true;
    foreach (['description' => $description !== '', 'km' => $km !== null, 'dplus' => $dplus !== null, 'dmoins' => $dmoins !== null] as $champ => $ok) {
      if ($ok) {
        $remplis[] = $champ;
      }
    }
    $depart_slug_gpx = $depart_slug;
    $arrivee_slug_gpx = $arrivee_slug;
  } else {
    $acces_id = (int) $existant['acces_id'];
    $cree = false;
    $sets = [];
    $types = '';
    $vals = [];
    if ($description !== '' && trim((string) $existant['description']) === '') {
      $sets[] = 'description = ?'; $types .= 's'; $vals[] = $description; $remplis[] = 'description';
    }
    foreach (['km' => [$km, 'd'], 'dplus' => [$dplus, 'i'], 'dmoins' => [$dmoins, 'i']] as $champ => [$val, $t]) {
      if ($val !== null && $existant[$champ] === null) {
        $sets[] = "$champ = ?"; $types .= $t; $vals[] = $val; $remplis[] = $champ;
      }
    }
    if ($sets) {
      $types .= 'i';
      $vals[] = $acces_id;
      $stmt = $mysqli->prepare("UPDATE acces_falaise SET " . implode(', ', $sets) . ", date_modification = CURRENT_TIMESTAMP WHERE acces_id = ?");
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();
      $stmt->close();
    }
    $depart_slug_gpx = $existant['depart_slug'];
    $arrivee_slug_gpx = $existant['arrivee_slug'];
    $variante_formate = (string) $existant['variante_formate'];
  }

  $gpx = 'absent';
  if ($dom !== null) {
    $rel = velo_gpx_rel($acces_id, $depart_slug_gpx, $arrivee_slug_gpx, $variante_formate);
    if (!$cree && vg_data_exists($rel)) {
      $gpx = 'present'; // jamais d'écrasement par un formulaire public
    } elseif ($dom->save(vg_data_path($rel)) === false) {
      error_log("acces_pied_enregistrer: échec de l'écriture du GPX pour acces_id=$acces_id");
      $gpx = 'echec';
    } else {
      $gpx = 'ecrit';
      $remplis[] = 'gpx';
    }
  }
  return ['acces_id' => $acces_id, 'cree' => $cree, 'remplis' => $remplis, 'gpx' => $gpx];
}

/**
 * Lie un arrêt à une falaise par un accès `pied`. Retourne true si le lien a été
 * créé, false s'il existait déjà (éventuellement complété avec $d / $dom, cf.
 * acces_pied_enregistrer) ou si l'arrêt ou la falaise n'existe pas.
 */
function acces_pied_lier(mysqli $mysqli, int $arret_id, int $falaise_id, string $contrib, string $contrib_mail, array $d = [], ?DOMDocument $dom = null): bool
{
  $arret = velo_trouver_arret($mysqli, $arret_id);
  $falaise = velo_trouver_falaise($mysqli, $falaise_id);
  if ($arret === null || $falaise === null) {
    return false;
  }
  return acces_pied_enregistrer($mysqli, $arret, $falaise, $d, $dom, $contrib, $contrib_mail)['cree'];
}

/**
 * Lit description / km / D+ / D- d'un accès à pied dans un tableau (champs d'un
 * formulaire ou objet JSON). Valeurs absentes, vides, négatives ou non
 * numériques : ignorées (null), comme velo_lire_indicateurs().
 *
 * `variante` n'est retournée que si elle est NON VIDE (liaisons rapides : champ vide = « n'importe laquelle »,
 * cf. acces_pied_enregistrer).
 *
 * @return array{description:string, km:?float, dplus:?int, dmoins:?int, variante?:string}
 */
function acces_lire_details(array $src): array
{
  $nombre = fn($v) => (is_numeric($v) && $v >= 0) ? $v + 0 : null;
  $km = $nombre($src['km'] ?? null);
  $dplus = $nombre($src['dplus'] ?? null);
  $dmoins = $nombre($src['dmoins'] ?? null);
  $details = [
    'description' => trim((string) ($src['description'] ?? '')),
    'km' => $km === null ? null : (float) $km,
    'dplus' => $dplus === null ? null : (int) $dplus,
    'dmoins' => $dmoins === null ? null : (int) $dmoins,
  ];
  $variante = trim((string) ($src['variante'] ?? ''));
  if ($variante !== '') {
    $details['variante'] = mb_substr($variante, 0, 100);
  }
  return $details;
}

/**
 * Slug d'un nom de variante, comme `formatVariante()` du formulaire d'ajout d'accès : minuscules,
 * sans accents, [a-z0-9-] seulement, tirets simples. Sert de clé de nom de fichier GPX.
 */
function acces_formater_variante(string $variante): string
{
  $s = mb_strtolower(trim($variante));
  if (class_exists('Normalizer')) {
    $s = preg_replace('/\p{Mn}/u', '', Normalizer::normalize($s, Normalizer::FORM_D));
  } else {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  }
  $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
  $s = preg_replace('/\s+/', '-', $s);
  $s = preg_replace('/-+/', '-', $s);
  return substr(trim($s, '-'), 0, 100);
}

/**
 * Crée un accès `velo` (itinéraire à vélo) vers une falaise qui vient d'être créée : km, D+, D- et
 * trace GPX obligatoires (déjà validés par l'appelant, qui a fait vg_data_prepare(VELO_GPX_DIR)).
 * $d : km (float), dplus (int), dmoins (int), description, variante (nom libre, facultatif).
 *
 * @return array{acces_id:int, gpx:string} gpx : 'ecrit' | 'echec' (la ligne existe, sans trace)
 */
function acces_velo_creer(mysqli $mysqli, array $arret, int $falaise_id, string $falaise_nomformate, array $d, DOMDocument $dom, string $contrib, string $contrib_mail, int $public): array
{
  $arret_id = (int) $arret['id'];
  $depart_slug = $arret['slug'];
  $km = (float) $d['km'];
  $dplus = (int) $d['dplus'];
  $dmoins = (int) $d['dmoins'];
  $description = trim((string) ($d['description'] ?? ''));
  $variante = trim((string) ($d['variante'] ?? ''));
  $variante_formate = acces_formater_variante($variante);
  $openrunner = '';

  $stmt = $mysqli->prepare(
    "INSERT INTO acces_falaise (type, arret_id, falaise_id, depart_slug, arrivee_slug, km, dplus, dmoins, description, public, variante, variante_formate, lien_openrunner, contrib, contrib_mail)
     VALUES ('velo', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );
  $stmt->bind_param("iissdiisisssss", $arret_id, $falaise_id, $depart_slug, $falaise_nomformate, $km, $dplus, $dmoins, $description, $public, $variante, $variante_formate, $openrunner, $contrib, $contrib_mail);
  $stmt->execute();
  $acces_id = (int) $stmt->insert_id;
  $stmt->close();

  $ok = $acces_id > 0 && $dom->save(velo_gpx_chemin($acces_id, $depart_slug, $falaise_nomformate, $variante_formate)) !== false;
  if (!$ok) {
    error_log("acces_velo_creer: échec de l'écriture du GPX pour acces_id=$acces_id");
  }
  return ['acces_id' => $acces_id, 'gpx' => $ok ? 'ecrit' : 'echec'];
}

/**
 * Supprime les accès `pied` entre un arrêt et une falaise, ou le seul accès `$acces_id` si on le précise (les accès
 * vélo ne sont jamais touchés). Retourne le nombre de lignes supprimées.
 */
function acces_pied_delier(mysqli $mysqli, int $arret_id, int $falaise_id, int $acces_id = 0): int
{
  $stmt = $mysqli->prepare("DELETE FROM acces_falaise WHERE type = 'pied' AND arret_id = ? AND falaise_id = ? AND (? = 0 OR acces_id = ?)");
  $stmt->bind_param("iiii", $arret_id, $falaise_id, $acces_id, $acces_id);
  $stmt->execute();
  $n = $stmt->affected_rows;
  $stmt->close();
  return $n;
}

/** Ids des falaises reliées à un arrêt par un accès `pied`. */
function acces_pied_falaise_ids(mysqli $mysqli, int $arret_id): array
{
  $stmt = $mysqli->prepare("SELECT falaise_id FROM acces_falaise WHERE type = 'pied' AND arret_id = ? ORDER BY falaise_id");
  $stmt->bind_param("i", $arret_id);
  $stmt->execute();
  $ids = array_map(fn($r) => (int) $r['falaise_id'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
  $stmt->close();
  return $ids;
}

/** Type d'accès lu dans une saisie ; `velo` par défaut, 400 si inconnu. */
function acces_lire_type($valeur): string
{
  $type = $valeur === null || $valeur === '' ? 'velo' : (string) $valeur;
  if (!in_array($type, ACCES_TYPES, true)) {
    http_response_code(400);
    die("Type d'accès invalide : $type");
  }
  return $type;
}

/**
 * Archive le GPX courant avant remplacement, dans gpx-historique/
 * (même principe que barres-historique pour les GeoJSON de falaise).
 *
 * Prend et rend un chemin de données relatif (cf. lib/paths.php). Retourne null
 * s'il n'y avait rien à archiver. Le dossier est supposé prêt : l'appelant a
 * appelé vg_data_prepare(VELO_GPX_ARCHIVE_DIR) avant toute mutation.
 */
function velo_archiver_gpx(string $rel_actuel): ?string
{
  if (!vg_data_exists($rel_actuel)) {
    return null;
  }
  $rel_archive = VELO_GPX_ARCHIVE_DIR . '/' . basename($rel_actuel, '.gpx') . '-' . date('Y-m-d-H\Hi') . '.gpx';
  return copy(vg_data_path($rel_actuel), vg_data_path($rel_archive)) ? $rel_archive : null;
}

/** Lien de validation d'un itinéraire, pour les mails aux admins. */
function velo_lien_validation(array $config, int $velo_id): string
{
  return "https://velogrimpe.fr/api/private/accept_velo.php?admin=" . urlencode($config["admin_token"]) . "&velo_id=$velo_id";
}

/** Lien d'édition admin d'un itinéraire, pour les mails aux admins. */
function velo_lien_edition_admin(array $config, array $velo): string
{
  return "https://velogrimpe.fr/ajout/edit_velo.php?" . http_build_query([
    'admin' => $config["admin_token"],
    'falaise_id' => $velo['falaise_id'],
    'gare_id' => $velo['gare_id'],
    'velo_id' => $velo['velo_id'],
  ]);
}


/** Arrêt de départ d'un accès (toutes modalités confondues), ou null s'il n'existe pas / est supprimé. */
function velo_trouver_arret(mysqli $mysqli, int $arret_id): ?array
{
  $stmt = $mysqli->prepare("SELECT id, nom, slug FROM arrets WHERE id = ? AND deleted = 0 AND parent_id IS NULL");
  $stmt->bind_param("i", $arret_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}

/** Falaise d'arrivée d'un accès, ou null si elle n'existe pas. */
function velo_trouver_falaise(mysqli $mysqli, int $falaise_id): ?array
{
  $stmt = $mysqli->prepare("SELECT falaise_id, falaise_nom, falaise_nomformate FROM falaises WHERE falaise_id = ?");
  $stmt->bind_param("i", $falaise_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}
