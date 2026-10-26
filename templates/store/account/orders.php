<?php
/** @var \Nebula\Shop\StoreView $this */
$tone = ['pending' => 'amber', 'paid' => 'green', 'processing' => 'blue', 'shipped' => 'blue', 'delivered' => 'green', 'cancelled' => 'gray', 'refunded' => 'gray'];
?>
<div class="nb-container nb-section">
  <h1><?= $this->e($this->t('account.hello', ['name' => explode(' ', (string) $customer['name'])[0]])) ?></h1>
  <?= $this->partial('account/nav', ['active' => 'orders']) ?>
  <?php if (!$orders): ?>
    <div class="nb-empty-state">
      <?= $this->icon('package', 'nb-empty-icon') ?>
      <p><?= $this->e($this->t('account.no_orders')) ?></p>
      <a class="nb-btn nb-btn-primary" href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('cart.continue')) ?></a>
    </div>
  <?php else: ?>
    <div class="nb-panel nb-table-wrap">
      <table class="nb-table">
        <thead><tr><th><?= $this->e($this->t('account.order')) ?></th><th><?= $this->e($this->t('account.date')) ?></th><th><?= $this->e($this->t('account.status')) ?></th><th class="nb-num"><?= $this->e($this->t('totals.total')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><a href="<?= $this->e($this->urls->order((string) $o['number'], (string) $o['token'])) ?>"><?= $this->e($o['number']) ?></a></td>
              <td><?= $this->e($this->date($o['created_at'])) ?></td>
              <td><span class="nb-status nb-tone-<?= $this->e($tone[$o['status']] ?? 'gray') ?>"><?= $this->e($this->t('status.' . $o['status'])) ?></span></td>
              <td class="nb-num"><?= $this->e($this->money((float) $o['total'])) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
