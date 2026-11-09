<?php

/**
 * Géocodage inverse unitaire via l'API Adresse de la Géoplateforme IGN :
 *
 *   https://data.geopf.fr/geocodage/reverse?lon={lng}&lat={lat}
 *
 * Réponse GeoJSON : on extrait `features[0].properties` (`citycode`,
 * `postcode`, `city`) ; `features` vide = aucun résultat à proximité.
 * Usage unitaire (formulaire d'ajout d'arrêt, Lot 3) — différent du backfill
 * par lot (api/private/backfill_gares_geocode.php, API batch reverse/csv).
 *
 * Robustesse : cette fonction ne lève JAMAIS d'exception et renvoie null sur
 * tout problème (réseau, timeout, HTTP ≠ 200, JSON invalide, aucun résultat).
 *
 * @return array{citycode: string, city: string, postcode: string}|null
 */
function fetch_geoplateforme_reverse_geocode(float $lat, float $lng): ?array
{
  try {
    $url = "https://data.geopf.fr/geocodage/reverse"
      . "?lon=" . urlencode((string) $lng)
      . "&lat=" . urlencode((string) $lat);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    if ($curlErr !== '' || $httpCode !== 200 || !is_string($response) || $response === '') {
      error_log(
        "[fetch_geoplateforme_reverse_geocode] échec lat=$lat lng=$lng http=$httpCode "
        . "curl=" . ($curlErr !== '' ? $curlErr : 'aucune')
      );
      return null;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      error_log("[fetch_geoplateforme_reverse_geocode] réponse inattendue lat=$lat lng=$lng : "
        . substr((string) $response, 0, 200));
      return null;
    }

    $props = $data['features'][0]['properties'] ?? null;
    if (!is_array($props) || !isset($props['citycode'], $props['city'], $props['postcode'])) {
      return null; // aucun résultat à proximité
    }

    return [
      'citycode' => (string) $props['citycode'],
      'city' => (string) $props['city'],
      'postcode' => (string) $props['postcode'],
    ];
  } catch (\Throwable $e) {
    error_log("[fetch_geoplateforme_reverse_geocode] exception lat=$lat lng=$lng : " . $e->getMessage());
    return null;
  }
}

/** Au-delà de ce nombre de points, on passe par l'endpoint batch (limites de débit de l'API IGN). */
const GEOPLATEFORME_BATCH_THRESHOLD = 10;

/**
 * Géocodage inverse d'un lot de points en UN appel HTTP (endpoint CSV de la
 * Géoplateforme, même format de réponse que l'API Adresse) :
 *
 *   POST https://data.geopf.fr/geocodage/reverse/csv  (data=<csv>, lat=lat, lon=lon)
 *
 * La colonne `idx` du CSV d'entrée est un passthrough renvoyé tel quel, ce qui
 * rattache chaque résultat à son point sans rapprocher de coordonnées.
 * Ne lève JAMAIS d'exception : en cas de problème global (réseau, HTTP ≠ 200,
 * CSV inattendu), tous les points valent null.
 *
 * @param array<int|string, array{0: float, 1: float}> $points clé => [lat, lng]
 * @return array<int|string, array{citycode: string, city: string, postcode: string}|null> mêmes clés
 */
function fetch_geoplateforme_reverse_geocode_batch(array $points): array
{
  $results = array_fill_keys(array_keys($points), null);
  if (!$points) {
    return $results;
  }
  $csvPath = null;
  try {
    $keys = array_keys($points);
    $csvPath = tempnam(sys_get_temp_dir(), 'geocode_batch_');
    $fh = fopen($csvPath, 'w');
    fputcsv($fh, ['idx', 'lat', 'lon']);
    foreach ($keys as $i => $key) {
      fputcsv($fh, [$i, $points[$key][0], $points[$key][1]]);
    }
    fclose($fh);

    $ch = curl_init('https://data.geopf.fr/geocodage/reverse/csv');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
      'data' => new CURLFile($csvPath, 'text/csv', 'points.csv'),
      'lat' => 'lat',
      'lon' => 'lon',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
      error_log("[fetch_geoplateforme_reverse_geocode_batch] échec http=$httpCode curl=" . ($curlErr ?: 'aucune'));
      return $results;
    }
    $lines = preg_split('/\r\n|\r|\n/', trim((string) $response));
    $col = array_flip(str_getcsv((string) array_shift($lines)));
    foreach (['idx', 'result_citycode', 'result_city', 'result_postcode'] as $required) {
      if (!isset($col[$required])) {
        error_log("[fetch_geoplateforme_reverse_geocode_batch] colonne '$required' absente de la réponse");
        return $results;
      }
    }
    foreach ($lines as $line) {
      if (trim($line) === '') {
        continue;
      }
      $f = str_getcsv($line);
      $key = $keys[(int) ($f[$col['idx']] ?? -1)] ?? null;
      $citycode = $f[$col['result_citycode']] ?? '';
      if ($key === null || $citycode === '') {
        continue; // aucun résultat pour ce point
      }
      $results[$key] = [
        'citycode' => $citycode,
        'city' => $f[$col['result_city']] ?? '',
        'postcode' => $f[$col['result_postcode']] ?? '',
      ];
    }
  } catch (\Throwable $e) {
    error_log('[fetch_geoplateforme_reverse_geocode_batch] exception : ' . $e->getMessage());
  } finally {
    if ($csvPath !== null && file_exists($csvPath)) {
      unlink($csvPath);
    }
  }
  return $results;
}

/**
 * Géocodage inverse de plusieurs points : appels unitaires jusqu'à
 * GEOPLATEFORME_BATCH_THRESHOLD points, un seul appel batch au-delà.
 *
 * @param array<int|string, array{0: float, 1: float}> $points clé => [lat, lng]
 * @return array<int|string, array{citycode: string, city: string, postcode: string}|null> mêmes clés
 */
function fetch_geoplateforme_reverse_geocode_many(array $points): array
{
  if (count($points) > GEOPLATEFORME_BATCH_THRESHOLD) {
    return fetch_geoplateforme_reverse_geocode_batch($points);
  }
  $results = [];
  foreach ($points as $key => [$lat, $lng]) {
    $results[$key] = fetch_geoplateforme_reverse_geocode((float) $lat, (float) $lng);
  }
  return $results;
}
