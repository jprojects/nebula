<?php
/** @var \Nebula\Shop\StoreView $this */
$icons = ['transfer' => 'landmark', 'redsys' => 'credit-card', 'paypal' => 'wallet'];
?>
<?php if (!$quote['payment_options']): ?>
  <p class="nb-alert nb-alert-warning" data-error-for="payment_id"><?= $this->e($this->t('checkout.no_payment')) ?></p>
<?php else: ?>
  <div class="nb-options" data-error-for="payment_id">
    <?php foreach ($quote['payment_options'] as $o): ?>
      <label class="nb-option">
        <input type="radio" name="payment_id" value="<?= (int) $o['id'] ?>"<?= ($quote['payment']['id'] ?? null) === $o['id'] ? ' checked' : '' ?> data-quote-trigger>
        <span class="nb-option-body">
          <span class="nb-option-title"><?= $this->icon($icons[$o['gateway']] ?? 'circle-dollar-sign') ?> <?= $this->e($o['name']) ?></span>
          <?php if ($o['description']): ?><span class="nb-option-desc"><?= $this->e($o['description']) ?></span><?php endif ?>
        </span>
        <?php if ($o['fee'] > 0): ?><span class="nb-option-price">+<?= $this->e($this->money($o['fee'])) ?></span><?php endif ?>
      </label>
    <?php endforeach ?>
  </div>
<?php endif ?>
