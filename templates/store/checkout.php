<?php
/** @var \Nebula\Shop\StoreView $this */
$c = $customer;
$bill = $initial + ['name' => $c['name'] ?? ''];
?>
<div class="nb-container nb-section">
  <h1><?= $this->e($this->t('checkout.title')) ?></h1>

  <form class="nb-checkout" data-checkout novalidate
        data-quote-url="<?= $this->e($this->url('/api/checkout/quote')) ?>"
        data-place-url="<?= $this->e($this->url('/api/checkout/place')) ?>">
    <input type="hidden" name="expected_total" value="<?= $this->e(\Nebula\Shop\Money::plain($quote['total'])) ?>" data-expected-total>
    <div class="nb-checkout-main">

      <section class="nb-panel nb-step">
        <h2><span class="nb-step-n">1</span> <?= $this->e($this->t('checkout.contact')) ?></h2>
        <?php if ($c): ?>
          <p><?= $this->icon('circle-user-round') ?> <?= $this->e($this->t('checkout.logged_as', ['email' => $c['email']])) ?></p>
          <input type="hidden" name="email" value="<?= $this->e($c['email']) ?>">
        <?php else: ?>
          <p class="nb-muted"><?= $this->e($this->t('checkout.have_account')) ?> <a href="<?= $this->e($this->url('/entrar', ['next' => $this->url('/checkout')])) ?>"><?= $this->e($this->t('account.login')) ?></a></p>
        <?php endif ?>
        <div class="nb-grid-2">
          <?php if (!$c): ?>
            <label class="nb-field"><span><?= $this->e($this->t('checkout.email')) ?> *</span>
              <input type="email" name="email" autocomplete="email" required data-quote-trigger></label>
          <?php endif ?>
          <label class="nb-field"><span><?= $this->e($this->t('checkout.phone')) ?></span>
            <input type="tel" name="phone" value="<?= $this->e($c['phone'] ?? '') ?>" autocomplete="tel"></label>
        </div>
        <?php if (!$c): ?>
          <label class="nb-check"><input type="checkbox" name="create_account" value="1" data-toggle-target="#nb-password-box"<?= $guestAllowed ? '' : ' checked disabled' ?>>
            <span><?= $this->e($this->t('checkout.create_account')) ?></span></label>
          <?php if (!$guestAllowed): ?><input type="hidden" name="create_account" value="1"><?php endif ?>
          <div id="nb-password-box"<?= $guestAllowed ? ' hidden' : '' ?>>
            <label class="nb-field"><span><?= $this->e($this->t('account.password')) ?> *</span>
              <input type="password" name="password" autocomplete="new-password" minlength="8"></label>
          </div>
        <?php endif ?>
      </section>

      <section class="nb-panel nb-step">
        <h2><span class="nb-step-n">2</span> <?= $this->e($this->t('checkout.billing')) ?></h2>
        <?php if (count($addresses) > 1): ?>
          <label class="nb-field"><span><?= $this->e($this->t('checkout.saved_addresses')) ?></span>
            <select data-address-book="bill_" data-addresses="<?= $this->json($addresses) ?>">
              <?php foreach ($addresses as $i => $a): ?>
                <option value="<?= $i ?>"><?= $this->e(($a['label'] ?? '') !== '' ? $a['label'] : $a['address'] . ', ' . $a['city']) ?></option>
              <?php endforeach ?>
            </select></label>
        <?php endif ?>
        <?= $this->partial('partials/address_fields', ['prefix' => 'bill_', 'ac' => 'billing', 'values' => $bill, 'countries' => $countries]) ?>
        <div class="nb-grid-2">
          <label class="nb-field"><span><?= $this->e($this->t('address.company')) ?></span>
            <input name="bill_company" value="<?= $this->e($c['company'] ?? '') ?>" autocomplete="organization"></label>
          <label class="nb-field"><span><?= $this->e($this->t('address.vat_id')) ?></span>
            <input name="bill_vat_id" value="<?= $this->e($c['vat_id'] ?? '') ?>"></label>
        </div>
        <label class="nb-check" data-ship-same-box><input type="checkbox" name="ship_same" value="1" checked data-toggle-target="#nb-ship-box" data-toggle-invert data-quote-trigger>
          <span><?= $this->e($this->t('checkout.ship_same')) ?></span></label>
        <div id="nb-ship-box" hidden>
          <h3><?= $this->e($this->t('checkout.shipping_address')) ?></h3>
          <?= $this->partial('partials/address_fields', ['prefix' => 'ship_', 'ac' => 'shipping', 'values' => ['country' => $bill['country']], 'countries' => $countries]) ?>
          <label class="nb-field"><span><?= $this->e($this->t('checkout.ship_phone')) ?></span>
            <input type="tel" name="ship_phone" autocomplete="shipping tel"></label>
        </div>
      </section>

      <section class="nb-panel nb-step">
        <h2><span class="nb-step-n">3</span> <?= $this->e($this->t('checkout.shipping')) ?></h2>
        <div data-shipping-options><?= $this->partial('partials/checkout_shipping', ['quote' => $quote]) ?></div>
      </section>

      <section class="nb-panel nb-step">
        <h2><span class="nb-step-n">4</span> <?= $this->e($this->t('checkout.payment')) ?></h2>
        <div data-payment-options><?= $this->partial('partials/checkout_payment', ['quote' => $quote]) ?></div>
        <label class="nb-field"><span><?= $this->e($this->t('checkout.notes')) ?></span>
          <textarea name="notes" rows="2" maxlength="1000"></textarea></label>
      </section>
    </div>

    <aside class="nb-checkout-aside">
      <div class="nb-panel nb-sticky">
        <h2><?= $this->e($this->t('checkout.summary')) ?></h2>
        <div data-checkout-summary><?= $this->partial('partials/checkout_summary', ['quote' => $quote]) ?></div>
        <label class="nb-check nb-terms"><input type="checkbox" name="accept_terms" value="1" required>
          <span><?= $this->e($this->t('checkout.accept_terms')) ?></span></label>
        <?php if (!$c): ?>
          <label class="nb-check"><input type="checkbox" name="accepts_marketing" value="1"><span><?= $this->e($this->t('checkout.marketing')) ?></span></label>
        <?php endif ?>
        <div class="nb-form-error" data-form-error hidden></div>
        <button class="nb-btn nb-btn-primary nb-btn-block nb-btn-lg" type="submit" data-place-button>
          <?= $this->icon('lock') ?> <?= $this->e($this->t('checkout.place')) ?>
        </button>
      </div>
    </aside>
  </form>
</div>
