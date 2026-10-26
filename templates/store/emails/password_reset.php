<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<h1 style="font-size:20px;margin:0 0 8px;"><?= $this->e($this->t('account.reset')) ?></h1>
<p><?= $this->e($this->t('mail.reset_text')) ?></p>
<?= $this->partial('emails/_button', ['href' => $link, 'label' => $this->t('account.save_password')]) ?>
<p style="font-size:12px;color:#6b7480;"><?= $this->e($this->t('mail.reset_ignore')) ?></p>
