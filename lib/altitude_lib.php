<?php

/**
 * Récupération de l'altitude (en mètres) d'un point lat/lng via l'API
 * altimétrie de la Géoplateforme IGN (publique, sans clé) :
 *
 *   https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json
 *     ?lon={lng}&lat={lat}&resource=ign_rge_alti_wld&zonly=true
 *
 * Réponse type : {"elevations": [2438.13]}
 * Hors couverture, l'API renvoie une valeur sentinelle (-99999) traitée comme
 * « pas d'altitude ».
 *
 * Robustesse : cette fonction ne lève JAMAIS d'exception et renvoie null sur
 * tout problème (réseau, timeout, HTTP ≠ 200, JSON invalide, format inattendu,
 * sentinelle). Elle ne doit jamais empêcher l'ajout/édition d'une falaise.
 *
 * @return int|null Altitude arrondie au mètre, ou null si indisponible.
 */
function fetch_ign_altitude(float $lat, float $lng): ?int
{
  try {
    $url = "https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json"
      . "?lon=" . urlencode((string) $lng)
      . "&lat=" . urlencode((string) $lat)
      . "&resource=ign_rge_alti_wld&zonly=true";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    // Pas de curl_close() : inutile depuis PHP 8.0.

    if ($curlErr !== '' || $httpCode !== 200 || !is_string($response) || $response === '') {
      error_log(
        "[fetch_ign_altitude] échec lat=$lat lng=$lng http=$httpCode "
        . "curl=" . ($curlErr !== '' ? $curlErr : 'aucune')
      );
      return null;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE
      || !isset($data['elevations'][0])
      || !is_numeric($data['elevations'][0])
    ) {
      error_log("[fetch_ign_altitude] réponse inattendue lat=$lat lng=$lng : "
        . substr((string) $response, 0, 200));
      return null;
    }

    $z = (float) $data['elevations'][0];
    if ($z <= -99990) {
      // Sentinelle hors couverture IGN
      return null;
    }

    return (int) round($z);
  } catch (\Throwable $e) {
    error_log("[fetch_ign_altitude] exception lat=$lat lng=$lng : " . $e->getMessage());
    return null;
  }
}
