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
  echo "<h1>Sortie non trouvée</h1>";
  exit;
}

// Format dates
$date_debut = new DateTime($sortie['date_debut']);
$date_debut_formatted = $date_debut->format('d/m/Y');

$date_display = $date_debut_formatted;
if ($sortie['date_fin']) {
  $date_fin = new DateTime($sortie['date_fin']);
  if ($date_fin->format('Y-m-d') !== $sortie['date_debut']) {
    $date_fin_formatted = $date_fin->format('d/m/Y');
    $date_display = "Du $date_debut_formatted au $date_fin_formatted";
  }
}

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sortie publiée - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-full">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="pb-8 px-2 md:px-8 pt-4">
    <div class="max-w-4xl mx-auto">
      <!-- Success message -->
      <div class="alert alert-success mb-6">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 fill-none stroke-current" viewBox="0 0 24 24"
          stroke-width="2">
          <use href="#checkbox-circle-fill"></use>
        </svg>
        <div>
          <h3 class="font-bold">Sortie publiée avec succès !</h3>
          <div class="text-sm">Votre sortie est maintenant visible par tous les utilisateurs.</div>
        </div>
      </div>
      <!-- Sortie details -->
      <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
          <h2 class="card-title text-2xl mb-4">
            <?= htmlspecialchars($sortie['falaise_principale_nom']) ?>
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <div class="text-sm text-base-content/70">Date</div>
              <div class="font-semibold"><?= $date_display ?></div>
            </div>
            <div>
              <div class="text-sm text-base-content/70">Ville de départ</div>
              <div class="font-semibold"><?= htmlspecialchars($sortie['ville_depart']) ?></div>
            </div>
            <?php if ($sortie['velo_nom']): ?>
              <div>
                <div class="text-sm text-base-content/70">Itinéraire vélo</div>
                <div class="font-semibold"><?= htmlspecialchars($sortie['velo_nom']) ?></div>
              </div>
            <?php endif; ?>
            <div>
              <div class="text-sm text-base-content/70">Organisateur</div>
              <div class="font-semibold"><?= htmlspecialchars($sortie['organisateur_nom']) ?></div>
            </div>
          </div>
          <?php
          $falaises_alt = json_decode($sortie['falaises_alternatives'], true);
          if (!empty($falaises_alt)): ?>
            <div class="mb-4">
              <div class="text-sm text-base-content/70 mb-2">Falaises alternatives</div>
              <div class="flex flex-wrap gap-2">
                <?php foreach ($falaises_alt as $falaise): ?>
                  <span class="badge badge-outline"><?= htmlspecialchars($falaise['nom']) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="mb-4">
            <div class="text-sm text-base-content/70 mb-2">Description</div>
            <div class="prose prose-sm max-w-none">
              <?= nl2br(htmlspecialchars($sortie['description'])) ?>
            </div>
          </div>
          <div class="mb-4">
            <div class="text-sm text-base-content/70 mb-2">Lien du groupe</div>
            <a href="<?= htmlspecialchars($sortie['lien_groupe']) ?>" target="_blank" rel="noopener noreferrer"
              class="link link-primary">
              <?= htmlspecialchars($sortie['lien_groupe']) ?>
            </a>
          </div>
        </div>
      </div>
      <!-- Important information -->
      <div class="alert alert-info mb-6">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6 fill-none"
          stroke-width="2">
          <use href="#information"></use>
        </svg>
        <div>
          <h3 class="font-bold">Informations importantes</h3>
          <div class="text-sm mt-2">
            <ul class="list-disc list-inside space-y-1">
              <li>Un email de confirmation vous a été envoyé avec un lien pour modifier ou supprimer votre sortie.</li>
              <li>Conservez ce lien précieusement, il ne peut pas être récupéré.</li>
              <li>Si des personnes sont intéressées vous recevrez un email pour vous mettre en contact.</li>
            </ul>
          </div>
        </div>
      </div>
      <!-- Actions -->
      <div class="flex flex-col md:flex-row gap-4">
        <a href="/sorties.php" class="btn btn-primary flex-1"> Voir toutes les sorties </a>
        <a href="/ajout/ajout_sortie.php" class="btn btn-outline flex-1"> Proposer une autre sortie </a>
      </div>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>