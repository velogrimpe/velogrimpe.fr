<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

$sortie_id = $_GET['sortie_id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$sortie_id || !$token) {
  http_response_code(400);
  echo "<h1>Paramètres manquants</h1>";
  exit;
}

// Verify token and fetch sortie
$stmt = $mysqli->prepare(
  "SELECT * FROM sorties WHERE sortie_id = ? AND edit_token = ?"
);
$stmt->bind_param("is", $sortie_id, $token);
$stmt->execute();
$result = $stmt->get_result();
$sortie = $result->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(404);
  echo "<h1>Sortie non trouvée ou lien invalide</h1>";
  exit;
}

// Decode falaises_alternatives
$sortie['falaises_alternatives'] = json_decode($sortie['falaises_alternatives'], true) ?? [];

// Load villes, falaises, gares for autocomplete
$villes = $mysqli->query("SELECT * FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);
$falaises = $mysqli->query("SELECT falaise_id, falaise_nom FROM falaises WHERE falaise_public >= 1 ORDER BY falaise_nom")->fetch_all(MYSQLI_ASSOC);
$gares = $mysqli->query("SELECT gare_id, gare_nom FROM gares WHERE deleted = 0 ORDER BY gare_nom")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Modifier ma sortie - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <?php vite_css('ajout-sortie'); ?>
</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="pb-8 px-2 md:px-8 pt-4">
    <div class="max-w-4xl mx-auto">
      <div class="mb-6">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Modifier ma sortie</h1>
        <p class="text-base-content/70"> Modifiez les informations de votre sortie d'escalade. </p>
      </div>
      <!-- Vue Form Container -->
      <div id="vue-sortie-form" data-villes='<?= htmlspecialchars(json_encode($villes), ENT_QUOTES) ?>'
        data-falaises='<?= htmlspecialchars(json_encode($falaises), ENT_QUOTES) ?>'
        data-gares='<?= htmlspecialchars(json_encode($gares), ENT_QUOTES) ?>'
        data-sortie='<?= htmlspecialchars(json_encode($sortie), ENT_QUOTES) ?>' data-edit-mode="true"
        data-edit-token="<?= htmlspecialchars($token) ?>">
      </div>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <!-- Load Vue app -->
  <?php vite_js('ajout-sortie'); ?>
</body>

</html>