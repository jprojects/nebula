<?php
/** @var \Nebula\Shop\StoreView $this */
$o = $order;
$waiting = $o['status'] === 'pending' && $o['payment_status'] !== 'paid' && $lastPayment !== null && $lastPayment['status'] === 'pending';
$tone = ['pending' => 'amber', 'paid' => 'green', 'processing' => 'blue', 'shipped' => 'blue', 'delivered' => 'green', 'cancelled' => 'gray', 'refunded' => 'gray'];
?>
<div class="nb-container nb-section nb-narrow">
  <?php foreach ($flashes as [$type, $message]): ?>
    <p class="nb-alert nb-alert-<?= $this->e($type === 'error' ? 'danger' : $type) ?>"><?= $this->e($message) ?></p>
  <?php endforeach ?>

  <?php if ($justPlaced): ?>
    <div class="nb-thanks">
      <?= $this->icon($o['payment_status'] === 'paid' ? 'circle-check' : 'package-check', 'nb-thanks-icon') ?>
      <h1><?= $this->e($this->t('order.thanks', ['name' => explode(' ', (string) $o['bill_name'])[0]])) ?></h1>
      <p class="nb-muted"><?= $this->e($this->t('order.thanks_text', ['email' => $o['email']])) ?></p>
    </div>
  <?php endif ?>

  <header class="nb-order-head">
    <div>
      <h<?= $justPlaced ? '2' : '1' ?>><?= $this->e($this->t('order.title', ['number' => $o['number']])) ?></h<?= $justPlaced ? '2' : '1' ?>>
      <p class="nb-muted"><?= $this->e($this->date($o['created_at'], true)) ?></p>
    </div>
    <span class="nb-status nb-tone-<?= $this->e($tone[$o['status']] ?? 'gray') ?>" data-order-status><?= $this->e($this->t('status.' . $o['status'])) ?></span>
  </header>

  <?php if ($waiting): ?>
    <p class="nb-alert nb-alert-info" data-order-poll="<?= $this->e($this->urls->order((string) $o['number'], (string) $o['token'])) ?>"
       data-paid-text="<?= $this->e($this->t('status.paid')) ?>">
      <?= $this->icon('loader-circle', 'nb-spin') ?> <?= $this->e($this->t('order.waiting_payment')) ?>
    </p>
  <?php endif ?>

  <?php if ($o['payment_status'] !== 'paid' && $o['status'] === 'pending' && $instructions): ?>
    <section class="nb-panel nb-instructions">
      <h2><?= $this->icon('landmark') ?> <?= $this->e($this->t('order.how_to_pay')) ?></h2>
      <div class="nb-prose"><?= nl2br($this->e($instructions)) ?></div>
    </section>
  <?php endif ?>

  <?php if ($canPay): ?>
    <a class="nb-btn nb-btn-primary nb-btn-lg" href="<?= $this->e($this->url('/pedido/' . rawurlencode((string) $o['number']) . '/pagar', ['t' => $o['token']])) ?>">
      <?= $this->icon('credit-card') ?> <?= $this->e($this->t('order.pay_now', ['method' => $o['payment_name']])) ?>
    </a>
  <?php endif ?>

  <section class="nb-panel">
    <?= $this->partial('partials/order_items', ['order' => $o, 'items' => $items]) ?>
  </section>

  <div class="nb-grid-2 nb-order-info">
    <section class="nb-panel">
      <h3><?= $this->e($this->t('checkout.billing')) ?></h3>
      <address>
        <?= $this->e($o['bill_name']) ?><br>
        <?php if ($o['bill_company']): ?><?= $this->e($o['bill_company']) ?><br><?php endif ?>
        <?= $this->e($o['bill_address']) ?><br><?= $this->e($o['bill_postcode'] . ' ' . $o['bill_city']) ?><br>
        <?= $this->e(trim(($o['bill_province'] ?? '') . ' ' . $o['bill_country'])) ?>
      </address>
    </section>
    <section class="nb-panel">
      <h3><?= $this->e($this->t('checkout.shipping')) ?>: <?= $this->e($o['shipping_name'] ?? '—') ?></h3>
      <?php if ($o['ship_address']): ?>
        <address>
          <?= $this->e($o['ship_name']) ?><br><?= $this->e($o['ship_address']) ?><br>
          <?= $this->e($o['ship_postcode'] . ' ' . $o['ship_city']) ?><br><?= $this->e(trim(($o['ship_province'] ?? '') . ' ' . $o['ship_country'])) ?>
        </address>
      <?php endif ?>
      <?php if ($o['tracking_number']): ?>
        <p><?= $this->icon('truck') ?> <?= $this->e($this->t('order.tracking')) ?>: <strong><?= $this->e($o['tracking_number']) ?></strong></p>
      <?php endif ?>
      <p class="nb-muted"><?= $this->e($this->t('checkout.payment')) ?>: <?= $this->e($o['payment_name']) ?></p>
    </section>
  </div>

  <?php if (count($history) > 1): ?>
    <section class="nb-panel">
      <h3><?= $this->e($this->t('order.history')) ?></h3>
      <ol class="nb-timeline">
        <?php foreach ($history as $h): ?>
          <li><strong><?= $this->e($this->t('status.' . $h['status'])) ?></strong> <span class="nb-muted"><?= $this->e($this->date($h['created_at'], true)) ?></span></li>
        <?php endforeach ?>
      </ol>
    </section>
  <?php endif ?>

  <p><a href="<?= $this->e($this->url('/')) ?>"><?= $this->icon('arrow-left') ?> <?= $this->e($this->t('cart.continue')) ?></a></p>
</div>
