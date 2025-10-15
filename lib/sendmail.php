<?php

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
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "$mailgun_baseurl/v3/$mailgun_domain/messages");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERPWD, "api:$mailgun_api_key");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  $response = curl_exec($ch);
  curl_close($ch);
  // check response status
  if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
    return false;
  }
  return true;
}