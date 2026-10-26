<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section nb-auth">
  <?php if (!$valid): ?>
    <div class="nb-panel">
      <h1><?= $this->e($this->t('account.reset')) ?></h1>
      <p class="nb-alert nb-alert-warning"><?= $this->e($this->t('errors.reset_invalid')) ?></p>
      <a class="nb-btn" href="<?= $this->e($this->url('/recuperar')) ?>"><?= $this->e($this->t('account.send_link')) ?></a>
    </div>
  <?php else: ?>
    <form class="nb-panel" data-ajax-form action="<?= $this->e($this->url('/api/account/reset')) ?>">
      <h1><?= $this->e($this->t('account.reset')) ?></h1>
      <input type="hidden" name="token" value="<?= $this->e($token) ?>">
      <label class="nb-field"><span><?= $this->e($this->t('account.new_password')) ?></span>
        <input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus></label>
      <div class="nb-form-error" data-form-error hidden></div>
      <button class="nb-btn nb-btn-primary nb-btn-block" type="submit"><?= $this->e($this->t('account.save_password')) ?></button>
    </form>
  <?php endif ?>
</div>
