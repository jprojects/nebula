<?php
/** @var \Nebula\Core\View $this */
/** Intentos de pago de un pedido (pasarela, referencia, estado). */
?>
<?php if (!$value): ?>
  <p class="nb-muted"><?= $this->e($this->t('payments.none')) ?></p>
<?php else: ?>
  <table class="nb-admin-table">
    <thead><tr><th><?= $this->e($this->t('payments.date')) ?></th><th><?= $this->e($this->t('payments.gateway')) ?></th><th><?= $this->e($this->t('payments.reference')) ?></th>
      <th><?= $this->e($this->t('payments.status')) ?></th><th><?= $this->e($this->t('payments.transaction')) ?></th><th class="num"><?= $this->e($this->t('payments.amount')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($value as $p): ?>
        <tr>
          <td><?= $this->e(date('d/m/Y H:i', strtotime((string) $p['created_at']))) ?></td>
          <td><?= $this->e($this->t('gateway.' . $p['gateway'])) ?></td>
          <td><code><?= $this->e($p['reference']) ?></code></td>
          <td><span class="qs-tag qs-tone-<?= $this->e(['paid' => 'green', 'failed' => 'red', 'cancelled' => 'gray'][$p['status']] ?? 'amber') ?>"><?= $this->e($this->t('payment.' . $p['status'])) ?></span>
            <?php if ($p['message']): ?><br><small class="nb-muted"><?= $this->e($p['message']) ?></small><?php endif ?></td>
          <td><?= $this->e($p['transaction_id'] ?? '') ?></td>
          <td class="num"><?= $this->e(\Nebula\Shop\Money::format((float) $p['amount'], $this->locale())) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>
