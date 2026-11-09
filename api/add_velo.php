<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
  // `gare_id` reste accepté : anciens liens et formulaires en cache (Lot 5).
  $arret_id = $_POST['arret_id'] ?? $_POST['gare_id'] ?? null;
  $falaise_id = $_POST['falaise_id'] ?? null;
  $nom_prenom = trim($_POST['nom_prenom'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');
  // `mode` est l'ancien nom du paramètre `type` (Lot 5) : les deux sont acceptés.
  $mode = $_POST['type'] ?? $_POST['mode'] ?? 'velo';

  if (!in_array($mode, ACCES_TYPES, true)) {
    die("Mode d'accès inconnu : " . htmlspecialchars($mode));
  }
  velo_verifier_obligatoires(['arret_id' => $arret_id, 'falaise_id' => $falaise_id]);

  // Discrimination précoce : le reste de ce fichier ne sert que l'accès vélo.
  $arret = velo_trouver_arret($mysqli, (int) $arret_id);
  if ($arret === null) {
    die("Arrêt de départ introuvable : il n'existe pas ou a été supprimé.");
  }
  $falaise = velo_trouver_falaise($mysqli, (int) $falaise_id);
  if ($falaise === null) {
    die("Falaise introuvable.");
  }
  $arret_id = (int) $arret_id;
  $falaise_id = (int) $falaise_id;

  if ($mode === 'pied') {
    $description = trim($_POST['description'] ?? '');
    // Variante : facultative, plusieurs accès sans vélo possibles entre un même arrêt et une même falaise
    $variante = trim($_POST['velo_variante'] ?? '');
    // km / D+ / D- et GPX restent facultatifs sur un accès à pied.
    [$km, $dplus, $dmoins] = velo_lire_indicateurs($_POST);
    $dom = velo_charger_gpx_upload('gpx_file');
    if ($dom !== null) {
      try {
        vg_data_prepare(VELO_GPX_DIR);
      } catch (VgDataException $e) {
        error_log('[add_velo/pied] ' . $e->getMessage());
        die("Le dossier des traces GPX est indisponible, l'accès n'a pas été enregistré. Merci de réessayer plus tard.");
      }
    }

    // Un accès à pied existe déjà ? On ne le complète que s'il y a quelque chose à ajouter,
    // sans jamais écraser ce qui est renseigné (formulaire public).
    $stmt = $mysqli->prepare("SELECT description, km, dplus, dmoins, acces_id, depart_slug, arrivee_slug, variante_formate FROM acces_falaise WHERE type = 'pied' AND variante = ? AND arret_id = ? AND falaise_id = ?");
    $stmt->bind_param("sii", $variante, $arret_id, $falaise_id);
    $stmt->execute();
    $existant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existant) {
      $a_ajouter = ($description !== '' && trim((string) $existant['description']) === '')
        || ($km !== null && $existant['km'] === null)
        || ($dplus !== null && $existant['dplus'] === null)
        || ($dmoins !== null && $existant['dmoins'] === null)
        || ($dom !== null && !vg_data_exists(velo_gpx_rel((int) $existant['acces_id'], $existant['depart_slug'], $existant['arrivee_slug'], (string) $existant['variante_formate'])));
      if (!$a_ajouter) {
        die("Un accès à pied existe déjà entre cet arrêt et cette falaise, avec ces informations. Pour le corriger, contactez-nous.");
      }
    }

    $res = acces_pied_enregistrer($mysqli, $arret, $falaise, [
      'description' => $description, 'km' => $km, 'dplus' => $dplus, 'dmoins' => $dmoins, 'variante' => $variante,
    ], $dom, $nom_prenom, $email);
    $acces_id = $res['acces_id'];
    $verbe = $res['cree'] ? 'ajouté' : 'complété';

    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
    logChanges($nom_prenom, $email, $res['cree'] ? 'insert' : 'update', 'acces_falaise', $acces_id, $falaise_id, [
      'Type' => 'pied',
      'Arrêt' => $arret['nom'],
      'Falaise' => $falaise['falaise_nom'],
      'Variante' => $variante,
      'Description' => $description,
      'km' => $km === null ? '' : (string) $km,
      'D+' => $dplus === null ? '' : (string) $dplus,
      'D-' => $dmoins === null ? '' : (string) $dmoins,
      'Champs renseignés' => implode(', ', $res['remplis']),
      'Trace GPX' => $res['gpx'],
    ]);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
    $arret_nom_h = htmlspecialchars($arret['nom']);
    $falaise_nom_h = htmlspecialchars($falaise['falaise_nom']);
    $html = "<html><body>";
    $html .= "<h1>Accès à pied $verbe : $arret_nom_h ⇢ $falaise_nom_h</h1>";
    $html .= "<p>Par " . htmlspecialchars($nom_prenom) . " (<a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a>)</p>";
    if ($message) {
      $html .= "<p>Message additionnel : " . nl2br(htmlspecialchars($message)) . "</p>";
    }
    $html .= "<p><b>Description</b> : " . nl2br(htmlspecialchars($description)) . "</p>";
    $html .= "<ul><li><b>Distance</b> : " . ($km === null ? '—' : "$km km") . "</li><li><b>D+</b> : " . ($dplus === null ? '—' : "$dplus m")
      . "</li><li><b>D-</b> : " . ($dmoins === null ? '—' : "$dmoins m") . "</li></ul>";
    if (!$res['cree']) {
      $html .= "<p>Accès existant complété : seuls les champs encore vides ont été renseignés (" . htmlspecialchars(implode(', ', $res['remplis']) ?: 'aucun') . ").</p>";
    }
    $html .= match ($res['gpx']) {
      'ecrit' => "<p>Trace GPX enregistrée.</p>",
      'echec' => "<p>⚠️ <b>La trace GPX n'a pas pu être enregistrée sur le serveur.</b> Redemander le fichier au contributeur.</p>",
      'present' => "<p>Une trace GPX existait déjà : elle n'a pas été remplacée.</p>",
      default => "",
    };
    $html .= "<p><a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id'>Voir la falaise</a></p>";
    $html .= "</body></html>";
    try {
      sendMail([
        'to' => $admin ? $config["admin_mail"] : $config["contact_mail"],
        'subject' => "🚶 Accès à pied $arret[nom] ⇢ $falaise[falaise_nom] $verbe par $nom_prenom",
        'html' => $html,
        'h:Reply-To' => $email,
      ]);
    } catch (\Throwable $e) {
      error_log('[add_velo/pied] sendMail failed: ' . $e->getMessage());
    }

    header("Location: /ajout/confirmation_velo.php?" . http_build_query([
      'falaise_id' => $falaise_id,
      'arret_id' => $arret_id,
      'mode' => 'pied',
      'admin' => $admin ? $config["admin_token"] : '',
    ]));
    exit;
  }

  $velo_depart = $_POST['velo_depart'] ?? null;
  $velo_arrivee = $_POST['velo_arrivee'] ?? null;
  [$velo_km, $velo_dplus, $velo_dmoins] = velo_lire_indicateurs($_POST);
  $velo_descr = $_POST['velo_descr'] ?? null;

  // Vérification des champs obligatoires
  velo_verifier_obligatoires([
    'velo_depart' => $velo_depart,
    'velo_arrivee' => $velo_arrivee,
    'velo_km' => $velo_km,
    'velo_dplus' => $velo_dplus,
    'velo_dmoins' => $velo_dmoins,
  ]);

  $velo_variante = $_POST['velo_variante'] ?? null;
  $velo_varianteformate = $_POST['velo_varianteformate'] ?? null;
  $velo_openrunner = $_POST['velo_openrunner'] ?? null;
  // « À pied possible » n'est plus saisi : il se déduit du type et de la distance (acces_a_pied_possible).
  $velo_apiedpossible = acces_a_pied_possible('velo', $velo_km) ? 1 : 0;
  $velo_public = isset($_POST['velo_public']) ? intval($_POST['velo_public']) : 0;

  // Ces trois champs composent le nom du fichier GPX écrit plus bas (cf. D002).
  // velo_depart et velo_arrivee reprennent les nomformate de la gare et de la
  // falaise, velo_varianteformate vient de formatVariante() côté formulaire.
  velo_verifier_slugs([
    'velo_depart' => $velo_depart,
    'velo_arrivee' => $velo_arrivee,
    'velo_varianteformate' => $velo_varianteformate,
  ]);

  // Gestion du fichier GPX (obligatoire à l'ajout)
  $dom = velo_charger_gpx_upload('gpx_file');
  if ($dom === null) {
    die("Il manque le fichier GPX.");
  }

  // Le nom du GPX contient insert_id : le fichier ne peut pas être écrit avant
  // l'INSERT, on ne peut donc que préflighter son dossier. C'est le seul moment
  // où un échec ne laisse pas de ligne orpheline — le contributeur renvoie son
  // formulaire, fichier compris.
  try {
    vg_data_prepare(VELO_GPX_DIR);
  } catch (VgDataException $e) {
    error_log('[add_velo] ' . $e->getMessage());
    die("Le dossier des traces GPX est indisponible, l'itinéraire n'a pas été enregistré. Merci de réessayer plus tard.");
  }

  // Préparer la requête
  $velo_variante = (string) $velo_variante;
  $velo_varianteformate = (string) $velo_varianteformate;
  $velo_descr = (string) $velo_descr;
  $stmt = $mysqli->prepare("INSERT INTO acces_falaise
        (type, arret_id, falaise_id, depart_slug, arrivee_slug, km, dplus, dmoins,
        description, public, variante, variante_formate, lien_openrunner,
        contrib, contrib_mail)
        VALUES ('velo', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

  if ($stmt) {
    $stmt->bind_param(
      "iissdiisisssss",
      $arret_id,
      $falaise_id,
      $velo_depart,
      $velo_arrivee,
      $velo_km,
      $velo_dplus,
      $velo_dmoins,
      $velo_descr,
      $velo_public,
      $velo_variante,
      $velo_varianteformate,
      $velo_openrunner,
      $nom_prenom,
      $email
    );

    try {
      $stmt->execute();
    } catch (mysqli_sql_exception $e) {
      if ($e->getCode() === 1062) {
        die("Un accès vélo avec cette variante existe déjà entre cet arrêt et cette falaise. Pour le corriger, contactez-nous.");
      }
      throw $e;
    }
    $velo_id = $stmt->insert_id;

    // Sans cette garde, un insert_id à 0 ferait écrire la trace sous « 0_… », en
    // écrasant celle d'une précédente contribution ratée.
    if (!$velo_id) {
      die("Erreur lors de l'insertion : identifiant d'itinéraire non attribué.");
    }

    // Enregistrer le fichier GPX nettoyé. velo_id vient d'insert_id et les trois
    // autres segments sont validés en amont : le chemin ne peut pas sortir du dossier.
    $gpx_target_file = velo_gpx_chemin((int) $velo_id, $velo_depart, $velo_arrivee, (string) $velo_varianteformate);
    // Dégradation volontaire : la ligne est déjà insérée et le fichier téléversé
    // est perdu avec la requête, donc une erreur fatale ici coûterait plus au
    // contributeur qu'elle ne rapporte (il ne pourrait pas réessayer sans créer
    // un doublon). L'itinéraire s'affiche sans tracé — fetch_velos renvoie
    // gpx_url: null, le front le gère. La panne est remontée à l'admin par mail.
    $gpx_ecrit = $dom->save($gpx_target_file) !== false;
    if (!$gpx_ecrit) {
      error_log("add_velo: échec de l'écriture du GPX nettoyé pour velo_id=$velo_id");
    }

    $stmt->close();

    //Store in log
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
    $new_comment = [
      "arret_id" => $arret_id,
      "falaise_id" => $falaise_id,
      "velo_depart" => $velo_depart,
      "velo_arrivee" => $velo_arrivee,
      "velo_km" => $velo_km,
      "velo_dplus" => $velo_dplus,
      "velo_dmoins" => $velo_dmoins,
      "velo_descr" => $velo_descr,
      "velo_public" => $velo_public,
      "velo_variante" => $velo_variante,
      "velo_varianteformate" => $velo_varianteformate,
      "velo_openrunner" => $velo_openrunner,
      "contrib" => $nom_prenom,
      "contrib_mail" => $email,
    ];
    logChanges(
      $nom_prenom,
      $email,
      'insert',
      'acces_falaise',
      $velo_id,
      $falaise_id,
      $new_comment
    );

    // Mail de notification : admin_mail en mode admin, contact_mail sinon
    // (même schéma que add_falaise.php / add_arret.php).
    {
      require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
      $to = $admin ? $config["admin_mail"] : $config["contact_mail"];

      $subject = "🚲 Itinéraire $velo_depart ⇢ $velo_arrivee ajouté par $nom_prenom";

      $html = "<html><body>";
      $html .= "<h1>L'itinéraire de $velo_depart à $velo_arrivee a été ajouté par $nom_prenom</h1>";
      $html .= "<p>email : <a href='mailto:$email'>$email</a></p>";
      $html .= "<p><a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id'>Voir la falaise</a><br/><br/></p>";
      if ($message) {
        $html .= "<p>Message additionnel : " . htmlspecialchars(nl2br(trim($message))) . "<br/><br/></p>";
      }
      $html .= "<p>Détails de l'itinéraire :</p>";
      $html .= "<ul>";
      $html .= "<li><b>Départ</b>: $velo_depart</li>";
      $html .= "<li><b>Arrivée</b>: $velo_arrivee</li>";
      $html .= "<li><b>Variante</b>: $velo_variante</li>";
      $html .= "<li><b>Distance</b>: $velo_km km</li>";
      $html .= "<li><b>D+</b>: $velo_dplus m</li>";
      $html .= "<li><b>D-</b>: $velo_dmoins m</li>";
      $html .= "<li><b>A pied possible</b>: " . ($velo_apiedpossible ? 'Oui' : 'Non') . "</li>";
      $html .= "<li><b>Description</b>: " . htmlspecialchars(nl2br(trim($velo_descr))) . "</li>";
      $html .= "</ul>";
      if (!$gpx_ecrit) {
        $html .= "<p>⚠️ <b>La trace GPX n'a pas pu être enregistrée sur le serveur.</b> "
          . "L'itinéraire est visible sans tracé ni lien de téléchargement, et il est "
          . "absent de l'export open data. Redemander le fichier au contributeur, puis "
          . "le déposer via le lien de modification ci-dessous.</p>";
      }
      $html .= "<h2>Actions</h2>";
      if ($velo_public !== 1) {
        $html .= "<p>Pour valider cet itinéraire, cliquez sur le lien suivant :</p>";
        $html .= "<p><a href='" . velo_lien_validation($config, (int) $velo_id) . "'>Valider l'itinéraire</a></p>";
      }
      $html .= "<p>Pour le vérifier / modifier :</p>";
      $html .= "<p><a href='" . velo_lien_edition_admin($config, ['falaise_id' => $falaise_id, 'gare_id' => $arret_id, 'velo_id' => $velo_id]) . "'>Modifier l'itinéraire</a></p>";
      $html .= "</body></html>";

      $data = [
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'h:Reply-To' => $email
      ];

      sendMail($data);
    }

    // Rediriger vers la page de confirmation
    $redirect_params = http_build_query([
      'falaise_id' => $falaise_id,
      'arret_id' => $arret_id,
      'admin' => $admin ? $config["admin_token"] : ''
    ]);
    header("Location: /ajout/confirmation_velo.php?$redirect_params");
    exit;

  } else {
    die("Erreur lors de l'insertion : " . $mysqli->error);
  }
}
?>
