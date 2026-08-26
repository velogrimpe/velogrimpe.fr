<?php
/**
 * Fonctions partagées entre l'ajout (api/add_velo.php) et l'édition
 * (api/edit_velo.php) d'un itinéraire vélo (cf. docs/plans/DECISIONS.md D009).
 *
 * Toutes les fonctions de validation terminent la requête par die() avec un
 * message lisible, comme le faisait historiquement add_velo.php : ces endpoints
 * répondent à une soumission de formulaire classique (navigation), pas en JSON.
 */

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

/** Chemin absolu sur disque du fichier GPX. */
function velo_gpx_chemin(int $velo_id, string $velo_depart, string $velo_arrivee, string $velo_varianteformate): string
{
  return $_SERVER['DOCUMENT_ROOT'] . "/bdd/gpx/" . velo_gpx_nom_fichier($velo_id, $velo_depart, $velo_arrivee, $velo_varianteformate);
}

/** URL publique du fichier GPX. */
function velo_gpx_url(int $velo_id, string $velo_depart, string $velo_arrivee, string $velo_varianteformate): string
{
  return "/bdd/gpx/" . velo_gpx_nom_fichier($velo_id, $velo_depart, $velo_arrivee, $velo_varianteformate);
}

/**
 * Charge et nettoie le GPX téléversé dans $_FILES[$champ].
 *
 * Retourne le DOMDocument nettoyé (waypoints <wpt> retirés, trace <trk> et
 * routes <rte> conservées), ou null si aucun fichier n'a été envoyé.
 * Termine la requête si le fichier est trop gros, n'est pas un XML valide ou
 * n'a pas <gpx> pour racine.
 */
function velo_charger_gpx_upload(string $champ = 'gpx_file'): ?DOMDocument
{
  if (empty($_FILES[$champ]['tmp_name']) || !is_uploaded_file($_FILES[$champ]['tmp_name'])) {
    return null;
  }
  // La plus grosse trace en base fait 553 Ko : ce plafond borne la mémoire du
  // parseur XML sans gêner une contribution légitime.
  if ($_FILES[$champ]['size'] > GPX_TAILLE_MAX) {
    die("Le fichier GPX dépasse la taille maximale de " . (GPX_TAILLE_MAX / 1024 / 1024) . " Mo.");
  }

  $dom = new DOMDocument();
  // LIBXML_NONET : interdit tout accès réseau pendant l'analyse.
  if (!@$dom->loadXML(file_get_contents($_FILES[$champ]['tmp_name']), LIBXML_NONET)) {
    die("Le fichier GPX n'est pas un XML valide.");
  }
  $has_gpx_root = ($dom->getElementsByTagName('gpx')->length > 0)
    && ($dom->getElementsByTagName('gpx')->item(0)->getNodePath() === '/*');
  if (!$has_gpx_root) {
    die("Le fichier GPX n'est pas valide.");
  }
  // Nettoyage : retirer les waypoints <wpt> (marqueurs début/fin, points isolés).
  $wpts = $dom->getElementsByTagName('wpt');
  for ($i = $wpts->length - 1; $i >= 0; $i--) {
    $wpt = $wpts->item($i);
    $wpt->parentNode->removeChild($wpt);
  }
  return $dom;
}

/** Chaîne `velo_contrib` telle que stockée en base depuis l'origine du site. */
function velo_contrib_string(string $nom_prenom, string $email): string
{
  return trim("'" . $nom_prenom . "','" . $email . "'");
}

/**
 * Archive le GPX courant avant remplacement, dans bdd/gpx-historique/
 * (même principe que bdd/barres-historique pour les GeoJSON de falaise).
 * Retourne le chemin de l'archive, ou null s'il n'y avait rien à archiver.
 */
function velo_archiver_gpx(string $chemin_actuel): ?string
{
  if (!file_exists($chemin_actuel)) {
    return null;
  }
  $dir = $_SERVER['DOCUMENT_ROOT'] . "/bdd/gpx-historique";
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
  $archive = $dir . "/" . basename($chemin_actuel, ".gpx") . "-" . date('Y-m-d-H\Hi') . ".gpx";
  return copy($chemin_actuel, $archive) ? $archive : null;
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
