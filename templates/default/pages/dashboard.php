<?php
/** @var \Nebula\Core\View $this */
$money = fn ($v) => \Nebula\Shop\Money::format((float) $v, $this->locale());
$max = max(1, ...array_column($series, 'total'));
$tones = ['pending' => 'amber', 'paid' => 'green', 'processing' => 'blue', 'shipped' => 'cyan', 'delivered' => 'teal', 'cancelled' => 'gray', 'refunded' => 'purple'];
$ordersUrl = fn (array $f = []) => $this->url('list', ['m' => 'orders'] + ($f ? ['f' => $f] : []));
?>
<div class="nb-kpis">
  <div class="nb-kpi">
    <div class="nb-kpi-label"><?= $this->icon('calendar-check') ?> <?= $this->e($this->t('kpi.today')) ?></div>
    <div class="nb-kpi-value"><?= $this->e($money($kpis['today']['total'])) ?></div>
    <div class="nb-kpi-sub"><?= $this->e($this->t('kpi.orders_n', ['n' => $kpis['today']['n']])) ?></div>
  </div>
  <div class="nb-kpi">
    <div class="nb-kpi-label"><?= $this->icon('calendar-range') ?> <?= $this->e($this->t('kpi.month')) ?></div>
    <div class="nb-kpi-value"><?= $this->e($money($kpis['month']['total'])) ?></div>
    <div class="nb-kpi-sub"><?= $this->e($this->t('kpi.prev_month', ['total' => $money($kpis['prev_month']['total'])])) ?></div>
  </div>
  <a class="nb-kpi" href="<?= $this->e($canOrders ? $ordersUrl(['status' => 'pending']) : '#') ?>">
    <div class="nb-kpi-label"><?= $this->icon('hourglass') ?> <?= $this->e($this->t('kpi.pending_payment')) ?></div>
    <div class="nb-kpi-value"><?= (int) $kpis['pending_payment'] ?></div>
  </a>
  <a class="nb-kpi" href="<?= $this->e($canOrders ? $ordersUrl(['status' => 'paid']) : '#') ?>">
    <div class="nb-kpi-label"><?= $this->icon('package-open') ?> <?= $this->e($this->t('kpi.to_ship')) ?></div>
    <div class="nb-kpi-value"><?= (int) $kpis['to_ship'] ?></div>
  </a>
  <div class="nb-kpi">
    <div class="nb-kpi-label"><?= $this->icon('user-plus') ?> <?= $this->e($this->t('kpi.customers')) ?></div>
    <div class="nb-kpi-value"><?= (int) $kpis['customers'] ?></div>
  </div>
  <a class="nb-kpi" href="<?= $this->e($shopUrl) ?>" target="_blank" rel="noopener">
    <div class="nb-kpi-label"><?= $this->icon('store') ?> <?= $this->e($this->t('kpi.shop')) ?></div>
    <div class="nb-kpi-sub"><?= $this->e($shopUrl) ?></div>
  </a>
</div>

<div class="nb-dash-grid">
  <div>
    <section class="nb-box">
      <h2><?= $this->icon('chart-column') ?> <?= $this->e($this->t('chart.title')) ?></h2>
      <div class="nb-bars" role="img" aria-label="<?= $this->e($this->t('chart.title')) ?>">
        <?php foreach ($series as $d): ?>
          <span class="nb-bar" style="height:<?= round($d['total'] / $max * 100, 1) ?>%" title="<?= $this->e(date('d/m', strtotime($d['date'])) . ' · ' . $money($d['total'])) ?>"></span>
        <?php endforeach ?>
      </div>
      <div class="nb-bars-axis"><span><?= $this->e(date('d/m', strtotime($series[0]['date']))) ?></span><span><?= $this->e($this->t('chart.today')) ?></span></div>
    </section>

    <section class="nb-box">
      <h2><?= $this->icon('shopping-cart') ?> <?= $this->e($this->t('recent.title')) ?></h2>
      <?php if (!$recent): ?>
        <p class="nb-muted"><?= $this->e($this->t('recent.empty')) ?></p>
      <?php else: ?>
        <table class="nb-admin-table">
          <tbody>
            <?php foreach ($recent as $o): ?>
              <tr>
                <td><?= $canOrders ? '<a href="' . $this->e($this->url('edit', ['m' => 'orders', 'id' => $o['id']])) . '">' . $this->e($o['number']) . '</a>' : $this->e($o['number']) ?></td>
                <td><?= $this->e($o['bill_name']) ?></td>
                <td><span class="qs-tag qs-tone-<?= $this->e($tones[$o['status']] ?? 'gray') ?>"><?= $this->e($this->t('orders:status.' . $o['status'])) ?></span></td>
                <td class="nb-muted"><?= $this->e(date('d/m H:i', strtotime((string) $o['created_at']))) ?></td>
                <td class="num"><?= $this->e($money($o['total'])) ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      <?php endif ?>
    </section>
  </div>

  <div>
    <section class="nb-box">
      <h2><?= $this->icon('trophy') ?> <?= $this->e($this->t('top.title')) ?></h2>
      <?php if (!$top): ?>
        <p class="nb-muted"><?= $this->e($this->t('top.empty')) ?></p>
      <?php else: ?>
        <table class="nb-admin-table"><tbody>
          <?php foreach ($top as $p): ?>
            <tr><td><?= $this->e($p['name']) ?></td><td class="num"><?= (int) $p['qty'] ?> u.</td><td class="num"><?= $this->e($money($p['total'])) ?></td></tr>
          <?php endforeach ?>
        </tbody></table>
      <?php endif ?>
    </section>

    <section class="nb-box">
      <h2><?= $this->icon('triangle-alert') ?> <?= $this->e($this->t('stock.title')) ?></h2>
      <?php if (!$lowStock): ?>
        <p class="nb-muted"><?= $this->e($this->t('stock.empty')) ?></p>
      <?php else: ?>
        <table class="nb-admin-table"><tbody>
          <?php foreach ($lowStock as $p): ?>
            <tr>
              <td><?= $canProducts ? '<a href="' . $this->e($this->url('edit', ['m' => 'products', 'id' => $p['id']])) . '">' . $this->e($p['name']) . '</a>' : $this->e($p['name']) ?></td>
              <td class="nb-muted"><?= $this->e($p['sku'] ?? '') ?></td>
              <td class="num"><span class="qs-tag qs-tone-<?= (int) $p['stock'] <= 0 ? 'red' : 'amber' ?>"><?= (int) $p['stock'] ?></span></td>
            </tr>
          <?php endforeach ?>
        </tbody></table>
      <?php endif ?>
    </section>
  </div>
</div>
