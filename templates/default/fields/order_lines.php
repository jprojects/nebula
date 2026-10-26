<?php
/** @var \Nebula\Core\View $this */
/** Líneas de un pedido en su ficha (campo "display" de modules/orders.json). */
$money = fn ($v) => \Nebula\Shop\Money::format((float) $v, $this->locale());
$o = $value['order'];
$promos = \Nebula\Shop\Text::json($o['promotions'] ?? null);
?>
<table class="nb-admin-table">
  <thead>
    <tr>
      <th><?= $this->e($this->t('lines.product')) ?></th>
      <th><?= $this->e($this->t('lines.sku')) ?></th>
      <th class="num"><?= $this->e($this->t('lines.qty')) ?></th>
      <th class="num"><?= $this->e($this->t('lines.unit_price')) ?></th>
      <th class="num"><?= $this->e($this->t('lines.discount')) ?></th>
      <th class="num"><?= $this->e($this->t('lines.tax')) ?></th>
      <th class="num"><?= $this->e($this->t('lines.total')) ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($value['items'] as $i): ?>
      <tr>
        <td>
          <?php if ($i['product_id']): ?><a href="<?= $this->e($this->url('edit', ['m' => 'products', 'id' => $i['product_id']])) ?>"><?= $this->e($i['name']) ?></a><?php else: ?><?= $this->e($i['name']) ?><?php endif ?>
          <?php if ($i['color_name']): ?><span class="nb-muted"> · <?= $this->e($i['color_name']) ?></span><?php endif ?>
        </td>
        <td><?= $this->e($i['sku'] ?? '') ?></td>
        <td class="num"><?= (int) $i['qty'] ?></td>
        <td class="num"><?= $this->e($money($i['unit_price'])) ?></td>
        <td class="num"><?= (float) $i['discount'] > 0 ? '−' . $this->e($money($i['discount'])) : '' ?></td>
        <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $i['tax_rate'], 2, ',', ''), '0'), ',')) ?> %</td>
        <td class="num"><strong><?= $this->e($money($i['total'])) ?></strong></td>
      </tr>
    <?php endforeach ?>
  </tbody>
  <tfoot>
    <tr><th colspan="6"><?= $this->e($this->t('fields.subtotal')) ?></th><td class="num"><?= $this->e($money($o['subtotal'])) ?></td></tr>
    <?php foreach ($promos as $p): ?>
      <tr><th colspan="6"><?= $this->e(($p['label'] ?? '') . (!empty($p['code']) ? ' (' . $p['code'] . ')' : '')) ?></th>
        <td class="num"><?= ($p['amount'] ?? 0) > 0 ? '−' . $this->e($money($p['amount'])) : $this->e($this->t('lines.free_shipping')) ?></td></tr>
    <?php endforeach ?>
    <tr><th colspan="6"><?= $this->e($this->t('fields.shipping_cost')) ?> · <?= $this->e($o['shipping_name'] ?? '') ?></th><td class="num"><?= $this->e($money($o['shipping_cost'])) ?></td></tr>
    <?php if ((float) $o['payment_fee'] > 0): ?>
      <tr><th colspan="6"><?= $this->e($this->t('fields.payment_fee')) ?></th><td class="num"><?= $this->e($money($o['payment_fee'])) ?></td></tr>
    <?php endif ?>
    <tr class="total"><th colspan="6"><?= $this->e($this->t('fields.total')) ?></th><td class="num"><?= $this->e($money($o['total'])) ?></td></tr>
    <?php foreach (\Nebula\Shop\Text::json($o['taxes'] ?? null) as $t): ?>
      <tr class="tax"><th colspan="6"><?= $this->e($this->t('lines.tax_breakdown', ['rate' => rtrim(rtrim(number_format((float) $t['rate'], 2, ',', ''), '0'), ','), 'base' => $money($t['base'])])) ?></th><td class="num"><?= $this->e($money($t['tax'])) ?></td></tr>
    <?php endforeach ?>
  </tfoot>
</table>
