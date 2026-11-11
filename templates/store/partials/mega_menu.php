<?php
/**
 * @var \Nebula\Shop\StoreView $this
 * Megamenú de categorías (lo incluye partials/header.php; un tema con cabecera propia lo puede incluir igual).
 * En escritorio: las categorías principales a la izquierda y, al pasar por encima o con el teclado, sus
 * subcategorías (y las de tercer nivel) en columnas. En el móvil es un panel a pantalla completa en el que se entra
 * en cada categoría y se vuelve atrás. Lo mueve store.js con data-mega*.
 */
$roots = $menu ?? [];
$limit = 6; // subcategorías de tercer nivel que se ven por grupo; el resto, con «Veure-ho tot»
// Al abrir, la primera que tiene subcategorías (si no, la primera)
$active = 0;
foreach ($roots as $i => $root) {
    if ($root['children']) {
        $active = $i;
        break;
    }
}
?>
<div class="nb-mega" id="nb-mega" data-mega hidden>
  <div class="nb-mega-backdrop" data-mega-close></div>
  <div class="nb-mega-dialog" role="dialog" aria-modal="false" aria-label="<?= $this->e($this->t('layout.all_categories')) ?>">
    <div class="nb-mega-head">
      <button class="nb-icon-btn nb-mega-back" type="button" data-mega-back hidden><?= $this->icon('chevron-left') ?><span><?= $this->e($this->t('layout.back')) ?></span></button>
      <strong class="nb-mega-title" data-mega-title><?= $this->e($this->t('layout.all_categories')) ?></strong>
      <button class="nb-icon-btn" type="button" data-mega-close aria-label="<?= $this->e($this->t('layout.close')) ?>"><?= $this->icon('x') ?></button>
    </div>
    <div class="nb-container nb-mega-in">
      <ul class="nb-mega-roots">
        <?php foreach ($roots as $i => $root): ?>
          <li>
            <a class="nb-mega-root<?= $i === $active ? ' is-active' : '' ?>" href="<?= $this->e($this->urls->category($root['slug'])) ?>"
               data-mega-root="<?= (int) $root['id'] ?>"<?= $root['children'] ? ' data-mega-has' : '' ?><?= ($path ?? '') === '/c/' . $root['slug'] ? ' aria-current="page"' : '' ?>>
              <span><?= $this->e($root['name']) ?></span>
              <?php if ($root['children']): ?><?= $this->icon('chevron-right') ?><?php endif ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
      <div class="nb-mega-panels">
        <?php foreach ($roots as $i => $root): ?>
          <section class="nb-mega-panel" data-mega-panel="<?= (int) $root['id'] ?>"<?= $i === $active ? '' : ' hidden' ?>>
            <header class="nb-mega-panel-head">
              <h2><?= $this->e($root['name']) ?></h2>
              <a class="nb-mega-all" href="<?= $this->e($this->urls->category($root['slug'])) ?>"><?= $this->e($this->t('layout.see_all')) ?> <?= $this->icon('arrow-right') ?></a>
            </header>
            <?php if ($root['children']): ?>
              <div class="nb-mega-cols">
                <?php foreach ($root['children'] as $sub): ?>
                  <div class="nb-mega-group">
                    <a class="nb-mega-sub" href="<?= $this->e($this->urls->category($sub['slug'])) ?>"><?= $this->e($sub['name']) ?></a>
                    <?php if ($sub['children']): ?>
                      <ul>
                        <?php foreach (array_slice($sub['children'], 0, $limit) as $leaf): ?>
                          <li><a href="<?= $this->e($this->urls->category($leaf['slug'])) ?>"><?= $this->e($leaf['name']) ?></a></li>
                        <?php endforeach ?>
                        <?php if (count($sub['children']) > $limit): ?>
                          <li><a class="nb-mega-more" href="<?= $this->e($this->urls->category($sub['slug'])) ?>"><?= $this->e($this->t('layout.more', ['n' => count($sub['children']) - $limit])) ?></a></li>
                        <?php endif ?>
                      </ul>
                    <?php endif ?>
                  </div>
                <?php endforeach ?>
              </div>
            <?php else: ?>
              <div class="nb-mega-leaf">
                <?php if (!empty($root['description'])): ?><p class="nb-muted"><?= $this->e(mb_strimwidth((string) $root['description'], 0, 240, '…')) ?></p><?php endif ?>
                <a class="nb-btn nb-btn-primary" href="<?= $this->e($this->urls->category($root['slug'])) ?>"><?= $this->e($this->t('layout.see_products')) ?> <?= $this->icon('arrow-right') ?></a>
              </div>
            <?php endif ?>
          </section>
        <?php endforeach ?>
      </div>
    </div>
  </div>
</div>
