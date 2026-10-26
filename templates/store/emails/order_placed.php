<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<h1 style="font-size:20px;margin:0 0 8px;"><?= $this->e($this->t('mail.order_placed_title', ['name' => explode(' ', (string) $order['bill_name'])[0]])) ?></h1>
<p><?= $this->e($this->t('mail.order_placed_text', ['number' => $order['number']])) ?></p>
<?php if ($instructions && $order['payment_status'] !== 'paid'): ?>
  <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:12px 14px;margin:16px 0;">
    <strong><?= $this->e($this->t('order.how_to_pay')) ?></strong><br><?= nl2br($this->e($instructions)) ?>
  </div>
<?php endif ?>
<?= $this->partial('emails/_order', get_defined_vars()) ?>
<?= $this->partial('emails/_button', ['href' => $orderUrl, 'label' => $this->t('mail.view_order')]) ?>
