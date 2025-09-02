<?php

function logChanges($author, $email, $type, $collection, $id, $new_values, $old_values = [])
{
  require "../database/velogrimpe.php";

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

  if (!empty($changes)) {
    // Store changes in the log table
    $stmt = $mysqli->prepare(
      "INSERT INTO edit_logs (type, collection, record_id, author, author_email, changes) VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
      http_response_code(500);
      die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
    }
    $changes_json = json_encode($changes);
    $stmt->bind_param("ssisss", $type, $collection, $id, $author, $email, $changes_json);
    if (!$stmt) {
      http_response_code(500);
      die(json_encode(["error" => "Problème de liaison des paramètres : " . $mysqli->error]));
    }
    // Execute the statement
    $stmt->execute();
    if ($stmt->error) {
      http_response_code(500);
      die(json_encode(["error" => "Erreur lors de l'exécution de la requête : " . $stmt->error]));
    }
    $stmt->close();
  }
}