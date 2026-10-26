<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-pay-redirect">
  <?= $this->icon('lock', 'nb-thanks-icon') ?>
  <h1><?= $this->e($this->t('pay.redirecting')) ?></h1>
  <p class="nb-muted"><?= $this->e($this->t('pay.redirecting_text', ['total' => $this->money((float) $order['total'])])) ?></p>
  <form method="post" action="<?= $this->e($action->url) ?>" data-autosubmit>
    <?php foreach ($action->fields as $name => $value): ?>
      <input type="hidden" name="<?= $this->e($name) ?>" value="<?= $this->e($value) ?>">
    <?php endforeach ?>
    <button class="nb-btn nb-btn-primary nb-btn-lg" type="submit"><?= $this->e($this->t('pay.continue')) ?></button>
  </form>
</div>
