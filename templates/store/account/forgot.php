<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section nb-auth">
  <form class="nb-panel" data-ajax-form action="<?= $this->e($this->url('/api/account/forgot')) ?>">
    <h1><?= $this->e($this->t('account.forgot')) ?></h1>
    <p class="nb-muted"><?= $this->e($this->t('account.forgot_text')) ?></p>
    <label class="nb-field"><span><?= $this->e($this->t('checkout.email')) ?></span>
      <input type="email" name="email" autocomplete="email" required autofocus></label>
    <div class="nb-form-error" data-form-error hidden></div>
    <button class="nb-btn nb-btn-primary nb-btn-block" type="submit"><?= $this->e($this->t('account.send_link')) ?></button>
    <p class="nb-auth-links"><a href="<?= $this->e($this->url('/entrar')) ?>"><?= $this->icon('arrow-left') ?> <?= $this->e($this->t('account.login')) ?></a></p>
  </form>
</div>
