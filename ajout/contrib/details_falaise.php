<?php
/**
 * Éditeur de détails falaise - Page contributeur
 *
 * Utilise le composant réutilisable falaise-details-editor.php
 */

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["contrib_token"];

$falaise_id = $_GET['falaise_id'] ?? null;
if (empty($falaise_id)) {
  echo 'Pas de falaise renseignée.';
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/components/falaise-details-editor.php';

// Liste des falaises pour le sélecteur
$falaises = $mysqli->query("SELECT falaise_id, falaise_nom
                            FROM falaises
                            ORDER BY falaise_id DESC")->fetch_all(MYSQLI_ASSOC);

// Données de la falaise courante
$stmtF = $mysqli->prepare("SELECT
  f.falaise_id,
  f.falaise_nom,
  f.falaise_nomformate,
  f.falaise_latlng
  FROM falaises f
  WHERE f.falaise_id = ?");
if (!$stmtF) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtF->bind_param("i", $falaise_id);
$stmtF->execute();
$falaise = $stmtF->get_result()->fetch_assoc();
$stmtF->close();

if (!$falaise) {
  echo 'Falaise non trouvée.';
  exit;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Editeur détails falaise - <?= htmlspecialchars($falaise['falaise_nom']) ?> - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Map libraries bundle (Leaflet, Fullscreen, Geoman, GPX, Turf) -->
  <script src="/dist/map.js"></script>
  <link rel="stylesheet" href="/dist/map.css" />
  <script src="/js/vendor/leaflet-textpath.js"></script>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="/index.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="py-4 px-2 md:px-8 flex flex-col gap-4">
    <div class="flex gap-2 justify-start items-center">
      <select id="selectFalaise1" name="selectFalaise1" class="select select-primary select-sm"
        onchange="window.location.href = '/ajout/contrib/details_falaise.php?falaise_id=' + this.value">
        <?php foreach ($falaises as $f): ?>
          <option value="<?= $f['falaise_id'] ?>" <?= $falaise_id == $f["falaise_id"] ? "selected" : "" ?>>
            <?= htmlspecialchars($f['falaise_nom']) ?> - <?= $f['falaise_id'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php
    render_falaise_details_editor($falaise, $token, [
      'height' => 'calc(100vh - 220px)',
      'showToolbar' => true,
      'contribNom' => '',
      'contribEmail' => '',
    ]);
    ?>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>
