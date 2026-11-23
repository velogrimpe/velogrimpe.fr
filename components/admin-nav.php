<?php
/**
 * Navigation latérale de l'admin (volet sur écran large, intégrée au tiroir du
 * site en mobile via components/header.php).
 *
 * Une seule définition des entrées : `vg_admin_nav_sections()` ; le rendu
 * (`vg_admin_nav_render()`) sert aussi bien au volet qu'au tiroir mobile.
 * La clé `$admin_active` (posée par la page avant d'inclure le layout) marque
 * l'entrée courante.
 */

/** Sections et entrées. `href` embarque déjà le jeton admin quand la page cible l'exige. */
function vg_admin_nav_sections(string $token): array
{
  $t = '?admin=' . urlencode($token);
  return [
    ['titre' => null, 'items' => [
      'dashboard' => ['Tableau de bord', '/admin/'],
    ]],
    ['titre' => 'Données', 'items' => [
      'falaises' => ['Falaises', '/admin/falaises.php'],
      'acces' => ['Accès falaises', '/admin/acces.php'],
      'transports' => ['Transports en commun', '/admin/arrets_lignes.php'],
      'trains' => ['(Train)', '/admin/trains.php'],
      'villes' => ['(Villes)', '/admin/villes.php'],
    ]],
    ['titre' => 'Communication', 'items' => [
      'newsletters' => ['Newsletters', '/admin/newsletter.php'],
      'pages' => ['Pages CMS', '/admin/pages.php'],
      'qr' => ['QR et liens trackés', '/admin/qr-generator.php' . $t],
    ]],
    ['titre' => 'Autres outils d\'admin', 'items' => [
      'logs' => ['Historique des modifications', '/admin/edit_logs.php'],
      'oblyk' => ['Liens Oblyk', '/admin/oblyk.php' . $t],
      'recap_trains' => ['(Récap train)', '/admin/tableau_trains.php' . $t],
      'actions' => ['Actions', '/admin/actions.php'],
    ]],
  ];
}

/** Items `<li>` du menu (à placer dans un `<ul class="menu">`). */
function vg_admin_nav_items_html(string $token, ?string $active): string
{
  $h = '';
  foreach (vg_admin_nav_sections($token) as $section) {
    if ($section['titre'] !== null) {
      $h .= '<li class="menu-title mt-2">' . htmlspecialchars($section['titre'], ENT_QUOTES, 'UTF-8') . '</li>';
    }
    foreach ($section['items'] as $key => [$label, $href]) {
      $cls = $key === $active ? ' class="menu-active" aria-current="page"' : '';
      $h .= '<li><a' . $cls . ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
  }
  return $h;
}
