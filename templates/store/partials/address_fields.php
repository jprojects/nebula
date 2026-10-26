<?php
/** @var \Nebula\Shop\StoreView $this */
/** Campos de una dirección con prefijo (bill_ / ship_). $values: valores iniciales sin prefijo. */
$v = fn (string $k) => $this->e($values[$k] ?? '');
?>
<div class="nb-grid-2">
  <label class="nb-field nb-span-2"><span><?= $this->e($this->t('address.name')) ?> *</span>
    <input name="<?= $prefix ?>name" value="<?= $v('name') ?>" autocomplete="<?= $ac ?> name" required></label>
  <label class="nb-field nb-span-2"><span><?= $this->e($this->t('address.address')) ?> *</span>
    <input name="<?= $prefix ?>address" value="<?= $v('address') ?>" autocomplete="<?= $ac ?> street-address" required></label>
  <label class="nb-field"><span><?= $this->e($this->t('address.postcode')) ?> *</span>
    <input name="<?= $prefix ?>postcode" value="<?= $v('postcode') ?>" autocomplete="<?= $ac ?> postal-code" required data-quote-trigger></label>
  <label class="nb-field"><span><?= $this->e($this->t('address.city')) ?> *</span>
    <input name="<?= $prefix ?>city" value="<?= $v('city') ?>" autocomplete="<?= $ac ?> address-level2" required></label>
  <label class="nb-field"><span><?= $this->e($this->t('address.province')) ?></span>
    <input name="<?= $prefix ?>province" value="<?= $v('province') ?>" autocomplete="<?= $ac ?> address-level1"></label>
  <label class="nb-field"><span><?= $this->e($this->t('address.country')) ?> *</span>
    <select name="<?= $prefix ?>country" autocomplete="<?= $ac ?> country" required data-quote-trigger>
      <?php foreach ($countries as $c): ?>
        <option value="<?= $this->e($c['code']) ?>"<?= ($values['country'] ?? '') === $c['code'] ? ' selected' : '' ?>><?= $this->e($c['name']) ?></option>
      <?php endforeach ?>
    </select></label>
</div>
