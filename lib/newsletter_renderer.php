<?php

$_newsletterConfig = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$_newsletterBaseUrl = rtrim($_newsletterConfig['base_url'] ?? 'http://localhost', '/');
$_newsletterHost = strpos($_newsletterBaseUrl, 'localhost') !== false ? $_newsletterBaseUrl . ':4002' : $_newsletterBaseUrl;

/**
 * Convert TipTap semantic HTML to email-compatible HTML with inline styles.
 */
function convertHtmlToEmailHtml(string $html, string $utm = ''): string
{
  global $_newsletterHost;
  $host = $_newsletterHost;

  // Prefix relative URLs with base URL and append UTM
  $html = preg_replace('/src="(\/bdd\/[^"]*)"/', 'src="' . $host . '$1"', $html);
  $html = preg_replace_callback('/href="(\/[^"]*)"/', function ($m) use ($host, $utm) {
    $url = $host . $m[1];
    if ($utm) {
      $url .= (str_contains($m[1], '?') ? '&' : '?') . $utm;
    }
    return 'href="' . $url . '"';
  }, $html);

  // Replace <h2> with inline styles
  $html = preg_replace('/<h2>/', '<h2 style="color: #2e8b57; margin-bottom: 4px;">', $html);

  // Replace <h3> with inline styles
  $html = preg_replace('/<h3>/', '<h3 style="color: #2c3e50; margin-bottom: 2px; margin-top: 8px;">', $html);

  // Replace <a> with inline styles (preserve all attributes, strip any existing style)
  $html = preg_replace_callback('/<a ([^>]*)>/', function ($m) {
    $attrs = preg_replace('/\s*style="[^"]*"/', '', $m[1]);
    return '<a ' . $attrs . ' style="color: #2e8b57; text-decoration: none; font-weight: bold;">';
  }, $html);

  // Replace <ul><li> with email-compatible bullet paragraphs
  // TipTap generates <ul><li><p>content</p></li></ul>
  $html = preg_replace_callback('/<ul>(.*?)<\/ul>/s', function ($matches) {
    $items = $matches[1];
    // Extract content from <li>, stripping inner <p> tags
    return preg_replace_callback('/<li>(.*?)<\/li>/s', function ($liMatch) {
      $content = trim($liMatch[1]);
      $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', $content);
      return '<p style="margin: 0px; margin-left: 20px;">&bull; ' . $content . '</p>';
    }, $items);
  }, $html);

  // Replace <ol><li> with email-compatible numbered paragraphs
  // TipTap generates <ol><li><p>content</p></li></ol>
  $html = preg_replace_callback('/<ol>(.*?)<\/ol>/s', function ($matches) {
    $items = $matches[1];
    $counter = 0;
    return preg_replace_callback('/<li>(.*?)<\/li>/s', function ($liMatch) use (&$counter) {
      $counter++;
      $content = trim($liMatch[1]);
      $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', $content);
      return '<p style="margin: 0px; margin-left: 20px;">' . $counter . '. ' . $content . '</p>';
    }, $items);
  }, $html);

  // Replace <hr> with email-compatible horizontal rule
  $html = preg_replace('/<hr\s*\/?>/', '<table cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 16px 0;"><tr><td style="border-top: 1px solid #ccc;"></td></tr></table>', $html);

  // Handle images - wrap centered images in table, leave others inline
  $html = preg_replace_callback('/<img([^>]*)>/', function ($matches) {
    $attrs = $matches[1];

    // Check for style with text-align center or data-text-align center
    $isCentered = preg_match('/text-align:\s*center/', $attrs) ||
      preg_match('/data-text-align="center"/', $attrs) ||
      !preg_match('/text-align/', $attrs); // default: centered

    // Clean existing style, add email styles
    $attrs = preg_replace('/style="[^"]*"/', '', $attrs);
    $imgStyle = 'border-radius: 12px; border: 1px solid #ccc;';

    // Ensure width if not present
    if (!preg_match('/width=/', $attrs)) {
      $attrs .= ' width="500px"';
    }
    $attrs .= ' height="auto"';

    if ($isCentered) {
      return '<table cellpadding="0" cellspacing="0" border="0" style="margin: 10px 0;"><tr><td style="width: 700px; text-align: center;"><img style="' . $imgStyle . '"' . $attrs . '></td></tr></table>';
    }

    return '<img style="' . $imgStyle . '"' . $attrs . '>';
  }, $html);

  return $html;
}

/**
 * Render the "nouvelles falaises" section for email.
 */
function renderNouvellesFalaisesEmail(array $section, string $utm): string
{
  global $_newsletterHost;
  $host = $_newsletterHost;

  $astyle = 'color: #2e8b57; text-decoration: none; font-weight: bold;';
  $h3Style = 'color: #2c3e50; margin-bottom: 2px; margin-top: 8px;';
  $liStyle = 'margin: 0px; margin-left: 20px;';
  $imageStyle = 'border-radius: 12px; border: 1px solid #ccc;';

  $html = '';

  if (!empty($section['intro_html'])) {
    $html .= convertHtmlToEmailHtml($section['intro_html'], $utm);
  }

  $html .= '<table cellpadding="0" cellspacing="0" border="0" style="margin: 10px 0;"><tr><td style="margin: 0 auto; padding-left: 12px;">';

  foreach ($section['regions'] ?? [] as $region) {
    $html .= '<h3 style="' . $h3Style . '">' . htmlspecialchars($region['name']) . '</h3>';

    if (!empty($region['image'])) {
      $imgSrc = $region['image'];
      if (str_starts_with($imgSrc, '/')) {
        $imgSrc = $host . $imgSrc;
      }
      $html .= '<table cellpadding="0" cellspacing="0" border="0" style="margin: 10px 0;"><tr><td style="width: 700px;">';
      $html .= '<img style="' . $imageStyle . '" width="500px" height="auto" alt="Aperçu des nouvelles falaises de la région ' . htmlspecialchars($region['name']) . '" src="' . htmlspecialchars($imgSrc) . '" />';
      $html .= '</td></tr></table>';
    }

    foreach ($region['falaises'] ?? [] as $falaise) {
      $html .= '<p style="' . $liStyle . '">&bull; <a style="' . $astyle . '" href="' . $host . '/falaise.php?falaise_id=' . intval($falaise['id']) . '&' . $utm . '">';
      $html .= htmlspecialchars($falaise['name']);
      $html .= '</a> par ' . htmlspecialchars($falaise['contributor']) . '</p>';
    }
  }

  $html .= '</td></tr></table>';

  return $html;
}

/**
 * Render a full newsletter as email HTML.
 */
function renderNewsletterEmail(array $newsletter): string
{
  global $_newsletterHost;
  $host = $_newsletterHost;

  $title = htmlspecialchars($newsletter['title']);
  $description = htmlspecialchars($newsletter['description'] ?? '');
  $slug = htmlspecialchars($newsletter['slug']);
  $slugifiedDate = preg_replace('/ /', '', strtolower($newsletter['date_label'] ?? ''));
  $utm = 'source=newsletter-' . $slugifiedDate;

  $bodyStyle = 'font-family: Arial, sans-serif; margin: 0 auto; width: 680px; line-height: 1.6; color: #333; background-color: #eee;';
  $tableStyle = 'width: 700px; background-color: #fff; padding: 20px;';
  $logoStyle = 'background: white; width: 700px; text-align: center; height: auto;';
  $h1Style = 'color: #2c3e50; text-align: center;';
  $webLinkStyle = 'text-align: center; display: block; width: 100%; font-size: 10px; color: #ccc; margin-bottom: 20px; font-weight: normal;';

  $html = '<!DOCTYPE html>
<html lang="fr" style="background-color: #eee;">
<head>
  <meta charset="UTF-8" />
  <meta name="description" content="' . $description . '" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta property="og:locale" content="fr_FR" />
  <meta property="og:title" content="' . $title . '" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Velogrimpe.fr" />
  <meta property="og:url" content="' . $host . '/actualites/newsletter.php?slug=' . $slug . '" />
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp" />
  <meta property="og:description" content="' . $description . '" />
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp" />
  <meta name="twitter:title" content="' . $title . '" />
  <meta name="twitter:description" content="' . $description . '" />
  <meta name="viewport" content="width=device-width" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="x-apple-disable-message-reformatting" />
  <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no" />
  <meta name="color-scheme" content="light" />
  <meta name="supported-color-schemes" content="light" />
  <title>' . $title . '</title>
  <style>a:hover { text-decoration: underline; }</style>
</head>
<body style="' . $bodyStyle . '">
  <table role="presentation" style="' . $tableStyle . '">
    <tr>
      <td>
        <a style="' . $webLinkStyle . '" href="' . $host . '/actualites/newsletter.php?slug=' . $slug . '&' . $utm . '">un problème pour visualiser le contenu ? cliquez ici pour la version web</a>
        <table cellpadding="0" cellspacing="0" border="0" style="margin: 10px 0;">
          <tr>
            <td style="' . $logoStyle . '">
              <a href="' . $host . '/?' . $utm . '">
                <img width="300px" height="auto" src="https://velogrimpe.fr/images/news/logo.png" alt="logo velogrimpe.fr" />
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td>
        <h1 style="' . $h1Style . '">' . preg_replace('/ - /', '<br />', $title, 1) . '</h1>';

  // Render sections
  $sections = $newsletter['sections'] ?? [];
  if (is_string($sections)) {
    $sections = json_decode($sections, true) ?? [];
  }

  foreach ($sections as $section) {
    if ($section['type'] === 'text') {
      $html .= convertHtmlToEmailHtml($section['html'] ?? '', $utm);
    } elseif ($section['type'] === 'nouvelles_falaises') {
      $html .= renderNouvellesFalaisesEmail($section, $utm);
    }
  }

  $html .= '
      </td>
    </tr>
    <tr>
      <td>
        <span data-placeholder></span>
      </td>
    </tr>
  </table>
</body>
</html>';

  return $html;
}

/**
 * Render a newsletter for web display.
 * Same HTML table-based 700px layout for perfect web/email parity.
 */
function renderNewsletterWeb(array $newsletter): string
{
  return renderNewsletterEmail($newsletter);
}
