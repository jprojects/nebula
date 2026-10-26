<?php
/** @var \Nebula\Shop\StoreView $this */
/** @var array $p producto de Catalog::present() */
$url = $this->urls->product($p['slug']);
?>
<article class="nb-card">
  <a class="nb-card-media" href="<?= $this->e($url) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($p['cover']): ?>
      <img src="<?= $this->e($this->img($p['cover'], true)) ?>" alt="" loading="lazy">
    <?php else: ?>
      <span class="nb-card-placeholder"><?= $this->icon('image') ?></span>
    <?php endif ?>
    <?php if ($p['compare']): ?><span class="nb-tag nb-tag-sale"><?= $this->e($p['promo'] ?? $this->t('product.sale')) ?></span><?php endif ?>
    <?php if (!$p['in_stock']): ?><span class="nb-tag nb-tag-muted"><?= $this->e($this->t('product.out_of_stock')) ?></span><?php endif ?>
  </a>
  <div class="nb-card-body">
    <h3 class="nb-card-title"><a href="<?= $this->e($url) ?>"><?= $this->e($p['name']) ?></a></h3>
    <?php if ($p['colors']): ?>
      <div class="nb-swatches nb-swatches-sm" aria-label="<?= $this->e($this->t('product.colors_n', ['n' => count($p['colors'])])) ?>">
        <?php foreach (array_slice($p['colors'], 0, 6) as $c): ?>
          <span class="nb-swatch" style="--swatch:<?= $this->e($c['hex'] ?? '#ccc') ?>" title="<?= $this->e($c['name']) ?>"></span>
        <?php endforeach ?>
        <?php if (count($p['colors']) > 6): ?><span class="nb-muted">+<?= count($p['colors']) - 6 ?></span><?php endif ?>
      </div>
    <?php endif ?>
    <div class="nb-price">
      <?php if ($p['from']): ?><span class="nb-price-from"><?= $this->e($this->t('product.from')) ?></span><?php endif ?>
      <span class="nb-price-now"><?= $this->e($this->money($p['price'])) ?></span>
      <?php if ($p['compare']): ?><s class="nb-price-old"><?= $this->e($this->money($p['compare'])) ?></s><?php endif ?>
    </div>
  </div>
</article>
