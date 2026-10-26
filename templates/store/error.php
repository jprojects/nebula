<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<section class="nb-container nb-empty-state">
  <?= $this->icon($status === 404 ? 'search-x' : 'triangle-alert', 'nb-empty-icon') ?>
  <h1><?= $this->e($title) ?></h1>
  <p class="nb-muted"><?= $this->e($this->t('errors.http_text_' . ($status === 404 ? '404' : 'other'))) ?></p>
  <a class="nb-btn nb-btn-primary" href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('layout.back_home')) ?></a>
  <?php if (!empty($debug)): ?><pre class="nb-debug"><?= $this->e($debug) ?></pre><?php endif ?>
</section>
