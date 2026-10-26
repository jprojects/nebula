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
  <link rel="icon" href="<?= $this->e($this->asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $this->e($this->asset('store.css')) ?>">
  <?php if ($brandColor): ?><style>:root{--primary:<?= $this->e($brandColor) ?>}</style><?php endif ?>
</head>
<body>
<a class="nb-skip" href="#main"><?= $this->e($this->t('layout.skip')) ?></a>

<header class="nb-header">
  <div class="nb-container nb-header-row">
    <button class="nb-icon-btn nb-menu-toggle" type="button" data-toggle="nav" aria-label="<?= $this->e($this->t('layout.menu')) ?>" aria-expanded="false">
      <?= $this->icon('menu') ?>
    </button>
    <a class="nb-logo" href="<?= $this->e($this->url('/')) ?>">
      <?php if ($hasLogo): ?><img src="<?= $this->e($this->url('/assets/logo')) ?>" alt="<?= $this->e($shopName) ?>"><?php else: ?><span><?= $this->e($shopName) ?></span><?php endif ?>
    </a>

    <form class="nb-search" action="<?= $this->e($this->url('/buscar')) ?>" method="get" role="search" data-search>
      <?= $this->icon('search', 'nb-search-icon') ?>
      <input type="search" name="q" value="<?= $this->e($q ?? '') ?>" placeholder="<?= $this->e($this->t('search.placeholder')) ?>"
             aria-label="<?= $this->e($this->t('search.placeholder')) ?>" autocomplete="off" data-search-input
             data-suggest-url="<?= $this->e($this->url('/api/search')) ?>">
      <div class="nb-suggest" data-suggest hidden></div>
    </form>

    <nav class="nb-header-actions" aria-label="<?= $this->e($this->t('layout.account_nav')) ?>">
      <?php if (count($locales) > 1): ?>
        <details class="nb-lang">
          <summary class="nb-icon-btn" aria-label="<?= $this->e($this->t('layout.language')) ?>"><?= $this->icon('globe') ?><span><?= $this->e(strtoupper($this->locale())) ?></span></summary>
          <div class="nb-dropdown">
            <?php foreach ($locales as $code => $label): ?>
              <a href="<?= $this->e($this->url('/idioma/' . $code)) ?>"<?= $code === $this->locale() ? ' aria-current="true"' : '' ?>><?= $this->e($label) ?></a>
            <?php endforeach ?>
          </div>
        </details>
      <?php endif ?>
      <a class="nb-icon-btn" href="<?= $this->e($this->url($customer ? '/cuenta' : '/entrar')) ?>" title="<?= $this->e($customer ? $this->t('account.title') : $this->t('account.login')) ?>">
        <?= $this->icon($customer ? 'circle-user-round' : 'user') ?>
        <span class="nb-hide-sm"><?= $this->e($customer ? (explode(' ', (string) $customer['name'])[0] ?: $this->t('account.title')) : $this->t('account.login')) ?></span>
      </a>
      <button class="nb-icon-btn nb-cart-btn" type="button" data-cart-open aria-label="<?= $this->e($this->t('cart.title')) ?>">
        <?= $this->icon('shopping-bag') ?>
        <span class="nb-badge" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= (int) $cartCount ?></span>
      </button>
    </nav>
  </div>

  <nav class="nb-nav" data-nav aria-label="<?= $this->e($this->t('layout.categories')) ?>">
    <ul class="nb-container nb-nav-list">
      <?php foreach ($menu as $cat): ?>
        <li class="<?= $cat['children'] ? 'has-children' : '' ?>">
          <a href="<?= $this->e($this->urls->category($cat['slug'])) ?>"<?= $path === '/c/' . $cat['slug'] ? ' aria-current="page"' : '' ?>><?= $this->e($cat['name']) ?></a>
          <?php if ($cat['children']): ?>
            <ul class="nb-subnav">
              <?php foreach ($cat['children'] as $sub): ?>
                <li><a href="<?= $this->e($this->urls->category($sub['slug'])) ?>"><?= $this->e($sub['name']) ?></a></li>
              <?php endforeach ?>
            </ul>
          <?php endif ?>
        </li>
      <?php endforeach ?>
    </ul>
  </nav>
</header>

<main id="main" class="nb-main">
  <?= $content ?>
</main>

<footer class="nb-footer">
  <div class="nb-container nb-footer-row">
    <div>
      <strong><?= $this->e($shopName) ?></strong>
      <p class="nb-muted">© <?= date('Y') ?></p>
    </div>
    <?php if ($footerLinks): ?>
      <ul class="nb-footer-links">
        <?php foreach ($footerLinks as $label => $href): ?>
          <li><a href="<?= $this->e(str_starts_with((string) $href, 'http') ? $href : $this->url((string) $href)) ?>"><?= $this->e($label) ?></a></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
    <div class="nb-pay-icons nb-muted"><?= $this->icon('lock') ?> <?= $this->e($this->t('layout.secure')) ?></div>
  </div>
</footer>

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
