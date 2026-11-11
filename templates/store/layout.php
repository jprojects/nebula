<?php
/** @var \Nebula\Shop\StoreView $this */
$shopName = $this->shopName();
$pageTitle = isset($title) && $title !== $shopName ? $title . ' · ' . $shopName : $shopName;
?><!doctype html>
<html lang="<?= $this->e($this->locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $this->e($pageTitle) ?></title>
  <?php if (!empty($metaDescription)): ?><meta name="description" content="<?= $this->e(mb_substr((string) $metaDescription, 0, 300)) ?>"><?php endif ?>
  <?php if (!empty($canonical)): ?><link rel="canonical" href="<?= $this->e($canonical) ?>"><?php endif ?>
  <meta name="csrf-token" content="<?= $this->e($this->csrfToken()) ?>">
  <?php if ($this->hasAsset('favicon.png')): ?><link rel="icon" href="<?= $this->e($this->asset('favicon.png')) ?>" type="image/png"><?php else: ?><link rel="icon" href="<?= $this->e($this->asset('favicon.svg')) ?>" type="image/svg+xml"><?php endif ?>
  <script src="<?= $this->e($this->asset('theme-mode.js')) ?>"></script>
  <link rel="stylesheet" href="<?= $this->e($this->asset('store.css')) ?>">
  <?php if ($brandColor): ?><style>:root{--primary:<?= $this->e($brandColor) ?>}</style><?php endif ?>
  <?php if ($this->hasAsset('theme.css')): ?><link rel="stylesheet" href="<?= $this->e($this->asset('theme.css')) ?>"><?php endif ?>
  <?php if ($this->has('partials/head')): ?><?= $this->partial('partials/head') ?><?php endif ?>
</head>
<body>
<a class="nb-skip" href="#main"><?= $this->e($this->t('layout.skip')) ?></a>

<?= $this->partial('partials/header', ['q' => $q ?? '']) ?>

<main id="main" class="nb-main">
  <?= $content ?>
</main>

<?= $this->partial('partials/footer') ?>

<div class="nb-drawer" data-cart-drawer hidden>
  <div class="nb-drawer-backdrop" data-cart-close></div>
  <aside class="nb-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="nb-cart-title">
    <header class="nb-drawer-head">
      <h2 id="nb-cart-title"><?= $this->e($this->t('cart.title')) ?></h2>
      <button class="nb-icon-btn" type="button" data-cart-close aria-label="<?= $this->e($this->t('layout.close')) ?>"><?= $this->icon('x') ?></button>
    </header>
    <div class="nb-drawer-body" data-cart-body><p class="nb-muted nb-center"><?= $this->e($this->t('layout.loading')) ?></p></div>
  </aside>
</div>

<div class="nb-toasts" data-toasts aria-live="polite"></div>

<script type="application/json" id="nb-config"><?= json_encode([
    'cartUrl' => $this->url('/api/cart'),
    'addUrl' => $this->url('/api/cart/add'),
    'updateUrl' => $this->url('/api/cart/update'),
    'couponUrl' => $this->url('/api/cart/coupon'),
    'texts' => [
        'error' => $this->t('errors.generic'),
        'noResults' => $this->t('search.no_results'),
        'seeAll' => $this->t('search.see_all'),
        'added' => $this->t('cart.added_short'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= $this->e($this->asset('store.js')) ?>" defer></script>
</body>
</html>
