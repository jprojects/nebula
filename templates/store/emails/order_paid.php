<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<h1 style="font-size:20px;margin:0 0 8px;"><?= $this->e($this->t('mail.order_paid_title', ['name' => explode(' ', (string) $order['bill_name'])[0]])) ?></h1>
<p><?= $this->e($this->t('mail.order_paid_text', ['number' => $order['number']])) ?></p>
<?= $this->partial('emails/_order', get_defined_vars()) ?>
<?= $this->partial('emails/_button', ['href' => $orderUrl, 'label' => $this->t('mail.view_order')]) ?>
