<?php
/**
 * Geo coding utilities shared by API endpoints.
 * - Load GeoJSON files
 * - Point-in-polygon tests (Polygon, MultiPolygon)
 * - Find containing feature
 * - Extract labels for zones and departments
 */

/**
 * Load a GeoJSON file from disk.
 * @param string $path
 * @return array|null FeatureCollection as associative array or null when invalid/missing
 */
function geojson_load(string $path): ?array
{
  if (!file_exists($path)) {
    return null;
  }
  $content = file_get_contents($path);
  if ($content === false || trim($content) === '') {
    return null;
  }
  $data = json_decode($content, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return null;
  }
  return $data;
}

/**
 * Ray-casting point-in-ring algorithm.
 * @param float $lng
 * @param float $lat
 * @param array $ring Array of [lng, lat] pairs
 */
function geo_point_in_ring(float $lng, float $lat, array $ring): bool
{
  $inside = false;
  $n = count($ring);
  if ($n === 0)
    return false;
  for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
    $xi = $ring[$i][0];
    $yi = $ring[$i][1];
    $xj = $ring[$j][0];
    $yj = $ring[$j][1];
    $den = ($yj - $yi);
    if ($den == 0.0)
      $den = 1e-12; // avoid division by zero
    $intersect = (($yi > $lat) != ($yj > $lat)) &&
      ($lng < ($xj - $xi) * ($lat - $yi) / $den + $xi);
    if ($intersect)
      $inside = !$inside;
  }
  return $inside;
}

/**
 * Point-in-polygon considering holes. Coordinates are [outerRing, hole1, hole2, ...]
 */
function geo_point_in_polygon(float $lng, float $lat, array $coordinates): bool
{
  if (!is_array($coordinates) || count($coordinates) === 0)
    return false;
  $inOuter = geo_point_in_ring($lng, $lat, $coordinates[0]);
  if (!$inOuter)
    return false;
  for ($k = 1; $k < count($coordinates); $k++) {
    if (geo_point_in_ring($lng, $lat, $coordinates[$k])) {
      return false;
    }
  }
  return true;
}

/**
 * Check if a GeoJSON feature contains the given point.
 */
function geo_feature_contains_point(array $feature, float $lng, float $lat): bool
{
  if (!isset($feature['geometry']) || !isset($feature['geometry']['type']))
    return false;
  $type = $feature['geometry']['type'];
  $coords = $feature['geometry']['coordinates'] ?? null;
  if (!$coords)
    return false;

  if ($type === 'Polygon') {
    return geo_point_in_polygon($lng, $lat, $coords);
  } elseif ($type === 'MultiPolygon') {
    foreach ($coords as $poly) {
      if (geo_point_in_polygon($lng, $lat, $poly))
        return true;
    }
    return false;
  }
  return false;
}

/**
 * Find the first feature containing the given point.
 * @param array $featureCollection GeoJSON FeatureCollection
 */
function geo_find_containing_feature(?array $featureCollection, float $lng, float $lat): ?array
{
  if (!$featureCollection || !isset($featureCollection['features']) || !is_array($featureCollection['features']))
    return null;
  foreach ($featureCollection['features'] as $feat) {
    if (geo_feature_contains_point($feat, $lng, $lat)) {
      return $feat;
    }
  }
  return null;
}

/**
 * Extract a human-friendly zone label from a feature.
 */
function geo_extract_zone_label(?array $feature): ?string
{
  if (!$feature)
    return null;
  $props = $feature['properties'] ?? [];
  foreach (['name'] as $k) {
    if (isset($props[$k]) && $props[$k] !== '')
      return $props[$k];
  }
  if (isset($feature['id']))
    return $feature['id'];
  return null;
}

/**
 * Extract department code and name from a feature.
 * @return array{code:?string,name:?string}
 */
function geo_extract_dept_info(?array $feature): array
{
  if (!$feature)
    return ['code' => null, 'name' => null];
  $props = $feature['properties'] ?? [];
  $code = $props['code'] ?? null;
  $name = $props['nom'] ?? null;
  if (!$code) {
    foreach (['code_insee', 'codeDept', 'dept', 'department'] as $k) {
      if (isset($props[$k]) && $props[$k] !== '') {
        $code = (string) $props[$k];
        break;
      }
    }
  }
  if (!$name) {
    foreach (['name', 'nomDept', 'label'] as $k) {
      if (isset($props[$k]) && $props[$k] !== '') {
        $name = (string) $props[$k];
        break;
      }
    }
  }
  return ['code' => $code, 'name' => $name];
}

?>