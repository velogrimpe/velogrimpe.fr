<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

$falaises = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_public
                                  FROM falaises
                                  ORDER BY falaise_public + mod(falaise_public, 3) * 3 DESC, falaise_nom ASC
                                  ")->fetch_all(MYSQLI_ASSOC);

$falaises_contrib = array_values(array_filter(
  $falaises,
  fn($falaise) => $falaise['falaise_public'] === "2"
));
$falaises_ht = array_values(array_filter(
  $falaises,
  fn($falaise) => $falaise['falaise_public'] === "3"
));
$falaises_topo = array_values(array_filter(
  $falaises,
  fn($falaise) => $falaise['falaise_public'] === "1"
));

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter des données (admin)</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto p-10 flex flex-col gap-8">
    <h1 class="text-3xl font-bold text-wrap text-center">
      <span class="text-red-900">PANNEAU D'ADMINISTRATION</span>
    </h1>
    <h2 class="text-3xl font-bold text-wrap text-center">Ajouter des données</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <a class="btn btn-primary btn-lg text-primary-content" href="/ajout/ajout_ville.php?admin=<?= $token ?>">Ajouter
        une ville de départ</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/ajout/ajout_falaise.php?admin=<?= $token ?>">Ajouter
        une falaise</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/ajout/ajout_train.php?admin=<?= $token ?>">Ajouter
        un itinéraire train</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/ajout/ajout_velo.php?admin=<?= $token ?>">Ajouter un
        itinéraire vélo</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/admin/oblyk.php?admin=<?= $token ?>">Créer les liens
        Oblyk</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/admin/tableau_trains.php?admin=<?= $token ?>">Récap.
        Trains</a>
      <a class="btn btn-primary btn-lg text-primary-content"
        href="/ajout/contrib/details_falaise.php?falaise_id=247">Éditeur Falaises</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/admin/edit_logs.php">Historique des
        modifications</a>
      <a class="btn btn-primary btn-lg text-primary-content" href="/admin/newsletter.php">Newsletters</a>
    </div>
    <h2 class="text-3xl font-bold text-wrap text-center">Modifier des données</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <label class="flex flex-col gap-1 items-center p-2 rounded-lg bg-primary" for="selectFalaise1">
        <b class="text-base-100 text-lg">⚠️ Falaises à vérifier</b>
        <?php if (count($falaises_contrib) === 0): ?>
          <p class="text-base-100 text-lg">Aucune falaise à vérifier 💪</p>
        <?php else: ?>
          <select id="selectFalaise1" name="selectFalaise1" class="select select-primary select-sm"
            onchange="window.location.href = '/ajout/ajout_falaise.php?admin=<?= $token ?>&falaise_id=' + this.value">
            <option value="">Sélectionner une falaise</option>
            <?php foreach ($falaises_contrib as $falaise): ?>
              <option value="<?= $falaise['falaise_id'] ?>"><?= htmlspecialchars($falaise['falaise_nom'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </label>
      <label class="flex flex-col gap-1 items-center p-2 rounded-lg bg-primary" for="selectFalaise3">
        <b class="text-base-100 text-lg">✅ Falaises du Topo</b>
        <select id="selectFalaise3" name="selectFalaise3" class="select select-primary select-sm"
          onchange="window.location.href = '/ajout/ajout_falaise.php?admin=<?= $token ?>&falaise_id=' + this.value">
          <option value="">Sélectionner une falaise</option>
          <?php foreach ($falaises_topo as $falaise): ?>
            <option value="<?= $falaise['falaise_id'] ?>"><?= htmlspecialchars($falaise['falaise_nom'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="flex flex-col gap-1 items-center p-2 rounded-lg bg-primary" for="selectFalaise2">
        <b class="text-base-100 text-lg">❌ Falaises Hors Topo</b>
        <select id="selectFalaise2" name="selectFalaise2" class="select select-primary select-sm"
          onchange="window.location.href = '/ajout/ajout_falaise.php?admin=<?= $token ?>&falaise_id=' + this.value">
          <option value="">Sélectionner une falaise</option>
          <?php foreach ($falaises_ht as $falaise): ?>
            <option value="<?= $falaise['falaise_id'] ?>"><?= htmlspecialchars($falaise['falaise_nom'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <h2 class="text-3xl font-bold text-wrap text-center">Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <button id="batchGeocodeBtn" class="btn btn-outline btn-lg" type="button">Affectation zones aux falaises</button>
      <button id="ingestCartotrainBtn" class="btn btn-outline btn-lg" type="button">MàJ Cartotrain</button>
    </div>
    <dialog id="batchGeocodeModal" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg">Affectation zones aux falaises</h3>
        <div id="batchGeocodeModalContent" class="py-2 text-sm"></div>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-primary">OK</button>
          </form>
        </div>
      </div>
    </dialog>
    <dialog id="ingestCartotrainModal" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg">MàJ Cartotrain</h3>
        <div id="ingestCartotrainModalContent" class="py-2 text-sm"></div>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-primary">OK</button>
          </form>
        </div>
      </div>
    </dialog>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <script>
    const btn = document.getElementById('batchGeocodeBtn');
    const modal = document.getElementById('batchGeocodeModal');
    const modalContent = document.getElementById('batchGeocodeModalContent');
    if (btn) {
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Exécution en cours…';
        try {
          const res = await fetch('/api/private/batch-geocode.php?admin=<?= $token ?>');
          const isJson = res.headers.get('content-type')?.includes('application/json');
          if (!res.ok) {
            const errTxt = isJson ? JSON.stringify(await res.json()) : await res.text();
            throw new Error(errTxt || `Erreur HTTP ${res.status}`);
          }
          const data = isJson ? await res.json() : {};
          const processed = data.processed ?? '?';
          const updated = data.updated ?? '?';
          const skippedNum = typeof data.skipped === 'number' ? data.skipped : (data.skipped?.length ?? '?');
          const noZoneList = Array.isArray(data.nozone) ? data.nozone.map(s => `${s.falaise_nom} (${s.falaise_id})`).join(', ') : '';
          const skippedList = Array.isArray(data.skipped) ? data.skipped.map(s => `${s.falaise_nom} (${s.falaise_id})`).join(', ') : '';

          let html = `Traitement terminé: <b>${processed}</b> traités, <b>${updated}</b> mis à jour, <b>${skippedNum}</b> ignorés.`;
          if (noZoneList) {
            html += `<br/><b>Sans zone :</b> ${noZoneList}`;
          }
          if (skippedList) {
            html += `<br/><b>Ignorés :</b> ${skippedList}`;
          }
          modalContent.innerHTML = html;
          modal?.showModal();
        } catch (e) {
          alert(`Erreur: ${e.message}`);
        } finally {
          btn.disabled = false;
          btn.textContent = originalText;
        }
      });
    }

    const cartoBtn = document.getElementById('ingestCartotrainBtn');
    const cartoModal = document.getElementById('ingestCartotrainModal');
    const cartoModalContent = document.getElementById('ingestCartotrainModalContent');
    if (cartoBtn) {
      cartoBtn.addEventListener('click', async () => {
        cartoBtn.disabled = true;
        const originalText = cartoBtn.textContent;
        cartoBtn.textContent = 'Exécution en cours…';
        try {
          const res = await fetch('/api/private/crons/ingest_cartotrain.php', {
            headers: { 'Authorization': 'Bearer <?= $config['vg_token'] ?>' }
          });
          const isJson = res.headers.get('content-type')?.includes('application/json');
          if (!res.ok) {
            const errTxt = isJson ? JSON.stringify(await res.json()) : await res.text();
            throw new Error(errTxt || `Erreur HTTP ${res.status}`);
          }
          const data = isJson ? await res.json() : {};
          cartoModalContent.innerHTML = `Import Cartotrain terminé : <b>${data.inserted ?? '?'}</b> lignes insérées, <b>${data.skipped ?? '?'}</b> ignorées.`;
          cartoModal?.showModal();
        } catch (e) {
          alert(`Erreur: ${e.message}`);
        } finally {
          cartoBtn.disabled = false;
          cartoBtn.textContent = originalText;
        }
      });
    }
  </script>
</body>

</html>