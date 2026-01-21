<?php
$bodyStyle = "font-family: Arial, sans-serif; margin: 0 auto; width: 680px; line-height: 1.6; color: #333; background-color: #eee;";
$astyle = "color: #2e8b57; text-decoration: none; font-weight: bold;";
$tableStyle = "width: 700px; background-color: #fff; padding: 20px;";
$logoStyle = "background: white; width: 700px;text-align: center; height: auto;";
$h1Style = "color: #2c3e50; text-align: center;";

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$host = $config['base_url'] ?? 'http://localhost';
$hostWithPort = strpos($host, 'localhost') !== false ? "$host:4002" : $host;
$utm = "source=newsletter-subscription";

?>
<!DOCTYPE html>
<html lang="fr" style="background-color: #eee;">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <!-- Forcing initial-scale shouldn't be necessary -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <!-- Use the latest(edge) version of IE rendering engine -->
  <meta name="x-apple-disable-message-reformatting" />
  <!-- Disable auto-scale in iOS 10 Mail entirely -->
  <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no" />
  <!-- Tell iOS not to automatically link certain text strings. -->
  <meta name="color-scheme" content="light" />
  <meta name="supported-color-schemes" content="light" />
  <!-- What it does: Makes background images in 72ppi Outlook render at correct size. -->
  <title>Confirmation d'inscription à la newsletter Velogrimpe.fr</title>
  <style>
    /* Hopefully get it rendered */
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body style="<?= $bodyStyle ?>">
  <table role="presentation" style="<?= $tableStyle ?>">
    <tr>
      <td>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $logoStyle ?>">
              <a href="<?= $hostWithPort ?>/?<?= $utm ?>">
                <img width="300px" height="auto" src="<?= $hostWithPort ?>/images/news/logo.png"
                  alt="logo velogrimpe.fr" />
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td>
        <h1 style="<?= $h1Style ?>">Inscription à la newsletter Velogrimpe.fr</h1>
        <p style="<?= $pStyle ?>">Merci de vous être inscrit à notre newsletter ! Pour confirmer votre inscription,
          veuillez cliquer sur le lien ci-dessous.</p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $logoStyle ?>">
              <a href="<?= $hostWithPort ?>/api/newsletter_confirmation.php?email=<?= urlencode($_GET['email']) ?>&token=<?= urlencode($_GET['token']) ?>"
                style="<?= $astyle ?>">Confirmer mon inscription</a>
            </td>
          </tr>
        </table>
        <p style="<?= $pStyle ?>">Si vous n'avez pas demandé cette inscription, vous pouvez l'ignorer, vous ne recevrez
          pas d'autre mails de notre part.</p>
        <p style="<?= $pStyle ?>"><b>L'équipe Velogrimpe.fr</b></p>
      </td>
    </tr>
  </table>
</body>

</html>