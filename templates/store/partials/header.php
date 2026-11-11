<?php
/**
 * @var \Nebula\Shop\StoreView $this
 * Cabecera de la tienda (la incluye layout.php). Un tema la sustituye con su propio partials/header.php; los atributos
 * data-* son los que usa store.js.
 */
$shopName = $this->shopName();
?>
<header class="nb-header">
  <div class="nb-container nb-header-row">
    <button class="nb-icon-btn nb-menu-toggle" type="button" data-mega-open aria-controls="nb-mega" aria-expanded="false" aria-label="<?= $this->e($this->t('layout.menu')) ?>">
      <?= $this->icon('menu') ?>
    </button>
    <a class="nb-logo" href="<?= $this->e($this->url('/')) ?>">
      <?php if ($hasLogo): ?><img src="<?= $this->e($this->url('/assets/logo')) ?>" alt="<?= $this->e($shopName) ?>"><?php else: ?><span><?= $this->e($shopName) ?></span><?php endif ?>
    </a>
    <?php if ($menu): ?>
      <button class="nb-mega-btn" type="button" data-mega-open aria-controls="nb-mega" aria-expanded="false">
        <?= $this->icon('layout-grid') ?><span><?= $this->e($this->t('layout.all_categories')) ?></span><?= $this->icon('chevron-down', 'nb-mega-caret') ?>
      </button>
    <?php endif ?>

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
      <?php if ($themeSwitch ?? true): ?>
        <button class="nb-icon-btn nb-theme-btn" type="button" data-theme-toggle aria-pressed="false"
                aria-label="<?= $this->e($this->t('layout.theme_dark')) ?>" data-label-dark="<?= $this->e($this->t('layout.theme_dark')) ?>" data-label-light="<?= $this->e($this->t('layout.theme_light')) ?>">
          <?= $this->icon('moon', 'nb-theme-moon') ?><?= $this->icon('sun', 'nb-theme-sun') ?>
        </button>
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

</header>
<?php if ($menu): ?><?= $this->partial('partials/mega_menu') ?><?php endif ?>
