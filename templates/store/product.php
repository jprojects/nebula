<?php
/** @var \Nebula\Shop\StoreView $this */
$p = $product;
$imgs = fn (array $ids) => array_map(fn ($id) => ['full' => $this->img($id), 'thumb' => $this->img($id, true)], $ids);
$general = $imgs($p['images']);
$colorData = array_map(fn ($c) => [
    'code' => $c['code'],
    'name' => $c['name'],
    'price' => $this->money($c['price']),
    'compare' => $c['compare'] !== null ? $this->money($c['compare']) : null,
    'images' => $c['images'] ? $imgs($c['images']) : $general,
], $p['colors']);
$first = $p['colors'][0] ?? null;
$gallery = $first !== null && $first['images'] ? $imgs($first['images']) : $general;
$price = $first['price'] ?? $p['price'];
$compare = $first !== null ? $first['compare'] : $p['compare'];
?>
<div class="nb-container nb-section">
  <?= $this->partial('partials/breadcrumbs', ['crumbs' => $crumbs, 'current' => $p['name']]) ?>

  <div class="nb-product" data-product data-colors="<?= $this->json($colorData) ?>">
    <div class="nb-gallery" data-gallery>
      <div class="nb-gallery-main">
        <?php if ($gallery): ?>
          <img src="<?= $this->e($gallery[0]['full']) ?>" alt="<?= $this->e($p['name']) ?>" data-gallery-main>
        <?php else: ?>
          <span class="nb-card-placeholder nb-gallery-empty"><?= $this->icon('image') ?></span>
        <?php endif ?>
      </div>
      <div class="nb-gallery-thumbs" data-gallery-thumbs<?= count($gallery) > 1 ? '' : ' hidden' ?>>
        <?php foreach ($gallery as $i => $g): ?>
          <button type="button" class="nb-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-full="<?= $this->e($g['full']) ?>" aria-label="<?= $this->e($this->t('product.image_n', ['n' => $i + 1])) ?>">
            <img src="<?= $this->e($g['thumb']) ?>" alt="" loading="lazy">
          </button>
        <?php endforeach ?>
      </div>
    </div>

    <div class="nb-product-info">
      <h1><?= $this->e($p['name']) ?></h1>
      <?php if ($p['sku']): ?><p class="nb-muted nb-sku"><?= $this->e($this->t('product.sku')) ?> <?= $this->e($p['sku']) ?></p><?php endif ?>

      <div class="nb-price nb-price-lg">
        <span class="nb-price-now" data-price><?= $this->e($this->money($price)) ?></span>
        <s class="nb-price-old" data-compare<?= $compare ? '' : ' hidden' ?>><?= $compare ? $this->e($this->money($compare)) : '' ?></s>
        <?php if ($p['promo']): ?><span class="nb-tag nb-tag-sale"><?= $this->e($p['promo']) ?></span><?php endif ?>
      </div>
      <p class="nb-muted nb-tax-note"><?= $this->e($this->t('product.tax_included')) ?></p>

      <?php if ($p['summary']): ?><p class="nb-summary"><?= $this->e($p['summary']) ?></p><?php endif ?>

      <form class="nb-buy" data-add-to-cart>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <?php if ($p['colors']): ?>
          <fieldset class="nb-colors">
            <legend><?= $this->e($this->t('product.color')) ?>: <strong data-color-name><?= $this->e($first['name']) ?></strong></legend>
            <div class="nb-swatches">
              <?php foreach ($p['colors'] as $i => $c): ?>
                <label class="nb-swatch-option" title="<?= $this->e($c['name']) ?>">
                  <input type="radio" name="color" value="<?= $this->e($c['code']) ?>"<?= $i === 0 ? ' checked' : '' ?> data-color-input>
                  <span class="nb-swatch nb-swatch-lg" style="--swatch:<?= $this->e($c['hex'] ?? '#ccc') ?>"></span>
                  <span class="nb-sr"><?= $this->e($c['name']) ?></span>
                </label>
              <?php endforeach ?>
            </div>
          </fieldset>
        <?php endif ?>

        <?php if ($p['tiers'] && count($p['tiers']) > 1 || ($p['tiers'][0]['min_qty'] ?? 1) > 1): ?>
          <table class="nb-tiers">
            <caption><?= $this->e($this->t('product.tiers')) ?></caption>
            <?php foreach ($p['tiers'] as $tier): ?>
              <tr><th><?= $this->e($this->t('product.tier_from', ['n' => $tier['min_qty']])) ?></th><td><?= $this->e($this->money($tier['price'])) ?></td></tr>
            <?php endforeach ?>
          </table>
        <?php endif ?>

        <div class="nb-buy-row">
          <div class="nb-qty" data-qty>
            <button type="button" class="nb-qty-btn" data-qty-step="-1" aria-label="<?= $this->e($this->t('cart.less')) ?>"><?= $this->icon('minus') ?></button>
            <input type="number" name="qty" value="1" min="1" max="<?= $p['stock'] !== null ? max(1, (int) $p['stock']) : 999 ?>" inputmode="numeric" aria-label="<?= $this->e($this->t('cart.qty')) ?>">
            <button type="button" class="nb-qty-btn" data-qty-step="1" aria-label="<?= $this->e($this->t('cart.more')) ?>"><?= $this->icon('plus') ?></button>
          </div>
          <button class="nb-btn nb-btn-primary nb-btn-lg" type="submit"<?= $p['in_stock'] ? '' : ' disabled' ?>>
            <?= $this->icon('shopping-bag') ?> <?= $this->e($p['in_stock'] ? $this->t('product.add') : $this->t('product.out_of_stock')) ?>
          </button>
        </div>
        <?php if ($p['stock'] !== null && $p['stock'] > 0 && $p['stock'] <= 5): ?>
          <p class="nb-stock-low"><?= $this->icon('clock') ?> <?= $this->e($this->t('product.last_units', ['n' => $p['stock']])) ?></p>
        <?php endif ?>
      </form>

      <?php if ($p['description'] !== ''): ?>
        <section class="nb-prose nb-description">
          <h2><?= $this->e($this->t('product.description')) ?></h2>
          <?= $this->richtext($p['description']) ?>
        </section>
      <?php endif ?>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="nb-section">
      <h2 class="nb-section-title"><?= $this->e($this->t('product.related')) ?></h2>
      <div class="nb-product-grid">
        <?php foreach ($related as $r): ?><?= $this->partial('partials/product_card', ['p' => $r]) ?><?php endforeach ?>
      </div>
    </section>
  <?php endif ?>
</div>
