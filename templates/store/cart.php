<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section">
  <h1><?= $this->e($this->t('cart.title')) ?></h1>
  <div data-cart-page>
    <?= $this->partial('partials/cart_page', ['quote' => $quote]) ?>
  </div>
</div>
