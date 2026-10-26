<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<?php if (!$quote['shipping_options']): ?>
  <p class="nb-alert nb-alert-warning" data-error-for="shipping_id"><?= $this->e($this->t('checkout.no_shipping')) ?></p>
<?php else: ?>
  <div class="nb-options" data-error-for="shipping_id">
    <?php foreach ($quote['shipping_options'] as $o): ?>
      <label class="nb-option">
        <input type="radio" name="shipping_id" value="<?= (int) $o['id'] ?>"<?= ($quote['shipping']['id'] ?? null) === $o['id'] ? ' checked' : '' ?> data-quote-trigger data-pickup="<?= $o['pickup'] ? '1' : '0' ?>">
        <span class="nb-option-body">
          <span class="nb-option-title"><?= $this->icon($o['pickup'] ? 'store' : 'truck') ?> <?= $this->e($o['name']) ?></span>
          <?php if ($o['description'] || $o['delivery']): ?>
            <span class="nb-option-desc"><?= $this->e(implode(' · ', array_filter([$o['delivery'], $o['description']]))) ?></span>
          <?php endif ?>
        </span>
        <span class="nb-option-price"><?= $this->e($o['price'] > 0 ? $this->money($o['price']) : $this->t('totals.free')) ?></span>
      </label>
    <?php endforeach ?>
  </div>
<?php endif ?>
