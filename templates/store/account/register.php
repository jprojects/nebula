<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section nb-auth">
  <form class="nb-panel" data-ajax-form action="<?= $this->e($this->url('/api/account/register')) ?>">
    <h1><?= $this->e($this->t('account.register')) ?></h1>
    <input type="hidden" name="next" value="<?= $this->e($next ?? '') ?>">
    <label class="nb-field"><span><?= $this->e($this->t('address.name')) ?> *</span>
      <input name="name" autocomplete="name" required></label>
    <label class="nb-field"><span><?= $this->e($this->t('checkout.email')) ?> *</span>
      <input type="email" name="email" autocomplete="email" required></label>
    <label class="nb-field"><span><?= $this->e($this->t('account.password')) ?> *</span>
      <input type="password" name="password" autocomplete="new-password" minlength="8" required>
      <small class="nb-muted"><?= $this->e($this->t('account.password_hint')) ?></small></label>
    <div class="nb-grid-2">
      <label class="nb-field"><span><?= $this->e($this->t('address.company')) ?></span><input name="company" autocomplete="organization"></label>
      <label class="nb-field"><span><?= $this->e($this->t('address.vat_id')) ?></span><input name="vat_id"></label>
    </div>
    <label class="nb-check"><input type="checkbox" name="accept_terms" value="1" required><span><?= $this->e($this->t('account.accept_terms')) ?></span></label>
    <label class="nb-check"><input type="checkbox" name="accepts_marketing" value="1"><span><?= $this->e($this->t('checkout.marketing')) ?></span></label>
    <div class="nb-form-error" data-form-error hidden></div>
    <button class="nb-btn nb-btn-primary nb-btn-block" type="submit"><?= $this->e($this->t('account.register')) ?></button>
    <p class="nb-auth-links"><a href="<?= $this->e($this->url('/entrar', ['next' => $next ?? null])) ?>"><?= $this->e($this->t('account.have_account')) ?></a></p>
  </form>
</div>
