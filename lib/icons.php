<?php
/**
 * Icon helper function
 *
 * Displays an icon from the SVG sprite at /symbols/icons.svg
 *
 * Usage:
 *   <?= icon('search') ?>
 *   <?= icon('search', 'w-6 h-6 text-primary') ?>
 *   <?= icon('filter', 'w-4 h-4', ['title' => 'Filtrer']) ?>
 *
 * @param string $name Icon name (e.g., 'search', 'filter', 'close')
 * @param string $class CSS classes (default: 'w-4 h-4 fill-none stroke-current')
 * @param array $attrs Additional SVG attributes
 * @return string HTML string
 */
function icon(string $name, string $class = 'w-4 h-4 fill-none stroke-current', array $attrs = []): string
{
    $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $attrStr = '';
    foreach ($attrs as $key => $value) {
        $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $attrStr .= " {$key}=\"{$value}\"";
    }

    return <<<HTML
<svg class="{$class}" aria-hidden="true"{$attrStr}>
    <use href="#{$name}"></use>
</svg>
HTML;
}

/**
 * Mapping from old ri-* icon names to new names
 * Use this during migration to find the new name
 */
function icon_migrate(string $oldName): string
{
    $map = [
        'search' => 'search',
        'filter' => 'filter',
        'close' => 'close',
        'mail' => 'mail',
        'mail-line' => 'mail-line',
        'phone' => 'phone',
        'user' => 'user',
        'pencil' => 'pencil',
        'external-link' => 'external-link',
        'checkbox-circle-fill' => 'checkbox-circle-fill',
        'error-warning-fill' => 'error-warning-fill',
        'information' => 'information',
        'save' => 'save',
        'file-upload' => 'file-upload',
        'chat' => 'chat',
        'building' => 'building',
        'ticket' => 'ticket',
        'sun-foggy' => 'sun-foggy',
        'sun-cloudy' => 'sun-cloudy',
        'riding' => 'riding',
        'footprint' => 'footprint',
        'login' => 'login',
        'logout' => 'logout',
        'table' => 'table',
        'sort-desc' => 'sort-desc',
        'link' => 'link',
        'eye' => 'eye',
        'arrow-right' => 'arrow-right',
    ];

    return $map[$oldName] ?? $oldName;
}
