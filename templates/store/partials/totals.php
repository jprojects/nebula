<?php
/** @var \Nebula\Shop\StoreView $this */
/** Totales de un carrito valorado. $short: sin envío ni pago (panel lateral y página del carrito). */
$short = $short ?? false;
?>
<dl class="nb-totals">
  <div><dt><?= $this->e($this->t('totals.subtotal')) ?></dt><dd><?= $this->e($this->money($quote['subtotal'])) ?></dd></div>
  <?php foreach ($quote['promotions'] as $promo): ?>
    <?php if ($promo['amount'] > 0): ?>
      <div class="nb-totals-discount"><dt><?= $this->icon('tag') ?> <?= $this->e($promo['label']) ?></dt><dd>−<?= $this->e($this->money($promo['amount'])) ?></dd></div>
    <?php elseif ($promo['type'] === 'free_shipping'): ?>
      <div class="nb-totals-discount"><dt><?= $this->icon('truck') ?> <?= $this->e($promo['label']) ?></dt><dd><?= $this->e($this->t('totals.free_shipping')) ?></dd></div>
    <?php endif ?>
  <?php endforeach ?>
  <?php if (!$short): ?>
    <div><dt><?= $this->e($this->t('totals.shipping')) ?><?= $quote['shipping'] ? ' · ' . $this->e($quote['shipping']['name']) : '' ?></dt>
      <dd><?= $quote['shipping'] ? $this->e($quote['shipping_cost'] > 0 ? $this->money($quote['shipping_cost']) : $this->t('totals.free')) : '—' ?></dd></div>
    <?php if ($quote['payment_fee'] > 0): ?>
      <div><dt><?= $this->e($this->t('totals.payment_fee')) ?></dt><dd><?= $this->e($this->money($quote['payment_fee'])) ?></dd></div>
    <?php endif ?>
    <div class="nb-totals-total"><dt><?= $this->e($this->t('totals.total')) ?></dt><dd><?= $this->e($this->money($quote['total'])) ?></dd></div>
    <?php foreach ($quote['taxes'] as $tax): ?>
      <div class="nb-totals-tax"><dt><?= $this->e($this->t('totals.tax_included', ['rate' => rtrim(rtrim(number_format($tax['rate'], 2, ',', ''), '0'), ',')])) ?></dt><dd><?= $this->e($this->money($tax['tax'])) ?></dd></div>
    <?php endforeach ?>
  <?php else: ?>
    <div class="nb-totals-total"><dt><?= $this->e($this->t('totals.products')) ?></dt><dd><?= $this->e($this->money($quote['subtotal'] - $quote['discount'])) ?></dd></div>
    <p class="nb-muted nb-small"><?= $this->e($this->t('totals.shipping_later')) ?></p>
  <?php endif ?>
</dl>
