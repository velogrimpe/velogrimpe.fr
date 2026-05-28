<?php
// Expects $siblings (array of ['slug','title','short_title']) and $current_slug.
if (empty($siblings) || count($siblings) < 2) return;
?>
<nav class="border-b border-base-300 bg-base-100">
  <div class="max-w-(--breakpoint-md) mx-auto px-4">
    <ul class="flex overflow-x-auto gap-1 lg:gap-0 lg:justify-around -mb-px scrollbar-none">
      <?php foreach ($siblings as $item):
        $is_active = $item['slug'] === $current_slug;
        $label = !empty($item['short_title']) ? $item['short_title'] : $item['title'];
        ?>
        <li class="shrink-0">
          <a href="/p/<?= htmlspecialchars($item['slug']) ?>" class="inline-block px-4 py-3 text-sm lg:text-base font-medium border-b-2 transition-colors whitespace-nowrap <?= $is_active
              ? 'border-primary text-primary'
              : 'border-transparent text-base-content/60 hover:text-base-content hover:border-base-300' ?>">
            <?= htmlspecialchars($label) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>
