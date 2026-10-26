<?php
/**
 * Layout de los correos de la tienda: estilos en línea y tablas (lo que respetan Outlook, Gmail y compañía).
 * @var \Nebula\Shop\StoreView $this
 */
$color = (string) (\Nebula\Shop\ShopConfig::get('shop.theme.color') ?? \Nebula\Shop\ShopConfig::get('branding.color') ?? '#4f46e5');
?>
<!doctype html>
<html lang="<?= $this->e($this->locale()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $this->e($this->shopName()) ?></title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;">
  <tr><td align="center" style="padding:24px 12px;">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
      <tr><td style="height:4px;background:<?= $this->e($color) ?>;font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr><td style="padding:20px 28px 8px;"><strong style="font-size:18px;color:<?= $this->e($color) ?>;"><?= $this->e($this->shopName()) ?></strong></td></tr>
      <tr><td style="padding:8px 28px 28px;font-size:14px;line-height:1.6;"><?= $content ?></td></tr>
      <tr><td style="padding:14px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#6b7480;">
        <?= $this->e($this->shopName()) ?> · <?= $this->e($this->t('mail.footer')) ?>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
