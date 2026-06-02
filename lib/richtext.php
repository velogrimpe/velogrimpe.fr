<?php

/**
 * Sanitisation et helpers pour les champs riches (éditeur TipTap).
 *
 * Périmètre volontairement minimal : gras, italique, barré, souligné, lien,
 * liste à puces. Toute autre balise est « déballée » (on garde son contenu
 * textuel) et les balises dangereuses (script/style) sont supprimées.
 *
 * La sanitisation est faite côté serveur à l'écriture (formulaire + migration) :
 * l'affichage peut donc sortir le HTML stocké directement.
 */

/** Balises autorisées => liste des attributs autorisés. */
function rt_allowed_tags(): array
{
    return [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        's' => [],
        'u' => [],
        'ul' => [],
        'li' => [],
        'a' => ['href', 'target', 'rel'],
    ];
}

/** true si l'URL d'un lien est acceptable (http/https/mailto, ancre ou relatif). */
function rt_safe_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if ($url[0] === '#' || $url[0] === '/') {
        return true;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme === '') {
        return true; // relatif
    }
    return in_array($scheme, ['http', 'https', 'mailto'], true);
}

/** Remonte les enfants d'un élément à sa place puis le supprime. */
function rt_unwrap_node(DOMElement $el): void
{
    $parent = $el->parentNode;
    if (!$parent) {
        return;
    }
    while ($el->firstChild) {
        $parent->insertBefore($el->firstChild, $el);
    }
    $parent->removeChild($el);
}

/** Nettoie récursivement les enfants d'un nœud selon l'allowlist. */
function rt_clean_node(DOMNode $node, array $allowed): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            continue; // texte conservé (échappé à la sérialisation)
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            $child->parentNode->removeChild($child); // commentaires, PI…
            continue;
        }

        $tag = strtolower($child->nodeName);

        // Balise interdite : on supprime script/style entièrement, sinon on déballe.
        if (!array_key_exists($tag, $allowed)) {
            if (in_array($tag, ['script', 'style'], true)) {
                $child->parentNode->removeChild($child);
                continue;
            }
            rt_clean_node($child, $allowed);
            rt_unwrap_node($child);
            continue;
        }

        // Nettoyage des attributs.
        if ($child->hasAttributes()) {
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                if ($name === 'href' && !rt_safe_url($attr->value)) {
                    $child->removeAttribute($attr->name);
                }
            }
        }

        // <br> enfant direct d'une liste : invalide en HTML, on le retire.
        if ($tag === 'br') {
            $parentTag = strtolower($child->parentNode->nodeName ?? '');
            if ($parentTag === 'ul' || $parentTag === 'ol') {
                $child->parentNode->removeChild($child);
                continue;
            }
        }

        // Lien : href valide => on force target/rel sûrs, sinon on déballe.
        if ($tag === 'a') {
            if ($child->getAttribute('href') === '') {
                rt_clean_node($child, $allowed);
                rt_unwrap_node($child);
                continue;
            }
            $child->setAttribute('target', '_blank');
            $child->setAttribute('rel', 'noopener nofollow');
        }

        rt_clean_node($child, $allowed);
    }
}

/**
 * Assainit du HTML utilisateur selon l'allowlist.
 * Retourne une chaîne HTML sûre (ou '' si vide).
 */
function rt_sanitize_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    // Le préfixe XML force l'interprétation UTF-8 par libxml.
    $dom->loadHTML(
        '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>',
        LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }

    rt_clean_node($body, rt_allowed_tags());

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }

    return trim($out);
}

/**
 * Rend un champ riche pour l'affichage.
 * - HTML déjà riche (assaini à l'écriture) : sorti tel quel.
 * - Ancien texte brut non encore migré : échappé + \n => <br> (rendu fidèle).
 * Fonctionne donc que la migration ait été lancée ou non.
 */
function rt_display(?string $value): string
{
    $value = (string) $value;
    if (trim($value) === '') {
        return '';
    }
    if (rt_looks_like_html($value)) {
        return $value;
    }
    return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), false);
}

/**
 * Retire un unique <p>…</p> englobant, pour un rendu inline
 * (ex. champ « Approche : … » sur la même ligne que le libellé).
 */
function rt_unwrap_p(?string $html): string
{
    $html = trim((string) $html);
    if (preg_match('#^<p>(.*)</p>$#is', $html, $m) && stripos($m[1], '<p') === false) {
        return $m[1];
    }
    return $html;
}

/**
 * Rendu inline du champ « Approche » : déballe le <p> et met la 1re lettre
 * en minuscule (le libellé « Approche : » précède le texte).
 */
function rt_matxt_display(?string $value): string
{
    $html = rt_unwrap_p(rt_display($value));
    if ($html === '') {
        return '';
    }
    return mb_strtolower(mb_substr($html, 0, 1)) . mb_substr($html, 1);
}

/** true si la valeur ressemble déjà à du HTML riche (balises de l'allowlist). */
function rt_looks_like_html(?string $value): bool
{
    return (bool) preg_match('#<(p|br|strong|b|em|i|s|u|ul|li|a)\b[^>]*>#i', (string) $value);
}

/** Remplace chaque retour à la ligne par <br> (sans \n résiduel). */
function rt_nl_to_br(string $s): string
{
    return preg_replace('/\r\n|\r|\n/', '<br>', $s);
}

/**
 * Convertit du texte brut (avec \n) en HTML fidèle : échappement + \n => <br>,
 * le tout dans un <p>.
 */
function rt_from_plaintext(?string $text): string
{
    $text = (string) $text;
    if (trim($text) === '') {
        return '';
    }
    $esc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return '<p>' . rt_nl_to_br($esc) . '</p>';
}

/**
 * Valeur migrée pour un champ existant, idempotente :
 *  - texte brut  => échappé + \n => <br> dans un <p> (rt_from_plaintext) ;
 *  - HTML existant (liens, listes…) => on redonne les <br> perdus (comme
 *    l'ancien nl2br), on assainit (allowlist), puis on englobe d'un <p> si le
 *    contenu est purement inline.
 * Tout le HTML existant étant déjà dans l'allowlist, la conversion est sans perte.
 */
function rt_migrate_value(?string $raw): string
{
    $raw = (string) $raw;
    if (trim($raw) === '') {
        return '';
    }

    if (!rt_looks_like_html($raw)) {
        return rt_from_plaintext($raw);
    }

    $html = rt_sanitize_html(rt_nl_to_br($raw));
    if ($html === '') {
        return '';
    }
    // Déjà structuré par un bloc (paragraphe, liste) : pas d'enveloppe (nesting invalide).
    if (preg_match('#<(p|ul|ol|li)\b#i', $html)) {
        return $html;
    }
    return '<p>' . $html . '</p>';
}
