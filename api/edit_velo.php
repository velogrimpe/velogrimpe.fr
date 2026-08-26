<?php
/**
 * POST /api/edit_velo.php (multipart/form-data)
 *
 * Modification d'un itinéraire vélo existant. Trois cas (cf. DECISIONS D010) :
 *  - admin (token) : tout est appliqué (km, D+, D-, description, Openrunner,
 *    GPX) et l'itinéraire passe en velo_public = 1 (validé) ;
 *  - contributeur, itinéraire non validé (velo_public ≠ 1) : km, D+, D-,
 *    description et GPX sont appliqués ; mail aux admins avec lien de validation ;
 *  - contributeur, itinéraire validé (velo_public = 1) : RIEN n'est écrit. La
 *    description proposée et le GPX (assaini) sont envoyés aux admins en
 *    suggestion ; km / D+ / D- sont ignorés (désactivés côté formulaire).
 *
 * Les champs qui composent le nom du fichier GPX ne sont pas modifiables (D008).
 * Formulaire public : on durcit les données, pas l'accès (D005).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die("Méthode non autorisée.");
}

$admin = hash_equals((string) $config["admin_token"], trim($_POST['admin'] ?? ''));
$velo_id = (isset($_POST['velo_id']) && $_POST['velo_id'] !== '') ? intval($_POST['velo_id']) : null;
[$velo_km, $velo_dplus, $velo_dmoins] = velo_lire_indicateurs($_POST);
$velo_descr = trim($_POST['velo_descr'] ?? '');
$velo_openrunner = trim($_POST['velo_openrunner'] ?? '');
$nom_prenom = trim($_POST['nom_prenom'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

velo_verifier_obligatoires([
  'velo_id' => $velo_id,
  'nom_prenom' => $nom_prenom,
  'email' => $email,
]);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  die("Adresse mail invalide.");
}

// Itinéraire existant
$stmt = $mysqli->prepare("SELECT v.velo_id, v.gare_id, v.falaise_id, v.velo_depart, v.velo_arrivee, v.velo_varianteformate,
    v.velo_variante, v.velo_km, v.velo_dplus, v.velo_dmoins, v.velo_descr, v.velo_openrunner, v.velo_public,
    g.gare_nom, f.falaise_nom
  FROM velo v
  LEFT JOIN gares g ON g.gare_id = v.gare_id
  LEFT JOIN falaises f ON f.falaise_id = v.falaise_id
  WHERE v.velo_id = ?");
$stmt->bind_param("i", $velo_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$old) {
  http_response_code(404);
  die("Itinéraire introuvable.");
}
$est_valide = intval($old['velo_public']) === 1;
$mode = $admin ? 'admin' : ($est_valide ? 'suggestion' : 'contrib');

// Les slugs viennent de la base, mais le fichier est écrit sous ce nom : on les
// valide comme à l'ajout, pour ne jamais écrire hors de /bdd/gpx/.
velo_verifier_slugs([
  'velo_depart' => $old['velo_depart'],
  'velo_arrivee' => $old['velo_arrivee'],
  'velo_varianteformate' => $old['velo_varianteformate'],
]);

// Les indicateurs ne sont obligatoires que lorsqu'ils vont être écrits.
if ($mode !== 'suggestion') {
  velo_verifier_obligatoires([
    'velo_km' => $velo_km,
    'velo_dplus' => $velo_dplus,
    'velo_dmoins' => $velo_dmoins,
  ]);
}
// Openrunner : admin uniquement (la valeur en base est conservée sinon).
if (!$admin) {
  $velo_openrunner = (string) ($old['velo_openrunner'] ?? '');
}

// GPX optionnel : validé et nettoyé AVANT toute écriture ou envoi. Un GPX
// invalide interrompt la requête sans rien modifier.
$dom = velo_charger_gpx_upload('gpx_file');

$gpx_nom = velo_gpx_nom_fichier((int) $old['velo_id'], $old['velo_depart'], $old['velo_arrivee'], (string) $old['velo_varianteformate']);
$gpx_chemin = velo_gpx_chemin((int) $old['velo_id'], $old['velo_depart'], $old['velo_arrivee'], (string) $old['velo_varianteformate']);
$lien_falaise = "https://velogrimpe.fr/falaise.php?falaise_id={$old['falaise_id']}";
$titre = htmlspecialchars(($old['gare_nom'] ?? $old['velo_depart']) . " → " . ($old['falaise_nom'] ?? $old['velo_arrivee'])
  . ($old['velo_variante'] !== '' ? " (" . $old['velo_variante'] . ")" : ''));

// ---------------------------------------------------------------------------
// Cas « suggestion » : itinéraire validé, contributeur. Rien n'est écrit.
// ---------------------------------------------------------------------------
if ($mode === 'suggestion') {
  $descr_changee = $velo_descr !== trim((string) $old['velo_descr']);
  if (!$descr_changee && $dom === null) {
    die("Aucune modification à transmettre : la description est inchangée et aucun nouveau GPX n'a été fourni.");
  }

  // Trace du dépôt de la suggestion (sans modification de la ligne velo).
  logChanges($nom_prenom, $email, 'suggestion', 'velo', $velo_id, $old['falaise_id'], [
    "velo_descr" => $velo_descr,
    "gpx_file" => $dom !== null ? 'nouveau GPX proposé (envoyé par mail)' : 'inchangé',
  ], [
    "velo_descr" => $old['velo_descr'],
    "gpx_file" => 'inchangé',
  ]);

  require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
  $html = "<html><body>";
  $html .= "<h1>Suggestion de modification sur l'itinéraire validé $titre</h1>";
  $html .= "<p>Proposée par " . htmlspecialchars($nom_prenom) . " — <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></p>";
  $html .= "<p><a href='$lien_falaise'>Voir la falaise</a></p>";
  if ($message) {
    $html .= "<p><b>Message :</b><br/>" . nl2br(htmlspecialchars($message)) . "</p>";
  }
  $html .= "<p>Cet itinéraire est <b>validé</b> : la suggestion n'a <b>pas</b> été appliquée en base.</p>";
  if ($descr_changee) {
    $html .= "<h2>Description</h2>";
    $html .= "<p><b>Actuelle :</b><br/>" . nl2br(htmlspecialchars((string) $old['velo_descr'])) . "</p>";
    $html .= "<p><b>Proposée :</b><br/>" . nl2br(htmlspecialchars($velo_descr)) . "</p>";
  }
  if ($dom !== null) {
    $html .= "<h2>Trace GPX</h2><p>Nouvelle trace proposée en pièce jointe (<code>$gpx_nom</code>), nettoyée des waypoints "
      . "et validée comme XML GPX. Trace actuelle : <a href='https://velogrimpe.fr" . velo_gpx_url((int) $old['velo_id'], $old['velo_depart'], $old['velo_arrivee'], (string) $old['velo_varianteformate']) . "'>télécharger</a>.</p>";
  }
  $html .= "<h2>Actions</h2>";
  $html .= "<p><a href='" . velo_lien_edition_admin($config, $old) . "'>Ouvrir l'itinéraire en édition (admin)</a> pour appliquer la suggestion.</p>";
  $html .= "</body></html>";

  $data = [
    'to' => $config["contact_mail"],
    'subject' => "💡 Suggestion sur l'itinéraire validé {$old['velo_depart']} ⇢ {$old['velo_arrivee']} par $nom_prenom",
    'html' => $html,
    'h:Reply-To' => $email,
  ];
  $tmp = null;
  if ($dom !== null) {
    // La PJ est le XML ré-sérialisé depuis le DOM nettoyé, jamais le fichier
    // reçu tel quel : seul un vrai GPX (racine <gpx>, sans <wpt>) part en PJ.
    $tmp = tempnam(sys_get_temp_dir(), 'vg-gpx-');
    $dom->save($tmp);
    $data['attachment'] = new CURLFile($tmp, 'application/gpx+xml', $gpx_nom);
  }
  sendMail($data);
  if ($tmp) {
    @unlink($tmp);
  }

  header("Location: /ajout/confirmation_velo.php?" . http_build_query([
    'falaise_id' => $old['falaise_id'],
    'gare_id' => $old['gare_id'],
    'velo_id' => $velo_id,
    'type' => 'suggestion',
  ]));
  exit;
}

// ---------------------------------------------------------------------------
// Cas « admin » et « contrib » : écriture en base.
// ---------------------------------------------------------------------------
$velo_public = $admin ? 1 : intval($old['velo_public']);

$stmt = $mysqli->prepare("UPDATE velo
  SET velo_km = ?, velo_dplus = ?, velo_dmoins = ?, velo_descr = ?, velo_openrunner = ?, velo_public = ?,
      date_modification = CURRENT_TIMESTAMP
  WHERE velo_id = ?");
if (!$stmt) {
  die("Erreur lors de la mise à jour : " . $mysqli->error);
}
$stmt->bind_param("diissii", $velo_km, $velo_dplus, $velo_dmoins, $velo_descr, $velo_openrunner, $velo_public, $velo_id);
$stmt->execute();
$stmt->close();

$gpx_remplace = false;
if ($dom !== null) {
  velo_archiver_gpx($gpx_chemin);
  if ($dom->save($gpx_chemin) === false) {
    error_log("edit_velo: échec de l'écriture du GPX nettoyé pour velo_id=$velo_id");
  } else {
    $gpx_remplace = true;
  }
}

// Journal des modifications. velo_km est un FLOAT : on journalise sa
// représentation courte ("3.8") plutôt que le flottant brut.
logChanges($nom_prenom, $email, 'update', 'velo', $velo_id, $old['falaise_id'], [
  "velo_km" => (string) $velo_km,
  "velo_dplus" => $velo_dplus,
  "velo_dmoins" => $velo_dmoins,
  "velo_descr" => $velo_descr,
  "velo_openrunner" => $velo_openrunner,
  "velo_public" => $velo_public,
  "gpx_file" => $gpx_remplace ? 'remplacé' : 'inchangé',
], [
  "velo_km" => (string) (float) $old['velo_km'],
  "velo_dplus" => $old['velo_dplus'],
  "velo_dmoins" => $old['velo_dmoins'],
  "velo_descr" => $old['velo_descr'],
  "velo_openrunner" => (string) ($old['velo_openrunner'] ?? ''),
  "velo_public" => intval($old['velo_public']),
  "gpx_file" => 'inchangé',
]);

// Notification : admin_mail en mode admin, contact_mail sinon (même schéma que
// add_falaise.php / add_bus.php). Le lien de validation n'a de sens que si
// l'itinéraire n'est pas déjà validé par cette écriture.
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
$statut = $velo_public === 1 ? 'validé' : 'non validé';
$html = "<html><body>";
$html .= "<h1>L'itinéraire $titre a été modifié par " . htmlspecialchars($nom_prenom) . ($admin ? " (admin)" : "") . "</h1>";
$html .= "<p>email : <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></p>";
$html .= "<p><a href='$lien_falaise'>Voir la falaise</a></p>";
if ($message) {
  $html .= "<p><b>Message :</b><br/>" . nl2br(htmlspecialchars($message)) . "</p>";
}
$html .= "<p>Modifications (velo_id $velo_id, itinéraire <b>$statut</b>) :</p><ul>";
$html .= "<li><b>Distance</b>: {$old['velo_km']} km → $velo_km km</li>";
$html .= "<li><b>D+</b>: {$old['velo_dplus']} m → $velo_dplus m</li>";
$html .= "<li><b>D-</b>: {$old['velo_dmoins']} m → $velo_dmoins m</li>";
$html .= "<li><b>Description</b>: " . nl2br(htmlspecialchars($velo_descr)) . "</li>";
if ($admin) {
  $html .= "<li><b>Openrunner</b>: " . htmlspecialchars($velo_openrunner) . "</li>";
}
$html .= "<li><b>Trace GPX</b>: " . ($gpx_remplace ? 'remplacée (ancienne version archivée dans bdd/gpx-historique)' : 'inchangée') . "</li>";
$html .= "</ul>";
$html .= "<h2>Actions</h2>";
if (!$admin) {
  $html .= "<p>Pour valider cet itinéraire, cliquez sur le lien suivant :</p>";
  $html .= "<p><a href='" . velo_lien_validation($config, (int) $velo_id) . "'>Valider l'itinéraire</a></p>";
}
$html .= "<p>Pour le vérifier / modifier :</p>";
$html .= "<p><a href='" . velo_lien_edition_admin($config, $old) . "'>Modifier l'itinéraire</a></p>";
$html .= "</body></html>";

sendMail([
  'to' => $admin ? $config["admin_mail"] : $config["contact_mail"],
  'subject' => "🚲 Itinéraire {$old['velo_depart']} ⇢ {$old['velo_arrivee']} modifié par $nom_prenom",
  'html' => $html,
  'h:Reply-To' => $email,
]);

header("Location: /ajout/confirmation_velo.php?" . http_build_query([
  'falaise_id' => $old['falaise_id'],
  'gare_id' => $old['gare_id'],
  'velo_id' => $velo_id,
  'type' => 'update',
  'admin' => $admin ? $config["admin_token"] : '',
]));
exit;
