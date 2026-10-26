<?php
/** @var \Nebula\Shop\StoreView $this */
/** Detalle del pedido para los correos: líneas, totales y direcciones. */
$o = $order;
$td = 'padding:6px 0;border-bottom:1px solid #eef0f3;';
$promos = \Nebula\Shop\Text::json($o['promotions']);
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0;font-size:14px;">
  <?php foreach ($items as $i): ?>
    <tr>
      <td style="<?= $td ?>"><?= $this->e($i['name']) ?><?= $i['color_name'] ? ' · ' . $this->e($i['color_name']) : '' ?> × <?= (int) $i['qty'] ?></td>
      <td style="<?= $td ?>text-align:right;white-space:nowrap;"><?= $this->e($this->money((float) $i['unit_price'] * (int) $i['qty'])) ?></td>
    </tr>
  <?php endforeach ?>
  <?php foreach ($promos as $p): ?>
    <?php if (($p['amount'] ?? 0) > 0): ?>
      <tr><td style="<?= $td ?>color:#047857;"><?= $this->e($p['label'] ?? '') ?></td><td style="<?= $td ?>text-align:right;color:#047857;">−<?= $this->e($this->money((float) $p['amount'])) ?></td></tr>
    <?php endif ?>
  <?php endforeach ?>
  <tr><td style="<?= $td ?>"><?= $this->e($this->t('totals.shipping')) ?> (<?= $this->e($o['shipping_name'] ?? '') ?>)</td><td style="<?= $td ?>text-align:right;"><?= $this->e((float) $o['shipping_cost'] > 0 ? $this->money((float) $o['shipping_cost']) : $this->t('totals.free')) ?></td></tr>
  <?php if ((float) $o['payment_fee'] > 0): ?>
    <tr><td style="<?= $td ?>"><?= $this->e($this->t('totals.payment_fee')) ?></td><td style="<?= $td ?>text-align:right;"><?= $this->e($this->money((float) $o['payment_fee'])) ?></td></tr>
  <?php endif ?>
  <tr><td style="padding:10px 0;font-weight:bold;"><?= $this->e($this->t('totals.total')) ?></td><td style="padding:10px 0;text-align:right;font-weight:bold;"><?= $this->e($this->money((float) $o['total'])) ?></td></tr>
  <tr><td style="font-size:12px;color:#6b7480;"><?= $this->e($this->t('totals.tax_total')) ?></td><td style="font-size:12px;color:#6b7480;text-align:right;"><?= $this->e($this->money((float) $o['tax_total'])) ?></td></tr>
</table>
<p style="font-size:13px;color:#4b5563;">
  <strong><?= $this->e($this->t('checkout.payment')) ?>:</strong> <?= $this->e($o['payment_name']) ?><br>
  <?php if ($o['ship_address']): ?>
    <strong><?= $this->e($this->t('checkout.shipping_address')) ?>:</strong> <?= $this->e($o['ship_name'] . ', ' . $o['ship_address'] . ', ' . $o['ship_postcode'] . ' ' . $o['ship_city'] . ' (' . $o['ship_country'] . ')') ?>
  <?php endif ?>
</p>
