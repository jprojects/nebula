<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<nav class="nb-tabs" aria-label="<?= $this->e($this->t('account.title')) ?>">
  <a href="<?= $this->e($this->url('/cuenta')) ?>"<?= $active === 'orders' ? ' aria-current="page"' : '' ?>><?= $this->icon('package') ?> <?= $this->e($this->t('account.orders')) ?></a>
  <a href="<?= $this->e($this->url('/cuenta/datos')) ?>"<?= $active === 'profile' ? ' aria-current="page"' : '' ?>><?= $this->icon('user-pen') ?> <?= $this->e($this->t('account.profile')) ?></a>
  <button class="nb-link-btn" type="button" data-logout="<?= $this->e($this->url('/api/account/logout')) ?>"><?= $this->icon('log-out') ?> <?= $this->e($this->t('account.logout')) ?></button>
</nav>
