<?php
// includes/html_head.php - Common <head> content for authenticated pages
// Set $pageTitle before including. Optional: $extraHeadCss (array), $extraHeadHtml (string)
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7c3aed">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Child Task App'); ?></title>
    <link rel="stylesheet" href="css/main.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/shared.css?v=<?php echo APP_VERSION; ?>">
<?php if (!empty($extraHeadCss)): ?>
<?php foreach ((array) $extraHeadCss as $cssFile): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssFile); ?>">
<?php endforeach; ?>
<?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">
<?php if (!empty($extraHeadHtml)) echo $extraHeadHtml; ?>
