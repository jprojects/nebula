<?php
/** @var \Nebula\Shop\StoreView $this */
/** Líneas del carrito con cantidad editable (panel lateral y página del carrito). */
?>
<ul class="nb-lines">
  <?php foreach ($quote['items'] as $item): ?>
    <li class="nb-line" data-line="<?= $this->e($item['key']) ?>">
      <a class="nb-line-img" href="<?= $this->e($this->urls->product($item['slug'])) ?>">
        <?php if ($item['image']): ?><img src="<?= $this->e($this->img($item['image'], true)) ?>" alt="" loading="lazy"><?php else: ?><?= $this->icon('image') ?><?php endif ?>
      </a>
      <div class="nb-line-info">
        <a class="nb-line-name" href="<?= $this->e($this->urls->product($item['slug'])) ?>"><?= $this->e($item['name']) ?></a>
        <?php if ($item['color_name']): ?>
          <span class="nb-line-meta"><span class="nb-swatch nb-swatch-xs" style="--swatch:<?= $this->e($item['color_hex'] ?? '#ccc') ?>"></span><?= $this->e($item['color_name']) ?></span>
        <?php endif ?>
        <span class="nb-line-meta"><?= $this->e($this->money($item['unit_price'])) ?> / <?= $this->e($this->t('cart.unit')) ?></span>
        <div class="nb-qty nb-qty-sm">
          <button type="button" class="nb-qty-btn" data-line-step="-1" aria-label="<?= $this->e($this->t('cart.less')) ?>"><?= $this->icon('minus') ?></button>
          <input type="number" value="<?= (int) $item['qty'] ?>" min="0" max="<?= (int) $item['max_qty'] ?>" inputmode="numeric" data-line-qty aria-label="<?= $this->e($this->t('cart.qty')) ?>">
          <button type="button" class="nb-qty-btn" data-line-step="1" aria-label="<?= $this->e($this->t('cart.more')) ?>"><?= $this->icon('plus') ?></button>
        </div>
      </div>
      <div class="nb-line-side">
        <span class="nb-line-total"><?= $this->e($this->money($item['total'])) ?></span>
        <button type="button" class="nb-link-btn" data-line-remove aria-label="<?= $this->e($this->t('cart.remove')) ?>"><?= $this->icon('trash-2') ?></button>
      </div>
    </li>
  <?php endforeach ?>
</ul>
