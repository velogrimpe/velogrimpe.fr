<?php
/**
 * Données du tableau de bord admin (admin/index.php) : derniers changements de
 * falaises / accès, top des contributeurs.
 *
 * Les adresses de l'équipe (EQUIPE_MAILS) sont exclues des derniers changements (on veut y voir l'activité
 * de la communauté, pas celle de l'admin qui passe par ce compte) mais comptent parmi les contributeurs.
 */

const ADMIN_DASHBOARD_EQUIPE_MAILS = ['contact@velogrimpe.fr'];

/** Résumé court d'un journal `edit_logs` (type + champs modifiés). */
function admin_dashboard_resume(string $collection, string $type, string $changes): string
{
  $fields = [];
  foreach (json_decode($changes, true) ?: [] as $c) {
    if (isset($c['field'])) $fields[] = (string) $c['field'];
  }
  return match ($type) {
    'insert' => 'Création',
    'delete' => 'Suppression',
    'suggestion' => 'Suggestion de modification',
    default => $fields
      ? 'Modifié : ' . implode(', ', array_slice($fields, 0, 4)) . (count($fields) > 4 ? ' +' . (count($fields) - 4) : '')
      : 'Modification',
  };
}

/**
 * Derniers changements DISTINCTS de falaises et d'accès : un seul par falaise (journaux `falaises`) et par accès (`acces_falaise`, `velo` = ancien nom de la table dans les vieux journaux), le plus récent.
 * L'exclusion de l'équipe se fait AVANT de retenir « le plus récent » : on montre le dernier changement
 * de la communauté sur chaque objet.
 *
 * @return array<int, array{date:string, auteur:string, falaise_id:?int, falaise:string, objet:string, resume:string}>
 */
function admin_dashboard_derniers_changements(mysqli $mysqli, int $limit = 10): array
{
  $exclus = "'" . implode("','", array_map([$mysqli, 'real_escape_string'], ADMIN_DASHBOARD_EQUIPE_MAILS)) . "'";
  $res = $mysqli->query(
    "SELECT l.id, l.date, l.type, l.collection, l.record_id, l.author, l.changes,
            COALESCE(l.falaise_id, af.falaise_id) AS falaise_id, f.falaise_nom, ar.nom AS arret_nom
     FROM edit_logs l
     LEFT JOIN acces_falaise af ON l.collection IN ('acces_falaise', 'velo') AND af.acces_id = l.record_id
     LEFT JOIN falaises f ON f.falaise_id = COALESCE(l.falaise_id, af.falaise_id)
     LEFT JOIN arrets ar ON ar.id = af.arret_id
     WHERE l.collection IN ('falaises', 'acces_falaise', 'velo')
       AND LOWER(l.author_email) NOT IN ($exclus)
     ORDER BY l.id DESC
     LIMIT 3000",
  );
  $out = [];
  $vus = [];
  while ($r = $res->fetch_assoc()) {
    $estAcces = in_array($r['collection'], ['acces_falaise', 'velo'], true);
    $cle = ($estAcces ? 'acces:' : 'falaise:') . $r['record_id'];
    if (isset($vus[$cle])) continue;
    $vus[$cle] = true;
    $out[] = [
      'date' => $r['date'],
      'auteur' => (string) $r['author'],
      'falaise_id' => $r['falaise_id'] !== null ? (int) $r['falaise_id'] : null,
      'falaise' => (string) ($r['falaise_nom'] ?? ''),
      'objet' => $estAcces ? 'Accès' . ($r['arret_nom'] ? ' · ' . $r['arret_nom'] : '') : 'Falaise',
      'resume' => admin_dashboard_resume($r['collection'], $r['type'], (string) $r['changes']),
    ];
    if (count($out) >= $limit) break;
  }
  return $out;
}

/**
 * Contributeurs (équipe comprise), regroupés par adresse mail : nombre de falaises créées, nombre d'accès
 * vélo créés et zone de prédilection (celle où tombent le plus de leurs contributions, falaises et accès).
 *
 * @return array<int, array{nom:string, falaises:int, acces_velo:int, zone:string}> classés par total décroissant
 */
function admin_dashboard_contributeurs(mysqli $mysqli): array
{
  $parNom = []; // mail => [nom => n]
  $c = []; // mail => ['falaises' => n, 'acces' => n, 'zones' => [zone => n]]
  $ajoute = function (string $mail, string $nom, string $kind, ?string $zone) use (&$parNom, &$c): void {
    $mail = strtolower(trim($mail));
    if ($mail === '') return;
    $c[$mail] ??= ['falaises' => 0, 'acces' => 0, 'zones' => []];
    $c[$mail][$kind]++;
    $nom = trim($nom);
    if ($nom !== '') $parNom[$mail][$nom] = ($parNom[$mail][$nom] ?? 0) + 1;
    if ($zone !== null && $zone !== '') $c[$mail]['zones'][$zone] = ($c[$mail]['zones'][$zone] ?? 0) + 1;
  };

  // falaise_contrib : « 'Nom','mail' » (guillemets simples, virgule) — le mail est le dernier élément.
  $res = $mysqli->query("SELECT falaise_contrib, falaise_zonename FROM falaises");
  while ($r = $res->fetch_assoc()) {
    $parts = array_map(fn($p) => trim($p, " '\""), explode(',', $r['falaise_contrib']));
    if (count($parts) < 2) continue;
    $mail = array_pop($parts);
    $ajoute($mail, implode(',', $parts), 'falaises', $r['falaise_zonename']);
  }
  $res = $mysqli->query(
    "SELECT af.contrib, af.contrib_mail, f.falaise_zonename
     FROM acces_falaise af LEFT JOIN falaises f ON f.falaise_id = af.falaise_id
     WHERE af.type = 'velo'",
  );
  while ($r = $res->fetch_assoc()) $ajoute($r['contrib_mail'], $r['contrib'], 'acces', $r['falaise_zonename']);

  $out = [];
  foreach ($c as $mail => $d) {
    arsort($d['zones']);
    $noms = $parNom[$mail] ?? [];
    arsort($noms);
    $out[] = [
      'nom' => (string) (array_key_first($noms) ?? $mail),
      'falaises' => $d['falaises'],
      'acces_velo' => $d['acces'],
      'zone' => (string) (array_key_first($d['zones']) ?? ''),
    ];
  }
  usort($out, fn($a, $b) => ($b['falaises'] + $b['acces_velo']) <=> ($a['falaises'] + $a['acces_velo']) ?: strcmp($a['nom'], $b['nom']));
  return $out;
}
