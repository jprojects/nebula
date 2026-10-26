<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<nav class="nb-crumbs" aria-label="<?= $this->e($this->t('layout.breadcrumbs')) ?>">
  <a href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('layout.home')) ?></a>
  <?php foreach ($crumbs as $i => $c): ?>
    <?= $this->icon('chevron-right') ?>
    <?php if ($i === count($crumbs) - 1 && ($current ?? null) === null && empty($linkLast)): ?>
      <span aria-current="page"><?= $this->e($c['name']) ?></span>
    <?php else: ?>
      <a href="<?= $this->e($this->urls->category($c['slug'])) ?>"><?= $this->e($c['name']) ?></a>
    <?php endif ?>
  <?php endforeach ?>
  <?php if (($current ?? null) !== null): ?>
    <?= $this->icon('chevron-right') ?><span aria-current="page"><?= $this->e($current) ?></span>
  <?php endif ?>
</nav>
