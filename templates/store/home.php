<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<section class="nb-hero">
  <div class="nb-container">
    <h1><?= $this->e($this->t('home.title', ['shop' => $this->shopName()])) ?></h1>
    <p><?= $this->e($this->t('home.intro')) ?></p>
    <?php if ($categories): ?>
      <a class="nb-btn nb-btn-primary" href="<?= $this->e($this->urls->category($categories[0]['slug'])) ?>"><?= $this->e($this->t('home.cta')) ?></a>
    <?php endif ?>
  </div>
</section>

<?php if ($categories): ?>
<section class="nb-container nb-section">
  <h2 class="nb-section-title"><?= $this->e($this->t('home.categories')) ?></h2>
  <div class="nb-category-grid">
    <?php foreach ($categories as $cat): ?>
      <a class="nb-category-card<?= $cat['image'] ? '' : ' is-empty' ?>" href="<?= $this->e($this->urls->category($cat['slug'])) ?>">
        <?php if ($cat['image']): ?>
          <img src="<?= $this->e($this->img((int) $cat['image'], true)) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="nb-category-placeholder"><?= $this->icon('shapes') ?></span>
        <?php endif ?>
        <span class="nb-category-name"><?= $this->e($cat['name']) ?></span>
      </a>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<?php if ($featured): ?>
<section class="nb-container nb-section">
  <h2 class="nb-section-title"><?= $this->e($this->t('home.featured')) ?></h2>
  <div class="nb-product-grid">
    <?php foreach ($featured as $p): ?><?= $this->partial('partials/product_card', ['p' => $p]) ?><?php endforeach ?>
  </div>
</section>
<?php endif ?>
