<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<h1 style="font-size:20px;margin:0 0 8px;"><?= $this->e($this->t('mail.shop_title', ['number' => $order['number']])) ?></h1>
<p><?= $this->e($order['bill_name']) ?> · <?= $this->e($order['email']) ?><?= $order['phone'] ? ' · ' . $this->e($order['phone']) : '' ?><br>
  <?= $this->e($this->t('status.' . $order['status'])) ?> · <?= $this->e($order['payment_name']) ?></p>
<?php if ($order['notes']): ?><p style="background:#f3f4f6;border-radius:6px;padding:10px 12px;"><?= nl2br($this->e($order['notes'])) ?></p><?php endif ?>
<?= $this->partial('emails/_order', get_defined_vars()) ?>
<?= $this->partial('emails/_button', ['href' => $adminUrl, 'label' => $this->t('mail.open_admin')]) ?>
