<?php

/** Connexion `$mysqli` de la requête si elle existe et n'a pas été fermée, sinon null. */
function vg_edit_logs_connexion_requete(): ?mysqli
{
  $db = $GLOBALS['mysqli'] ?? null;
  if (!($db instanceof mysqli)) return null;
  try {
    return $db->thread_id > 0 ? $db : null; // lève une Error si l'objet a été fermé
  } catch (Error $e) {
    return null;
  }
}

/**
 * Journalise une modification dans `edit_logs`.
 *
 * Convention : `$type` = insert | update | delete ; `$collection` = table (ou domaine) touché ;
 * actions d'admin : `$author` = "Admin", `$email` = "". `$new_values` / `$old_values` : seuls les
 * champs dont la valeur diffère sont enregistrés (comparaison en chaîne).
 *
 * `$db` : connexion à utiliser. Sans elle, celle de la requête (`$mysqli` global) est réutilisée,
 * à défaut une nouvelle est ouverte. Passer la connexion de l'appelant (obligatoire dans une lib)
 * place le log dans sa transaction : annulé avec elle s'il y a rollback.
 *
 * Toute erreur de journalisation interrompt la requête en 500 (JSON).
 */
function logChanges($author, $email, $type, $collection, $id, $falaise_id, $new_values, $old_values = [], ?mysqli $db = null)
{
  if ($db === null) {
    $db = vg_edit_logs_connexion_requete();
    if ($db === null) {
      require $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
      $db = $mysqli;
    }
  }

  // get changes in the record
  $changes = [];
  foreach ($new_values as $field => $newValue) {
    $oldValue = $old_values[$field] ?? "";
    // compare as string
    if (strval($oldValue) !== strval($newValue)) {
      // append to changes
      $changes[] = [
        'field' => $field,
        'old' => $oldValue,
        'new' => $newValue
      ];
    }
  }

  // Store changes in the log table
  $stmt = $db->prepare(
    "INSERT INTO edit_logs (type, collection, record_id, author, author_email, changes, falaise_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
  );
  if (!$stmt) {
    http_response_code(500);
    die(json_encode(["error" => "Problème de préparation de la requête : " . $db->error]));
  }
  $changes_json = json_encode($changes);
  if (!$stmt->bind_param("ssissss", $type, $collection, $id, $author, $email, $changes_json, $falaise_id)) {
    http_response_code(500);
    die(json_encode(["error" => "Problème de liaison des paramètres : " . $stmt->error]));
  }
  // Execute the statement
  if (!$stmt->execute()) {
    http_response_code(500);
    die(json_encode(["error" => "Erreur lors de l'exécution de la requête : " . $stmt->error]));
  }
  $stmt->close();
  return $changes_json;

}

/**
 * Résumé loggable d'un contenu JSON volumineux (sections de newsletter / page) : le contenu complet
 * ferait déborder la colonne `changes` (TEXT). Deux contenus différents donnent deux résumés différents.
 */
function logSectionsDigest(?string $sectionsJson): string
{
  $sections = json_decode((string) $sectionsJson, true);
  $n = is_array($sections) ? count($sections) : 0;
  return $n . " section(s), empreinte " . substr(md5((string) $sectionsJson), 0, 8);
}
