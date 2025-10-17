<?php


function fetchMailTemplate($url)
{
  $config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

  $host = $config['base_url'] ?? 'http://localhost';
  $hostWithPort = strpos($host, 'localhost') !== false ? "$host:4000" : $host;
  $options = [
    CURLOPT_URL => "$host$url",
    CURLOPT_RETURNTRANSFER => true,
  ];

  $ch = curl_init();
  curl_setopt_array($ch, $options);
  $mailBody = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  // Check that the response is a 200
  if ($httpCode !== 200) {
    http_response_code(500);
    die("Failed to fetch the template content: $url - HTTP code: $httpCode, body='$mailBody'");
  }
  curl_close($ch);

  // Store document in a variable mailBody
// $recipients = ["yoann@couble.eu", "couble.yoann@gmail.com"];//, "ycouble@icloud.com", "contact@velogrimpe.fr", "marc_miroil@hotmail.com", "amandine.spiandore@orange.fr", "amandine.spiandore@hotmail.fr"];
// parse html for title tag
  preg_match('/<title>(.*?)<\/title>/', $mailBody, $matches);
  $title = trim($matches[1]) ?? 'Actualités Velogrimpe.fr';
  // remove script tags from head
  $mailBody = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $mailBody);

  return [
    'title' => $title,
    'html' => $mailBody
  ];
}