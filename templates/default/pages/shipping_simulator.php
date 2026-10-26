<?php
/** @var \Nebula\Core\View $this */
$money = fn ($v) => \Nebula\Shop\Money::format((float) $v, $this->locale());
?>
<section class="nb-box">
  <form class="nb-sim-form" method="get" action="index.php">
    <input type="hidden" name="r" value="page">
    <input type="hidden" name="m" value="shipping_simulator">
    <label><?= $this->e($this->t('form.country')) ?>
      <select name="country" class="qs-input">
        <?php foreach ($countries as $c): ?>
          <option value="<?= $this->e($c['code']) ?>"<?= $c['code'] === $input['country'] ? ' selected' : '' ?>><?= $this->e($c['name']) ?></option>
        <?php endforeach ?>
      </select></label>
    <label><?= $this->e($this->t('form.postcode')) ?><input class="qs-input" name="postcode" value="<?= $this->e($input['postcode']) ?>"></label>
    <label><?= $this->e($this->t('form.weight')) ?><input class="qs-input" name="weight" type="number" step="0.001" min="0" value="<?= $this->e($input['weight']) ?>"></label>
    <label><?= $this->e($this->t('form.subtotal')) ?><input class="qs-input" name="subtotal" type="number" step="0.01" min="0" value="<?= $this->e($input['subtotal']) ?>"></label>
    <label><?= $this->e($this->t('form.group')) ?>
      <select name="group" class="qs-input">
        <option value="0"><?= $this->e($this->t('form.default_group')) ?></option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['id'] ?>"<?= (int) $g['id'] === $input['group'] ? ' selected' : '' ?>><?= $this->e($g['name']) ?></option>
        <?php endforeach ?>
      </select></label>
    <button class="qs-btn qs-btn-primary" type="submit"><?= $this->icon('calculator') ?> <?= $this->e($this->t('form.submit')) ?></button>
  </form>
</section>

<?php if ($ran): ?>
<section class="nb-box">
  <h2><?= $this->icon('truck') ?> <?= $this->e($this->t('result.title')) ?></h2>
  <?php if (!$options): ?>
    <p class="nb-muted"><?= $this->e($this->t('result.none')) ?></p>
  <?php else: ?>
    <table class="nb-admin-table">
      <thead><tr><th><?= $this->e($this->t('result.method')) ?></th><th><?= $this->e($this->t('result.delivery')) ?></th><th class="num"><?= $this->e($this->t('result.price')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($options as $o): ?>
          <tr><td><a href="<?= $this->e($this->url('edit', ['m' => 'shipping_methods', 'id' => $o['id']])) ?>"><?= $this->e($o['name']) ?></a></td>
            <td><?= $this->e($o['delivery'] ?? '') ?></td><td class="num"><?= $this->e($o['price'] > 0 ? $money($o['price']) : $this->t('result.free')) ?></td></tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</section>
<?php endif ?>
