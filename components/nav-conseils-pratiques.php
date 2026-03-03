<?php
$nav_items = [
  ['url' => '/conseils-pratiques/equipement-velo.php', 'label' => 'Le vélo'],
  ['url' => '/conseils-pratiques/se-deplacer-en-train.php', 'label' => 'Le train'],
  ['url' => '/conseils-pratiques/prendre-son-velo-dans-le-train.php', 'label' => 'Train + vélo'],
  ['url' => '/conseils-pratiques/camping-escalade-materiel.php', 'label' => 'Le reste'],
];
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?> <nav class="border-b border-base-300 bg-base-100">
  <div class="max-w-(--breakpoint-lg) mx-auto px-4">
    <ul class="flex overflow-x-auto gap-1 lg:gap-0 lg:justify-around -mb-px scrollbar-none">
      <?php foreach ($nav_items as $item):
        $is_active = $current_path === $item['url'];
        ?>
        <li class="shrink-0">
          <a href="<?= $item['url'] ?>" class="inline-block px-4 py-3 text-sm lg:text-base font-medium border-b-2 transition-colors whitespace-nowrap <?= $is_active
              ? 'border-primary text-primary'
              : 'border-transparent text-base-content/60 hover:text-base-content hover:border-base-300' ?>">
            <?= $item['label'] ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>