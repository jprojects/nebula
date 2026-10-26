<?php
/** @var \Nebula\Shop\StoreView $this */
$pageUrl = fn (int $n) => $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($query + ($n > 1 ? ['page' => $n] : []));
?>
<?php if (!$result['items']): ?>
  <div class="nb-empty-state">
    <?= $this->icon('package-search', 'nb-empty-icon') ?>
    <p><?= $this->e($this->t('listing.empty')) ?></p>
  </div>
<?php else: ?>
  <div class="nb-product-grid">
    <?php foreach ($result['items'] as $p): ?><?= $this->partial('partials/product_card', ['p' => $p]) ?><?php endforeach ?>
  </div>
  <?php if ($result['pages'] > 1): ?>
    <nav class="nb-pagination" aria-label="<?= $this->e($this->t('listing.pages')) ?>">
      <?php if ($result['page'] > 1): ?>
        <a class="nb-page" href="<?= $this->e($pageUrl($result['page'] - 1)) ?>" data-page="<?= $result['page'] - 1 ?>" rel="prev" aria-label="<?= $this->e($this->t('listing.prev')) ?>"><?= $this->icon('chevron-left') ?></a>
      <?php endif ?>
      <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <?php if ($i === 1 || $i === $result['pages'] || abs($i - $result['page']) <= 2): ?>
          <a class="nb-page<?= $i === $result['page'] ? ' is-current' : '' ?>" href="<?= $this->e($pageUrl($i)) ?>" data-page="<?= $i ?>"<?= $i === $result['page'] ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        <?php elseif (abs($i - $result['page']) === 3): ?>
          <span class="nb-page-gap">…</span>
        <?php endif ?>
      <?php endfor ?>
      <?php if ($result['page'] < $result['pages']): ?>
        <a class="nb-page" href="<?= $this->e($pageUrl($result['page'] + 1)) ?>" data-page="<?= $result['page'] + 1 ?>" rel="next" aria-label="<?= $this->e($this->t('listing.next')) ?>"><?= $this->icon('chevron-right') ?></a>
      <?php endif ?>
    </nav>
  <?php endif ?>
<?php endif ?>
