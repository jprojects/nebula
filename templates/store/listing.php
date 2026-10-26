<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<div class="nb-container nb-section">
  <?= $this->partial('partials/breadcrumbs', ['crumbs' => $crumbs, 'current' => $category === null ? $this->t('search.title') : null]) ?>

  <header class="nb-listing-head">
    <div>
      <h1><?= $this->e($category['name'] ?? ($q !== null && $q !== '' ? $this->t('search.title_q', ['q' => $q]) : $this->t('search.title'))) ?></h1>
      <p class="nb-muted" data-listing-count><?= $this->e($this->t('listing.count', ['n' => $result['total']])) ?></p>
    </div>
    <label class="nb-sort">
      <span><?= $this->e($this->t('listing.sort')) ?></span>
      <select data-listing-sort>
        <?php foreach (\Nebula\Shop\Catalog::SORTS as $s): ?>
          <option value="<?= $this->e($s) ?>"<?= $s === $sort ? ' selected' : '' ?>><?= $this->e($this->t('listing.sort_' . $s)) ?></option>
        <?php endforeach ?>
      </select>
    </label>
  </header>

  <?php if (!empty($category['description'])): ?>
    <div class="nb-prose nb-category-desc"><?= nl2br($this->e($category['description'])) ?></div>
  <?php endif ?>

  <?php if ($subcategories): ?>
    <nav class="nb-chips" aria-label="<?= $this->e($this->t('layout.categories')) ?>">
      <?php foreach ($subcategories as $sub): ?>
        <a class="nb-chip" href="<?= $this->e($this->urls->category($sub['slug'])) ?>"><?= $this->e($sub['name']) ?></a>
      <?php endforeach ?>
    </nav>
  <?php endif ?>

  <div data-listing data-listing-url="<?= $this->e($baseUrl) ?>" data-listing-query="<?= $this->json($query) ?>">
    <?= $this->partial('partials/product_grid', get_defined_vars()) ?>
  </div>
</div>
