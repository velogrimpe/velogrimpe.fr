<?php

function sendEvent($pageUrl, $userId, $source, $event = "pageviews", $userAgent = null)
{
  $host = "couble.eu";
  $path = "/api/event";
  $payload = json_encode([
    "d" => "velogrimpe.fr",
    "e" => $event,
    "p" => $pageUrl,
    "u" => $userId,
    "s" => $source,
  ]);

  // User-Agent du visiteur à forwarder au service analytics (sinon le service
  // ne voit que la requête serveur-à-serveur, sans browser/OS). On retire tout
  // CR/LF pour empêcher une injection de header dans la requête forgée.
  $uaHeader = "";
  if ($userAgent) {
    $ua = str_replace(["\r", "\n"], "", $userAgent);
    $uaHeader = "User-Agent: $ua\r\n";
  }

  // Fire-and-forget : on ouvre la connexion, on pousse la requête puis on coupe
  // sans lire la réponse. Le rendu de page / le téléchargement n'attend donc
  // jamais le traitement de l'analytics (ni un éventuel timeout si le service
  // est lent ou indisponible). Le timeout de connexion (1s) borne le pire cas.
  $fp = @fsockopen("ssl://" . $host, 443, $errno, $errstr, 1);
  if (!$fp) {
    return;
  }
  $request = "POST $path HTTP/1.1\r\n"
    . "Host: $host\r\n"
    . $uaHeader
    . "Content-Type: application/json\r\n"
    . "Content-Length: " . strlen($payload) . "\r\n"
    . "Connection: Close\r\n"
    . "\r\n"
    . $payload;
  stream_set_timeout($fp, 1);
  fwrite($fp, $request);
  fclose($fp);
}