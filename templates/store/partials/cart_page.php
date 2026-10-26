<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<?php if (!$quote['items']): ?>
  <div class="nb-empty-state">
    <?= $this->icon('shopping-bag', 'nb-empty-icon') ?>
    <p><?= $this->e($this->t('cart.empty')) ?></p>
    <a class="nb-btn nb-btn-primary" href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('cart.continue')) ?></a>
  </div>
<?php else: ?>
  <?php foreach ($quote['notices'] as $n): ?>
    <p class="nb-alert nb-alert-warning"><?= $this->e($this->t('cart.notice_' . $n['type'], ['name' => $n['name'] ?? '', 'qty' => $n['qty'] ?? ''])) ?></p>
  <?php endforeach ?>
  <div class="nb-cart-layout">
    <div class="nb-panel"><?= $this->partial('partials/cart_lines', ['quote' => $quote]) ?></div>
    <aside class="nb-panel nb-cart-aside">
      <form class="nb-coupon" data-coupon>
        <label for="nb-coupon-code"><?= $this->e($this->t('cart.coupon')) ?></label>
        <div class="nb-input-row">
          <input id="nb-coupon-code" name="code" value="<?= $this->e($quote['coupon'] ?? '') ?>" autocomplete="off" placeholder="<?= $this->e($this->t('cart.coupon_placeholder')) ?>">
          <button class="nb-btn" type="submit"><?= $this->e($this->t('cart.apply')) ?></button>
        </div>
        <?php if ($quote['coupon']): ?>
          <button class="nb-link-btn nb-small" type="button" data-coupon-remove><?= $this->icon('x') ?> <?= $this->e($this->t('cart.coupon_remove')) ?></button>
        <?php endif ?>
      </form>
      <?= $this->partial('partials/totals', ['quote' => $quote, 'short' => true]) ?>
      <a class="nb-btn nb-btn-primary nb-btn-block nb-btn-lg" href="<?= $this->e($this->url('/checkout')) ?>"><?= $this->e($this->t('cart.checkout')) ?></a>
      <a class="nb-btn nb-btn-ghost nb-btn-block" href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('cart.continue')) ?></a>
    </aside>
  </div>
<?php endif ?>
