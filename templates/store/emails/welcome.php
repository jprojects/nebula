<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<h1 style="font-size:20px;margin:0 0 8px;"><?= $this->e($this->t('mail.welcome_title', ['name' => explode(' ', (string) $customer['name'])[0]])) ?></h1>
<p><?= $this->e($this->t('mail.welcome_text', ['shop' => $this->shopName()])) ?></p>
<?= $this->partial('emails/_button', ['href' => $link, 'label' => $this->t('account.title')]) ?>
