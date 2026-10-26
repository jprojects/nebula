# Nebula

Tienda online independiente: catálogo, tarifas por grupo de clientes, portes por reglas, pagos con Redsys, PayPal y
transferencia, promociones y pedidos. PHP 8.1+, MySQL/MariaDB y JavaScript sin dependencias.

- **Tienda** (`public/index.php`): pública, con URLs limpias y AJAX para todo lo que no necesita recargar.
- **Gestión** (`public/admin/`): el núcleo de [Quasar](../quasar) 0.7.0 (tablas y formularios por JSON, usuarios,
  grupos, permisos, exportaciones, correo, API remota) con los módulos de la tienda. Su referencia completa está en
  [docs/gestion.md](docs/gestion.md).
- Se gestiona desde **Cosmo** como Quasar y Neutron: misma API remota (`?r=remote`), `"app": "nebula"`.

## Instalación

```bash
# 1. Credenciales (solo las claves que cambian)
cat > config/config.local.php <<'PHP'
<?php return [
    'db' => ['user' => 'nebula', 'pass' => 'secreto'],
    'base_url' => 'https://tienda.example.com',      // URL pública de la tienda (correos y pasarelas)
    'mail' => ['host' => 'smtp…', 'username' => '…', 'password' => '…', 'from' => ['address' => 'tienda@…', 'name' => 'Mi tienda']],
];
PHP

# 2. BD, tablas, administrador y tienda de ejemplo
php bin/install.php                 # --no-demo: sin la tienda de ejemplo (sí crea países y el grupo por defecto)

# 3. Arranca (en producción, DocumentRoot → public/)
php -S localhost:8000 -t public public/index.php
```

- Tienda: `http://localhost:8000/` · Gestión: `http://localhost:8000/admin/`
- La tienda de ejemplo es `database/demo.sql` (todos los INSERT) más sus imágenes en `database/demo/`, que el
  instalador copia a `storage/uploads/demo/`. A mano: `mysql nebula < database/demo.sql` y
  `cp -r database/demo storage/uploads/`.
- Con la tienda de ejemplo: clientes `cliente@nebula.test` (particular), `pro@nebula.test` (profesional) y
  `distribuidor@nebula.test`, todos con la contraseña `demo1234`; cupones `BIENVENIDA10` y `5EUROS`.
- Apache: el `.htaccess` de `public/` manda a `index.php` todo lo que no es un fichero. Sin reescritura de URLs,
  `shop.pretty_urls => false` y las URLs son `index.php/p/producto`.
- Cron cada hora: `php bin/cron.php --quiet` (cancela los pedidos sin pagar caducados y devuelve sus unidades).

Requiere `pdo_mysql`, `mbstring`, `json`, `curl` y `openssl` (pasarelas); `gd` para miniaturas e imágenes de ejemplo;
`intl` para formatear importes según el idioma (si no, formato español).

## Estructura

```
public/index.php          tienda (y API remota en ?r=remote)
public/admin/index.php    gestión
src/Shop/                 servicios de la tienda (sin dependencias de la gestión salvo BD, correo y archivos)
  Shop.php                controlador frontal: rutas, sesión, idioma, recursos e imágenes públicas
  Controllers/            Catalog, Cart, Checkout (pedido y pago), Account
  Catalog · Pricing · Promotions · Shipping · Cart · CartCalculator · OrderService · Customers · ShopMailer
  Payments/               Gateway (base), TransferGateway, RedsysGateway, PaypalGateway, PaymentService
  Services.php            crea los servicios (tienda, hooks de la gestión y cron)
src/Hooks/                lógica de los módulos de la tienda en la gestión
src/Pages/                Panel y Simulador de portes
modules/                  módulos de la gestión (orders, products, categories, customers, promotions, …)
templates/store/          plantillas, store.css y store.js de la tienda; emails/ los correos
templates/default/        plantilla de la gestión (fields/order_*.php: bloques de la ficha del pedido)
lang/{ca,es,en}/store.json  textos de la tienda; el resto, de la gestión
database/schema.sql       tablas de la gestión · database/shop.sql tablas de la tienda
database/demo.sql         tienda de ejemplo (INSERT) · database/demo/ sus imágenes
bin/                      install.php · cron.php · media-clean.php · mail-test.php · notifications.php
docs/gestion.md           referencia del motor de la gestión
```

## Configuración de la tienda

En `config.php` están todas las claves con su valor por defecto; en `config.local.php` solo las que cambian.

```php
'shop' => [
    'name' => 'Mi tienda',            // por defecto branding.company / app_name
    'locale' => 'ca',                 // idioma por defecto; el visitante lo cambia (/idioma/es) y se recuerda
    'locales' => ['ca' => 'Català', 'es' => 'Español', 'en' => 'English'],
    'currency' => 'EUR',
    'pretty_urls' => true,
    'page_size' => 12,
    'order_prefix' => '',             // número de pedido: {prefijo}2026-000001
    'guest_checkout' => true,         // false: hay que crear cuenta para comprar
    'default_country' => 'ES',
    'shipping_tax_rate' => 21,        // IVA de portes y recargos
    'notify_email' => null,           // copia de los pedidos (por defecto mail.from.address)
    'pending_ttl_hours' => 48,        // cron: cancela los pedidos con tarjeta/PayPal sin pagar
    'transfer_ttl_days' => 0,         // ídem transferencias (0 = nunca)
    'low_stock' => 3,                 // aviso en el Panel
    'theme' => ['color' => '#0f766e', 'logo' => 'storage/branding/logo.svg'],   // por defecto, los de branding
    'footer_links' => ['Aviso legal' => '/legal', 'Condiciones' => 'https://…'],
],
```

**Precios con IVA incluido.** Cada producto tiene su tipo de IVA; el pedido guarda el desglose por tipo (los portes y
los recargos van al `shipping_tax_rate`).

## Catálogo

**Categorías** (Tienda → Categorías): árbol con `Dentro de`, orden, visible, en el menú, imagen y SEO. El listado de
una categoría incluye los productos de sus subcategorías.

**Productos** (Tienda → Productos):

- **Varias categorías** por producto (select múltiple). Se copian a `product_categories` al guardar.
- **Imágenes** generales y **colores**: cada color tiene nombre, muestra, referencia, suplemento de precio y sus
  propias imágenes. En la ficha de la tienda, al elegir un color cambian las fotos y el precio sin recargar. El código
  de cada color (oculto) no cambia aunque se renombre: lo usan el carrito y los pedidos.
- **Precio** general con IVA, **precio anterior** (tachado), IVA %, peso (portes) y existencias (vacío = sin control).
  Las existencias son del producto (compartidas por sus colores).
- **Precios por grupo**: filas `grupo · desde (u.) · precio`. Para un grupo se usa la fila de mayor cantidad mínima
  que alcanza el carrito (sumando todos los colores del producto); sin filas para el grupo, el precio general con el
  **descuento general del grupo**. En la ficha se muestra la tabla de precios por cantidad. Se copian a
  `product_prices` (lo que consulta la tienda, también para ordenar por precio).
- Botones: **Duplicar** (copia sin publicar) y **Ver en la tienda**.

## Grupos de clientes y tarifas

Configuración → Grupos y tarifas. Cada cliente tiene un grupo (rol); los visitantes y los clientes nuevos, el
marcado **Por defecto** (no se puede borrar). El grupo decide:

| | |
|---|---|
| Precios | filas del producto para el grupo, o precio general − `discount_percent` del grupo |
| Envíos | formas de envío con `Grupos de clientes` (vacío = todos) |
| Pagos | ídem en las formas de pago (p. ej. «Recibo a 30 días» solo para distribuidores) |
| Promociones | ídem |

Para pasar un cliente a otra tarifa, cambia su grupo en Tienda → Clientes.

## Portes

Configuración → Formas de envío. Cada forma tiene una lista de **reglas**; para un carrito se usa la **primera que
encaja**, en orden. Si ninguna encaja, esa forma no se ofrece.

| Campo | |
|---|---|
| Países | `ES` o `ES,PT,AD`; vacío = cualquiera |
| Códigos postales | `07*` (empieza por), `08000-08999` (intervalo), `25001` (exacto), `!35*` (excepto). Separados por comas; vacío = cualquiera |
| Peso desde / hasta | kg del pedido; desde incluido, hasta excluido |
| Importe desde / hasta | productos después de descuentos |
| Precio | con IVA |
| + por kg | por cada kg o fracción por encima de «Peso desde» |
| Gratis desde | a partir de este importe, 0 |

Ejemplo (tienda de ejemplo): península `ES` + `!07*, !35*, !38*, !51*, !52*`; Baleares `07*`; Canarias, Ceuta y
Melilla `35*, 38*, 51*, 52*`. **Recogida en tienda**: no pide dirección; sin reglas se ofrece siempre gratis.
`URL de seguimiento` con `{tracking}` va en el correo de «Enviado». **Simulador de portes**: qué vería un cliente
para un país, código postal, peso, importe y grupo.

## Pagos

Configuración → Formas de pago: nombre, descripción, instrucciones (`{number}`, `{total}`, `{shop}`), grupos,
recargo (% y fijo), pedido mínimo y máximo. Cada forma usa una **pasarela**; las claves van en `config.local.php`.
La columna «Claves» indica si la pasarela está configurada: si no, no se ofrece en la tienda.

```php
'payments' => [
    'redsys' => ['sandbox' => false, 'merchant_code' => '999008881', 'terminal' => '1', 'secret' => 'clave SHA-256',
                 'pay_methods' => null],          // 'z' = solo Bizum, 'C' = solo tarjeta
    'paypal' => ['sandbox' => false, 'client_id' => '…', 'client_secret' => '…'],
],
```

- **Transferencia**: el pedido queda pendiente con las instrucciones en la página y el correo. En la gestión,
  **Marcar como pagado** confirma el pago y avisa al cliente.
- **Redsys**: redirección al TPV con firma HMAC_SHA256_V1. El resultado llega por la notificación online
  (`/pay/redsys/notify`); si no llega (p. ej. en local), se acepta el de la vuelta a URLOK, que va firmado igual.
  Pruebas: `sandbox => true`, FUC `999008881`, terminal `1`, clave `sq7HjrUOBfKmC576ILgskD5srU870gJ7`, tarjeta
  `4548810000000003`, CVV `123`.
- **PayPal**: API Orders v2. Se crea la orden, el cliente la aprueba en PayPal y se captura al volver. Credenciales
  en developer.paypal.com (una pareja para sandbox y otra para live).

Cada intento de pago queda en `payments` (se ve en la ficha del pedido). El resultado se aplica una sola vez aunque
lleguen a la vez la notificación y la vuelta, y se comprueba que el importe cobrado es el del pedido.

### Añadir una pasarela

```php
// src/Shop/Payments/StripeGateway.php (fichero del proyecto)
final class StripeGateway extends Gateway
{
    public function available(): bool { return $this->opt('secret') !== null; }
    public function start(array $order, array $payment, PaymentUrls $urls): PaymentAction
    {
        // crear el pago en la pasarela con $payment['amount'], $urls->return, $urls->cancel, $urls->notify…
        return PaymentAction::redirect($url)->withReference($idDeLaPasarela);
    }
    public function notify(array $get, array $post, string $body): ?PaymentResult { /* verificar firma */ }
    public function complete(array $payment, array $get, array $post): PaymentResult { /* al volver el cliente */ }
}
```

```php
// config.local.php
'payments' => ['gateways' => ['stripe' => Nebula\Shop\Payments\StripeGateway::class], 'stripe' => ['secret' => '…']],
```

y la opción `"stripe": "gateway.stripe"` en `modules/payment_methods.json` (con su texto en `lang/*/payment_methods.json`).
`PaymentResult::paid($referencia, $transaccion, $importe)`, `failed()`, `cancelled()` o `pending()`.
Las URLs de la pasarela son `/pay/{código}/return|cancel|notify`.

## Promociones

Tienda → Promociones.

- **Sin cupón**: se aplica sola a quien cumple las condiciones. Si además no tiene importe mínimo, el precio rebajado
  ya se ve en el catálogo (con el anterior tachado y la etiqueta de la promoción).
- **Con cupón**: el cliente lo escribe en el carrito (sin distinguir mayúsculas).
- **Tipo**: porcentaje, importe fijo (repartido entre las líneas a las que se aplica) o portes gratis.
- **Se aplica a** todo el pedido, unas categorías o unos productos; **grupos**, **fechas**, **importe mínimo**,
  **usos máximos** y **usos por cliente** (por email, sin contar pedidos cancelados).
- **Combinable**: todas las combinables se suman (cada porcentaje sobre lo que queda). Si hay alguna no combinable, se
  queda la opción que más ahorra: esa sola o la suma de las combinables.
- El descuento se reparte por línea (para el IVA de cada tipo). El pedido guarda las promociones aplicadas y su uso.

## Pedidos

Tienda → Pedidos: filtros por estado, pago, pasarela, fecha, país y cliente; total de los filtrados; exportación.

- **Estados**: pendiente de pago → pagado → en preparación → enviado → entregado; cancelado y reembolsado en
  cualquier momento. Al cambiarlo se guarda en el historial (con la nota) y se avisa al cliente por correo
  (en preparación, enviado con el seguimiento, entregado, cancelado, reembolsado) salvo que se marque
  «No avisar al cliente».
- **Existencias**: se descuentan al crear el pedido y se devuelven al cancelarlo, reembolsarlo o borrarlo (una vez;
  si se reactiva, se vuelven a descontar).
- La ficha muestra las líneas con descuentos e IVA, los intentos de pago y el historial. Botones **Marcar como
  pagado** (permiso propio `mark_paid`), **Reenviar confirmación** y **Ver en la tienda**. Cada pedido tiene
  conversación interna (chat).
- Los pedidos solo se crean desde la tienda (no hay «Nuevo»).

Correos: al cliente (recibido con instrucciones si el pago es fuera de línea, pagado, cambios de estado) y a la tienda
(`shop.notify_email`) en los pedidos nuevos por transferencia y en los pagados. Plantillas en `templates/store/emails/`,
en el idioma del pedido.

**Panel** (Tienda → Panel, inicio de la gestión): ventas de hoy y del mes, pendientes de pago, por preparar, clientes
nuevos, gráfico de 30 días, últimos pedidos, más vendidos y existencias bajas.

## Clientes

Cuenta opcional (`shop.guest_checkout`): quien compra sin cuenta queda como cliente sin contraseña y, si luego se
registra con el mismo email, se completa esa ficha (con sus pedidos). La cuenta tiene pedidos, datos, cambio de
contraseña y libreta de direcciones (la del pedido se guarda sola). Acceso con bloqueo tras `shop.login_max_attempts`
fallos y recuperación de contraseña por enlace de un solo uso (1 h). Son independientes de los usuarios de la gestión.

## La tienda por dentro

| Ruta | |
|---|---|
| `/` · `/c/{slug}` · `/p/{slug}` · `/buscar?q=` | portada, categoría (`sort`, `page`), producto, búsqueda |
| `/carrito` · `/checkout` · `/pedido/{número}?t=` · `/pedido/{número}/pagar` | carrito, checkout, pedido (con su token o la sesión de quien lo hizo), ir a la pasarela |
| `/entrar` · `/registro` · `/recuperar` · `/restablecer` · `/cuenta` · `/cuenta/datos` | cuenta |
| `/idioma/{código}` | cambia el idioma |
| `/media/{id}[/thumb]` | imágenes de productos y categorías (las del resto de módulos solo se ven en la gestión) |
| `/assets/{fichero}` · `/assets/logo` | `templates/store/assets/` y el logo |
| `/api/…` | JSON para `store.js`: `search`, `cart`, `cart/add`, `cart/update`, `cart/coupon`, `checkout/quote`, `checkout/place`, `account/*` |
| `/pay/{pasarela}/return·cancel·notify` | vuelta y aviso de las pasarelas (sin CSRF) |

- Los POST de `/api/` llevan la cabecera `X-CSRF-Token` (meta `csrf-token`). El navegador nunca envía importes: el
  carrito guarda producto, color y cantidad, y `CartCalculator` lo valora todo en el servidor. Al confirmar, si el
  total ha cambiado desde que el cliente lo vio (precio, existencias, promoción), se le enseña antes de crear el pedido.
- `store.js` usa atributos `data-*` (`data-add-to-cart`, `data-cart-open`, `data-listing`, `data-checkout`,
  `data-ajax-form`…): una plantilla propia puede reutilizarlos.
- Textos en `lang/{ca,es,en}/store.json`; los datos del catálogo (nombres, descripciones) están en un solo idioma.

## Cosmo

`config.local.php` → `'remote' => ['token' => '…64 caracteres…', 'allow_ips' => ['IP de Cosmo']]`. La API responde en
`https://tienda/index.php?r=remote&a=…` y en `…/admin/index.php?r=remote&a=…`, con las mismas acciones que Quasar
(estado, log, integridad, copias, actualizar, migrar, mantenimiento; ver docs/gestion.md, «API remota») y
`"app": "nebula"`. El estado añade `shop` (productos, pedidos de 24 h, pendientes, por preparar, ventas del mes y
pasarelas configuradas). En modo mantenimiento la tienda responde 503 salvo `/pay/` (los avisos de pago siguen llegando).

Para darla de alta en Cosmo falta una entrada en su `config/config.php` → `apps`:

```php
'nebula' => [
    'name' => 'Nebula',
    'repo' => 'jprojects/nebula',          // por confirmar
    'min_version' => '0.1.0',
    'backups' => ['full' => 'Completa', 'db' => 'Base de datos', 'files' => 'Ficheros'],
],
```

## Versiones

Semver en `VERSION` con su entrada en `CHANGELOG.md`. Los ficheros del núcleo están en `core.json`: lo que no esté
ahí (módulos, idiomas, hooks y pasarelas propios de una tienda) nunca lo toca una actualización.
