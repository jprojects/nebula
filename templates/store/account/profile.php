<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section">
  <h1><?= $this->e($this->t('account.profile')) ?></h1>
  <?= $this->partial('account/nav', ['active' => 'profile']) ?>
  <div class="nb-grid-2 nb-align-start">
    <form class="nb-panel" data-ajax-form action="<?= $this->e($this->url('/api/account/profile')) ?>">
      <h2><?= $this->e($this->t('account.details')) ?></h2>
      <label class="nb-field"><span><?= $this->e($this->t('checkout.email')) ?></span><input value="<?= $this->e($customer['email']) ?>" disabled></label>
      <label class="nb-field"><span><?= $this->e($this->t('address.name')) ?> *</span><input name="name" value="<?= $this->e($customer['name']) ?>" required></label>
      <div class="nb-grid-2">
        <label class="nb-field"><span><?= $this->e($this->t('checkout.phone')) ?></span><input name="phone" value="<?= $this->e($customer['phone'] ?? '') ?>"></label>
        <label class="nb-field"><span><?= $this->e($this->t('address.vat_id')) ?></span><input name="vat_id" value="<?= $this->e($customer['vat_id'] ?? '') ?>"></label>
      </div>
      <label class="nb-field"><span><?= $this->e($this->t('address.company')) ?></span><input name="company" value="<?= $this->e($customer['company'] ?? '') ?>"></label>
      <label class="nb-check"><input type="checkbox" name="accepts_marketing" value="1"<?= $customer['accepts_marketing'] ? ' checked' : '' ?>><span><?= $this->e($this->t('checkout.marketing')) ?></span></label>
      <h3><?= $this->e($this->t('account.change_password')) ?></h3>
      <div class="nb-grid-2">
        <label class="nb-field"><span><?= $this->e($this->t('account.current_password')) ?></span><input type="password" name="current_password" autocomplete="current-password"></label>
        <label class="nb-field"><span><?= $this->e($this->t('account.new_password')) ?></span><input type="password" name="password" autocomplete="new-password" minlength="8"></label>
      </div>
      <div class="nb-form-error" data-form-error hidden></div>
      <button class="nb-btn nb-btn-primary" type="submit"><?= $this->e($this->t('account.save')) ?></button>
    </form>

    <form class="nb-panel" data-addresses-form action="<?= $this->e($this->url('/api/account/addresses')) ?>">
      <h2><?= $this->e($this->t('account.addresses')) ?></h2>
      <div data-address-list>
        <?php foreach (array_merge($addresses, [[]]) as $i => $a): ?>
          <fieldset class="nb-address-item" data-address-item<?= $a === [] ? ' data-template hidden disabled' : '' ?>>
            <div class="nb-address-head">
              <input name="label" value="<?= $this->e($a['label'] ?? '') ?>" placeholder="<?= $this->e($this->t('address.label')) ?>" aria-label="<?= $this->e($this->t('address.label')) ?>">
              <button type="button" class="nb-link-btn" data-address-remove aria-label="<?= $this->e($this->t('cart.remove')) ?>"><?= $this->icon('trash-2') ?></button>
            </div>
            <div class="nb-grid-2">
              <?php foreach (['name' => 'nb-span-2', 'address' => 'nb-span-2', 'postcode' => '', 'city' => '', 'province' => '', 'phone' => ''] as $k => $span): ?>
                <label class="nb-field <?= $span ?>"><span><?= $this->e($this->t('address.' . $k)) ?></span><input name="<?= $k ?>" value="<?= $this->e($a[$k] ?? '') ?>"></label>
              <?php endforeach ?>
              <label class="nb-field nb-span-2"><span><?= $this->e($this->t('address.country')) ?></span>
                <select name="country">
                  <?php foreach ($countries as $c): ?>
                    <option value="<?= $this->e($c['code']) ?>"<?= ($a['country'] ?? 'ES') === $c['code'] ? ' selected' : '' ?>><?= $this->e($c['name']) ?></option>
                  <?php endforeach ?>
                </select></label>
            </div>
          </fieldset>
        <?php endforeach ?>
      </div>
      <button type="button" class="nb-btn nb-btn-ghost" data-address-add><?= $this->icon('plus') ?> <?= $this->e($this->t('account.add_address')) ?></button>
      <button class="nb-btn nb-btn-primary" type="submit"><?= $this->e($this->t('account.save')) ?></button>
    </form>
  </div>
</div>
