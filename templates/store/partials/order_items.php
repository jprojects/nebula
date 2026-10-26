<?php
/** @var \Nebula\Shop\StoreView $this */
/** Líneas y totales de un pedido guardado (página del pedido). */
$o = $order;
$promos = \Nebula\Shop\Text::json($o['promotions']);
?>
<table class="nb-table">
  <thead><tr><th><?= $this->e($this->t('order.product')) ?></th><th class="nb-num"><?= $this->e($this->t('cart.qty')) ?></th><th class="nb-num"><?= $this->e($this->t('totals.total')) ?></th></tr></thead>
  <tbody>
    <?php foreach ($items as $i): ?>
      <tr>
        <td><?= $this->e($i['name']) ?><?php if ($i['color_name']): ?> <span class="nb-muted">· <?= $this->e($i['color_name']) ?></span><?php endif ?>
          <br><small class="nb-muted"><?= $this->e($this->money((float) $i['unit_price'])) ?><?= $i['sku'] ? ' · ' . $this->e($i['sku']) : '' ?></small></td>
        <td class="nb-num"><?= (int) $i['qty'] ?></td>
        <td class="nb-num"><?= $this->e($this->money((float) $i['unit_price'] * (int) $i['qty'])) ?></td>
      </tr>
    <?php endforeach ?>
  </tbody>
  <tfoot>
    <tr><th colspan="2"><?= $this->e($this->t('totals.subtotal')) ?></th><td class="nb-num"><?= $this->e($this->money((float) $o['subtotal'])) ?></td></tr>
    <?php foreach ($promos as $p): ?>
      <?php if (($p['amount'] ?? 0) > 0): ?>
        <tr class="nb-totals-discount"><th colspan="2"><?= $this->e($p['label'] ?? '') ?></th><td class="nb-num">−<?= $this->e($this->money((float) $p['amount'])) ?></td></tr>
      <?php endif ?>
    <?php endforeach ?>
    <tr><th colspan="2"><?= $this->e($this->t('totals.shipping')) ?></th><td class="nb-num"><?= $this->e((float) $o['shipping_cost'] > 0 ? $this->money((float) $o['shipping_cost']) : $this->t('totals.free')) ?></td></tr>
    <?php if ((float) $o['payment_fee'] > 0): ?>
      <tr><th colspan="2"><?= $this->e($this->t('totals.payment_fee')) ?></th><td class="nb-num"><?= $this->e($this->money((float) $o['payment_fee'])) ?></td></tr>
    <?php endif ?>
    <tr class="nb-totals-total"><th colspan="2"><?= $this->e($this->t('totals.total')) ?></th><td class="nb-num"><?= $this->e($this->money((float) $o['total'])) ?></td></tr>
    <tr class="nb-totals-tax"><th colspan="2"><?= $this->e($this->t('totals.tax_total')) ?></th><td class="nb-num"><?= $this->e($this->money((float) $o['tax_total'])) ?></td></tr>
  </tfoot>
</table>
