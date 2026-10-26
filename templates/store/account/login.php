<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section nb-auth">
  <form class="nb-panel" data-ajax-form action="<?= $this->e($this->url('/api/account/login')) ?>">
    <h1><?= $this->e($this->t('account.login')) ?></h1>
    <input type="hidden" name="next" value="<?= $this->e($next ?? '') ?>">
    <label class="nb-field"><span><?= $this->e($this->t('checkout.email')) ?></span>
      <input type="email" name="email" autocomplete="email" required autofocus></label>
    <label class="nb-field"><span><?= $this->e($this->t('account.password')) ?></span>
      <input type="password" name="password" autocomplete="current-password" required></label>
    <div class="nb-form-error" data-form-error hidden></div>
    <button class="nb-btn nb-btn-primary nb-btn-block" type="submit"><?= $this->e($this->t('account.login')) ?></button>
    <p class="nb-auth-links">
      <a href="<?= $this->e($this->url('/recuperar')) ?>"><?= $this->e($this->t('account.forgot')) ?></a>
      <a href="<?= $this->e($this->url('/registro', ['next' => $next ?? null])) ?>"><?= $this->e($this->t('account.no_account')) ?></a>
    </p>
  </form>
</div>
