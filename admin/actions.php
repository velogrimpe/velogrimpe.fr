<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Actions (admin) - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php $admin_active = 'actions'; include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-open.php"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto p-4 flex flex-col gap-4">
    <h1 class="text-2xl font-bold"><span class="text-red-900">ADMIN</span> · Actions</h1>
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
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-close.php"; ?>
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
