<?php

function sendEvent($pageUrl, $userId, $source, $event = "pageviews")
{
  $url = "https://couble.eu/api/event";
  $data = [
    "d" => "velogrimpe.fr",
    "e" => $event,
    "p" => $pageUrl,
    "u" => $userId,
    "s" => $source,
  ];

  $options = [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($data),
  ];

  $ch = curl_init();
  curl_setopt_array($ch, $options);
  curl_exec($ch);
  curl_close($ch);
}