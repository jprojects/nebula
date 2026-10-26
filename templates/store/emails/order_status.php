<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<h1 style="font-size:20px;margin:0 0 8px;"><?= $this->e($this->t('mail.status_title.' . $status, ['number' => $order['number']])) ?></h1>
<p><?= $this->e($this->t('mail.status_text.' . $status, ['number' => $order['number']])) ?></p>
<?php if ($status === 'shipped' && $order['tracking_number']): ?>
  <p><strong><?= $this->e($this->t('order.tracking')) ?>:</strong> <?= $this->e($order['tracking_number']) ?>
    <?php if (!empty($trackingUrl)): ?> · <a href="<?= $this->e($trackingUrl) ?>"><?= $this->e($this->t('mail.track')) ?></a><?php endif ?></p>
<?php endif ?>
<?php if ($note): ?>
  <div style="background:#f3f4f6;border-radius:6px;padding:12px 14px;margin:16px 0;"><?= nl2br($this->e($note)) ?></div>
<?php endif ?>
<?= $this->partial('emails/_button', ['href' => $orderUrl, 'label' => $this->t('mail.view_order')]) ?>
