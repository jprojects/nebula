<?php
/** @var \Nebula\Core\View $this */
/** Historial de estados de un pedido. */
?>
<ol class="nb-history">
  <?php foreach ($value as $h): ?>
    <li>
      <strong><?= $this->e($this->t('status.' . $h['status'])) ?></strong>
      <span class="nb-muted"><?= $this->e(date('d/m/Y H:i', strtotime((string) $h['created_at']))) ?> · <?= $this->e($h['user_name'] ?? $this->t('history.system')) ?></span>
      <?php if ($h['notified']): ?><span class="qs-tag qs-tone-blue"><?= $this->e($this->t('history.notified')) ?></span><?php endif ?>
      <?php if ($h['note']): ?><div><?= nl2br($this->e($h['note'])) ?></div><?php endif ?>
    </li>
  <?php endforeach ?>
</ol>
