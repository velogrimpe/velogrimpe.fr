<?php
// === ENDPOINT DE PURGE DE TEST (temporaire) ===
// Émet l'en-tête de purge LiteSpeed. C'est le mécanisme qu'on veut valider :
// si Hostinger l'honore, le cache de tout le site est vidé immédiatement.
// À SUPPRIMER (ou à transformer en helper) une fois le test concluant.

header('Content-Type: text/plain; charset=utf-8');
// Empêche la mise en cache de cette réponse de purge elle-même.
header('X-LiteSpeed-Cache-Control: no-cache');
// Purge l'intégralité du cache du site.
header('X-LiteSpeed-Purge: *');

echo "purge envoyée (X-LiteSpeed-Purge: *) à " . date('Y-m-d H:i:s') . "\n";
