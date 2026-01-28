<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

// Get sortie_id from URL
$sortie_id = isset($_GET['sortie_id']) && !empty($_GET['sortie_id']) ? intval($_GET['sortie_id']) : null;

if ($sortie_id === null) {
  http_response_code(404);
  die("Sortie non trouvée");
}

// Fetch sortie
$stmt = $mysqli->prepare("SELECT * FROM sorties WHERE sortie_id = ?");
$stmt->bind_param("i", $sortie_id);
$stmt->execute();
$result = $stmt->get_result();
$sortie = $result->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(404);
  die("Sortie non trouvée");
}

// Decode falaises_alternatives JSON
$sortie['falaises_alternatives'] = json_decode($sortie['falaises_alternatives'], true) ?? [];

// Compute is_past and is_multi_day
$today = date('Y-m-d');
$sortie['is_past'] = $sortie['date_debut'] < $today;
$sortie['is_multi_day'] = !empty($sortie['date_fin']) && $sortie['date_fin'] !== $sortie['date_debut'];

// Format dates for meta
$formatter = new IntlDateFormatter(
  'fr_FR',                // Locale française
  IntlDateFormatter::SHORT, // Format long pour la date
  IntlDateFormatter::SHORT, // Format court pour l'heure
  'Europe/Paris',         // Fuseau horaire
  IntlDateFormatter::GREGORIAN,
  "E' 'd' 'MMM' 'YY"
);
$date_debut_formatted = $formatter->format(new DateTime($sortie['date_debut']));
if ($sortie['is_multi_day']) {
  $date_formatted = $date_debut_formatted . ' au ' . $formatter->format(new DateTime($sortie['date_fin']));
} else {
  $date_formatted = $date_debut_formatted;
}

$meta_description = "Sortie vélogrimpe à {$sortie['falaise_principale_nom']} le {$date_formatted}. Rejoignez-nous !";
$title = "Sortie Vélogrimpe du {$date_formatted} à " . htmlspecialchars($sortie['falaise_principale_nom']);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title><?= $title ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/sortie.php?sortie_id=<?= $sortie_id ?>" />
  <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/sortie.php?sortie_id=<?= $sortie_id ?>">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/sortie-social.webp">
  <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/sortie-social.webp">
  <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($meta_description) ?>">
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <!-- Vue Component Styles -->
  <?php vite_css('sortie-details'); ?>
</head>

<body>
  <?php include "./components/header.html"; ?>
  <main class="pb-8 px-2 md:px-8 pt-4">
    <div class="max-w-4xl mx-auto">
      <!-- Vue App Container -->
      <div id="vue-sortie-details" data-sortie='<?= htmlspecialchars(json_encode($sortie), ENT_QUOTES) ?>'>
      </div>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <!-- Load Vue app -->
  <?php vite_js('sortie-details'); ?>
</body>

</html>