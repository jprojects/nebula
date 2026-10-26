<?php /** @var \Nebula\Shop\StoreView $this */ ?>
<ul class="nb-summary-lines">
  <?php foreach ($quote['items'] as $item): ?>
    <li>
      <span class="nb-summary-img">
        <?php if ($item['image']): ?><img src="<?= $this->e($this->img($item['image'], true)) ?>" alt=""><?php endif ?>
        <span class="nb-badge"><?= (int) $item['qty'] ?></span>
      </span>
      <span class="nb-summary-name"><?= $this->e($item['name']) ?><?php if ($item['color_name']): ?><small><?= $this->e($item['color_name']) ?></small><?php endif ?></span>
      <span><?= $this->e($this->money($item['total'])) ?></span>
    </li>
  <?php endforeach ?>
</ul>
<?= $this->partial('partials/totals', ['quote' => $quote]) ?>
