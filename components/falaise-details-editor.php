<?php
/**
 * Composant éditeur de détails falaise (secteurs, parkings, approches, etc.)
 *
 * Usage:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/components/falaise-details-editor.php';
 *   render_falaise_details_editor($falaise, $token, $options);
 *
 * @param array $falaise - Données de la falaise (falaise_id, falaise_nom, falaise_nomformate, falaise_latlng)
 * @param string $token - Token d'authentification pour l'API
 * @param array $options - Options (height, showToolbar, showFalaiseSelect, falaises, apiEndpoint, showNavigation, backUrl, nextUrl)
 */

/**
 * Génère le HTML et JS pour l'éditeur de détails falaise
 */
function render_falaise_details_editor(array $falaise, string $token, array $options = []): void
{
  $defaults = [
    'height' => 'calc(100vh - 180px)',
    'showToolbar' => true,
    'showFalaiseSelect' => false,
    'falaises' => [],
    'apiEndpoint' => '/api/private/falaise_details.php',
    'containerId' => 'falaise-details-editor-' . $falaise['falaise_id'],
    'showNavigation' => false,
    'backUrl' => null,
    'nextUrl' => null,
    'contribNom' => '',
    'contribEmail' => '',
  ];
  $opts = array_merge($defaults, $options);
  $containerId = $opts['containerId'];
  $falaiseJson = htmlspecialchars(json_encode($falaise), ENT_QUOTES, 'UTF-8');
  ?>
  <style>
    .vg-icon {
      width: 24px;
      height: 24px;
      background-size: cover;
    }

    .vg-draw-approche {
      background-image: url('/images/map/pm/pm_walking.png');
    }

    .vg-draw-parking {
      background-image: url('/images/map/pm/pm_parking.png');
    }

    .vg-draw-secteur {
      background-image: url('/images/map/pm/pm_rock-climbing.png');
    }

    .vg-draw-ext-falaise {
      background-image: url('/images/map/pm/pm_link.png');
    }

    .vg-draw-velo {
      background-image: url('/images/map/pm/pm_bicycle.png');
    }

    .vg-draw-bus-stop {
      background-image: url('/images/map/pm/pm_bus.png');
    }
  </style>
  <div id="<?= $containerId ?>" class="falaise-details-editor flex flex-col gap-1" data-falaise="<?= $falaiseJson ?>"
    data-token="<?= htmlspecialchars($token) ?>" data-api-endpoint="<?= htmlspecialchars($opts['apiEndpoint']) ?>"
    data-contrib-nom="<?= htmlspecialchars($opts['contribNom']) ?>"
    data-contrib-email="<?= htmlspecialchars($opts['contribEmail']) ?>">
    <?php if ($opts['showToolbar']): ?>
      <div class="flex gap-2 justify-end items-center">
        <?php if ($opts['showFalaiseSelect'] && !empty($opts['falaises'])): ?>
          <select class="select select-primary select-sm falaise-select">
            <?php foreach ($opts['falaises'] as $f): ?>
              <option value="<?= $f['falaise_id'] ?>" <?= $falaise['falaise_id'] == $f['falaise_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($f['falaise_nom']) ?> - <?= $f['falaise_id'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <a class="btn btn-sm" href="/falaise.php?falaise_id=<?= $falaise['falaise_id'] ?>">Voir la falaise</a>
        <input type="file" hidden accept=".geojson" class="upload-geojson-input" />
        <button class="btn btn-sm upload-geojson-btn" title="Importer un fichier GeoJSON et remplacer la carte actuelle">
          <svg class="w-5 h-5 fill-none stroke-current">
            <use href="#file-upload"></use>
          </svg> Import </button>
        <input type="file" hidden accept=".geojson" class="upload-add-geojson-input" />
        <button class="btn btn-sm upload-add-geojson-btn" title="Importer un fichier GeoJSON et ajouter son contenu">
          <svg class="w-5 h-5 fill-none stroke-current">
            <use href="#file-upload"></use>
          </svg> Ajouter </button>
        <button class="btn btn-sm download-geojson-btn">Télécharger le GeoJSON</button>
        <div class="tooltip tooltip-left" data-tip="Cmd/Ctrl + S">
          <button class="btn btn-primary btn-sm save-geojson-btn">Enregistrer</button>
        </div>
        <?php if ($opts['showNavigation']): ?>
          <div class="divider divider-horizontal mx-1"></div>
          <?php if ($opts['backUrl']): ?>
            <a href="<?= htmlspecialchars($opts['backUrl']) ?>" class="btn btn-sm btn-outline">
              <svg class="w-4 h-4 fill-none stroke-current">
                <use href="#arrow-left"></use>
              </svg> Retour </a>
          <?php endif; ?>
          <?php if ($opts['nextUrl']): ?>
            <button class="btn btn-sm btn-secondary save-and-next-btn"
              data-next-url="<?= htmlspecialchars($opts['nextUrl']) ?>"> Terminer <svg class="w-4 h-4 fill-none stroke-current">
                <use href="#arrow-right"></use>
              </svg>
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="relative">
      <div class="editor-map w-full" style="height: <?= $opts['height'] ?>"></div>
      <div class="absolute bottom-3 left-3 z-10000 flex gap-1">
        <input class="input input-sm rounded-none coords-input" type="text" placeholder="ex: 45.1234,6.2355"
          value="<?= htmlspecialchars($falaise['falaise_latlng']) ?>">
        <button class="btn btn-sm btn-primary px-1 goto-coords-btn">
          <svg class="w-5 h-5 fill-none stroke-current">
            <use href="#arrow-right"></use>
          </svg>
        </button>
      </div>
    </div>
    <dialog class="modal modal-bottom tableau-modal">
      <div class="modal-box w-screen max-w-full h-full">
        <form method="dialog">
          <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-bold text-xl">Tableau récapitulatif</h3>
        <p class="text-error text-sm">N'oubliez pas de sauvegarder le GeoJSON après avoir modifié les données dans le
          tableau.</p>
        <div class="tableau-recap flex flex-col gap-1"></div>
      </div>
    </dialog>
    <dialog class="modal contrib-modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg">Informations contributeur</h3>
        <p class="py-2">Merci d'indiquer vos coordonnées pour cette contribution.</p>
        <div class="flex flex-col gap-3">
          <label class="input input-primary flex items-center gap-2">
            <svg class="w-4 h-4 fill-none stroke-current">
              <use href="#user"></use>
            </svg>
            <input type="text" class="grow contrib-nom-input" placeholder="Prénom (et/ou nom)" required>
          </label>
          <label class="input input-primary flex items-center gap-2">
            <svg class="w-4 h-4 fill-none stroke-current">
              <use href="#mail-line"></use>
            </svg>
            <input type="email" class="grow contrib-email-input" placeholder="Email" required>
          </label>
        </div>
        <div class="modal-action">
          <button class="btn contrib-cancel-btn" type="button">Annuler</button>
          <button class="btn btn-primary contrib-confirm-btn" type="button">Enregistrer</button>
        </div>
      </div>
    </dialog>
  </div>
  <script src="/js/contrib-storage.js"></script>
  <script type="module">
    import { initFalaiseDetailsEditor } from '/js/components/falaise-details-editor.js';
    initFalaiseDetailsEditor('<?= $containerId ?>');
  </script> <?php
}
?>