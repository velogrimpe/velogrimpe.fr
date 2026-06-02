<?php

/**
 * Envoi d'un message via l'API HTTP Mailgun.
 *
 * - Récupère le code HTTP / l'erreur curl juste après curl_exec, tant que le
 *   handle est vivant (curl_close n'est plus nécessaire depuis PHP 8.0).
 * - Logge tout échec (error_log) avec le code, la réponse Mailgun et le destinataire.
 * - Réessaie 1 fois en cas d'échec TRANSITOIRE (erreur réseau, HTTP 5xx, 429).
 *   Les erreurs définitives (4xx : mauvaise clé, domaine non vérifié, requête
 *   invalide…) ne sont pas réessayées, ce serait inutile.
 *
 * @return bool true si Mailgun a accepté le message (HTTP 200).
 */
function sendMail($data)
{
  $config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
  $mailgun_api_key = $config["mailgun_api_key"];
  $mailgun_domain = $config["mailgun_domain"];
  $mailgun_baseurl = $config["mailgun_baseurl"];
  $from = "Velogrimpe.fr <postmaster@$mailgun_domain>";

  // if from is not set in data, use default
  if (!isset($data['from']) || empty($data['from'])) {
    $data["from"] = $from;
  }

  $url = "$mailgun_baseurl/v3/$mailgun_domain/messages";
  $to = is_array($data['to'] ?? null) ? implode(',', $data['to']) : ($data['to'] ?? '?');
  $maxAttempts = 2; // 1 essai + 1 retry

  for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, "api:$mailgun_api_key");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    // Pas de curl_close() : déprécié en PHP 8.5, inutile depuis 8.0 (le handle
    // CurlHandle est libéré par le GC à la réassignation de $ch / fin de fonction).

    if ($curlErr === '' && $httpCode === 200) {
      if ($attempt > 1) {
        error_log("[sendMail] OK au retry (tentative $attempt) pour to=$to");
      }
      return true;
    }

    error_log(
      "[sendMail] échec tentative $attempt/$maxAttempts to=$to http=$httpCode "
      . "curl=" . ($curlErr !== '' ? $curlErr : 'aucune')
      . " réponse=" . substr((string) $response, 0, 500)
    );

    // N'insister que sur une erreur transitoire ; sortir sinon (4xx définitif).
    $transient = $curlErr !== '' || $httpCode === 0 || $httpCode >= 500 || $httpCode === 429;
    if (!$transient || $attempt === $maxAttempts) {
      break;
    }
    usleep(1000000); // 1 s avant de réessayer
  }

  return false;
}
