<?php /** @var \Nebula\Shop\StoreView $this */ ?><!doctype html>
<html lang="<?= $this->e($this->locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= $this->e(($title ?? '') . ' · ' . $this->shopName()) ?></title>
  <link rel="icon" href="<?= $this->e($this->asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $this->e($this->asset('store.css')) ?>">
  <?php if (!empty($brandColor)): ?><style>:root{--primary:<?= $this->e($brandColor) ?>}</style><?php endif ?>
</head>
<body class="nb-blank">
<?= $content ?>
<script src="<?= $this->e($this->asset('store.js')) ?>" defer></script>
</body>
</html>
