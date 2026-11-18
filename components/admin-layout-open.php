<?php
/**
 * Ouverture du layout admin : en-tête du site, volet latéral, puis ouverture de
 * la colonne de contenu. À utiliser À LA PLACE de l'include de header.html, et à
 * refermer avec admin-layout-close.php (à la place du footer).
 *
 * Variable attendue : `$admin_active` (clé d'entrée de components/admin-nav.php).
 * Elle est aussi lue par header.html pour intégrer la navigation admin dans le
 * tiroir mobile.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/components/admin-nav.php';
$vg_admin_config = $vg_admin_config ?? require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$vg_admin_token = (string) $vg_admin_config['admin_token'];
$admin_active = $admin_active ?? '';
include $_SERVER['DOCUMENT_ROOT'] . '/components/header.html';
?>
<link rel="stylesheet" href="/css/admin-ui.css" />
<div class="vg-admin-shell flex grow min-h-0 w-full">
  <aside class="vg-admin-sidebar hidden xl:block w-64 shrink-0 bg-base-200 border-r border-base-300" aria-label="Navigation admin">
    <ul class="menu w-full p-3 gap-0.5 sticky top-0 max-h-screen overflow-y-auto flex-nowrap">
      <?= vg_admin_nav_items_html($vg_admin_token, $admin_active) ?>
    </ul>
  </aside>
  <div class="vg-admin-content grow min-w-0 min-h-0 flex flex-col">
