<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<?php if (!$quote['items']): ?>
  <div class="nb-empty-state nb-empty-sm">
    <?= $this->icon('shopping-bag', 'nb-empty-icon') ?>
    <p><?= $this->e($this->t('cart.empty')) ?></p>
  </div>
<?php else: ?>
  <?= $this->partial('partials/cart_lines', ['quote' => $quote]) ?>
  <div class="nb-drawer-foot">
    <?= $this->partial('partials/totals', ['quote' => $quote, 'short' => true]) ?>
    <a class="nb-btn nb-btn-primary nb-btn-block" href="<?= $this->e($this->url('/checkout')) ?>"><?= $this->e($this->t('cart.checkout')) ?></a>
    <a class="nb-btn nb-btn-block nb-btn-ghost" href="<?= $this->e($this->url('/carrito')) ?>"><?= $this->e($this->t('cart.view')) ?></a>
  </div>
<?php endif ?>
