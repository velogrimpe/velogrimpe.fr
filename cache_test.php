<?php
// === SONDE DE TEST DE CACHE (temporaire) ===
// Page volontairement mise en cache par LiteSpeed afin de détecter le comportement
// du cache Hostinger : on affiche l'heure de rendu PHP. Si deux requêtes rapprochées
// renvoient la même heure => la page est servie depuis le cache. Si l'heure change
// après un appel à /cache_purge.php => la purge X-LiteSpeed-Purge est honorée.
// À SUPPRIMER une fois le test terminé.

// Page volontairement "normale" : aucun en-tête de cache imposé, pour qu'elle soit
// traitée par le "Cache automatique" Hostinger exactement comme carte.php.
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>cache test</title></head>
<body>
<h1>Sonde de cache</h1>
<p>rendered_at = <strong><?php echo date('Y-m-d H:i:s'); ?></strong> (<?php echo microtime(true); ?>)</p>
</body>
</html>
