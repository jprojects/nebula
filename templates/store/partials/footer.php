<?php
/**
 * @var \Nebula\Shop\StoreView $this
 * Pie de la tienda (la incluye layout.php). Un tema la sustituye con su propio partials/footer.php; los atributos
 * data-* son los que usa store.js.
 */
$shopName = $this->shopName();
?>
<footer class="nb-footer">
  <div class="nb-container nb-footer-row">
    <div>
      <strong><?= $this->e($shopName) ?></strong>
      <p class="nb-muted">© <?= date('Y') ?></p>
    </div>
    <?php if ($footerLinks): ?>
      <ul class="nb-footer-links">
        <?php foreach ($footerLinks as $label => $href): ?>
          <li><a href="<?= $this->e(str_starts_with((string) $href, 'http') ? $href : $this->url((string) $href)) ?>"><?= $this->e($label) ?></a></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
    <div class="nb-pay-icons nb-muted"><?= $this->icon('lock') ?> <?= $this->e($this->t('layout.secure')) ?></div>
  </div>
</footer>
