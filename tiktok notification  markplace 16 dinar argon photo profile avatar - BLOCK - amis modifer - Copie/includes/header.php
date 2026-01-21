<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="description" content="منصة Mohamed Aroussi لمشاركة الفيديوهات والقصص">
  <meta name="keywords" content="فيديو, قصص, مشاركة, محتوى, تيك توك">
  <meta name="author" content="Mohamed Aroussi">
  <title><?php echo isset($pageTitle) ? $pageTitle : 'Mohamed Aroussi'; ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="images/favicon.png">
  <link rel="apple-touch-icon" href="images/apple-touch-icon.png">

  <!-- Mobile Web App Meta -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Mohamed Aroussi">
  <meta name="theme-color" content="#673DE6">
  <link rel="manifest" href="manifest.json">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

  <!-- Custom Theme CSS -->
  <link href="css/theme.css" rel="stylesheet">

  <?php if (isset($additionalCss)): ?>
    <?php foreach ($additionalCss as $css): ?>
      <link href="<?php echo $css; ?>" rel="stylesheet">
    <?php endforeach; ?>
  <?php endif; ?>

  <style>
    body {
      font-family: 'Tajawal', sans-serif;
    }

    /* Add any page-specific styles here */
    <?php if (isset($inlineStyles)): ?>
      <?php echo $inlineStyles; ?>
    <?php endif; ?>
  </style>
</head>
<body>
