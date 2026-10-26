# Nebula

Tienda online independiente. PHP 8.1+, MySQL/MariaDB y JS sin dependencias. Dos partes:

- **Tienda** (`public/index.php` → `src/Shop/`, `templates/store/`, `lang/*/store.json`).
- **Gestión** (`public/admin/` → núcleo de Quasar 0.7.0 renombrado a `Nebula\`: `src/Core`, `src/Fields`,
  `src/Controllers`, `templates/default`) con los módulos JSON de la tienda (`modules/`, `src/Hooks/`, `src/Pages/`).

El README es la referencia de la tienda y `docs/gestion.md` la del motor de la gestión: léelos antes de cambiar algo y
actualízalos con cada función nueva. Se gestiona desde Cosmo (`~/Projects/cosmo`) igual que Quasar (`~/Projects/quasar`)
y Neutron.

## Convenciones (las de Quasar)

- **Sin dependencias**: ni Composer ni npm. No añadas librerías (tampoco la de Redsys: la firma está en `RedsysGateway`).
- Comentarios, mensajes para el desarrollador, README y CHANGELOG **en castellano**.
- Textos de la interfaz en los tres idiomas: tienda en `lang/{ca,es,en}/store.json`, gestión en `lang/*/{módulo}.json`
  (el catalán es el idioma por defecto). Nada de texto fijo en PHP ni en JS: lo que necesita `store.js` va en el
  `#nb-config` del layout o ya pintado en el HTML que devuelve la API.
- Iconos Lucide (`$this->icon()` en las dos plantillas), nunca emojis. CSS con variables (`--primary`, `--surface`…),
  claro y oscuro.
- Prefijos: `nb-` en la tienda y en lo nuevo de la gestión; la gestión conserva los `qs-` heredados de Quasar.
- Permisos y validaciones siempre en el servidor. En la tienda el navegador nunca envía importes: `CartCalculator`
  lo valora todo y `CheckoutController::place` lo vuelve a calcular antes de crear el pedido.
- POST de la tienda con CSRF (`X-CSRF-Token`), salvo `/pay/*` (las pasarelas). Errores esperados para el cliente:
  `ShopException('clave')` → `errors.clave` de `store.json`.
- `groups` es palabra reservada en MySQL 8: las columnas de grupos de clientes se llaman `group_ids`.
- Los `pattern` de los JSON van con el guion escapado (`[a-z0-9\\-]*`): los navegadores los evalúan con el flag `v`.

## Núcleo de Quasar

- Los ficheros heredados (`src/Core`, `src/Fields`, `src/Controllers`, `src/Remote`, `src/Mail`, `src/Export`,
  `src/Ai`, `src/Reports`, `src/Google`, `templates/default`) se mantienen lo más parecidos posible a Quasar para poder
  portar arreglos en los dos sentidos. Cambios propios de Nebula en ellos: `Migrator` (shop.sql y datos iniciales),
  `HomeController` (redirige al Panel), `RemoteController` (`app: nebula`, bloque `shop`), `FieldFactory` (tipos
  `display` y `color`), `bootstrap.php` (`ShopConfig`), estilos `nb-` al final de `style.css`.
- La cabecera de la API remota sigue siendo `X-Quasar-Token` (la que envía Cosmo).

## Base de datos

- Gestión en `database/schema.sql`, tienda en `database/shop.sql` (`CREATE TABLE IF NOT EXISTS`). Columnas nuevas en
  `Migrator::run()` (`ensureColumn`). Todo idempotente: lo ejecutan `bin/install.php` y `?r=remote&a=migrate`.
- `products.categories` y `products.prices` (JSON de los campos de la gestión) se copian a `product_categories` y
  `product_prices` en `ProductsHooks`; la tienda consulta esas tablas.
- Datos de ejemplo en `database/demo.sql` (INSERT con ids fijos) e imágenes en `database/demo/` (rutas `demo/…` de la
  tabla `media`). Los carga `Migrator` solo si no hay productos. Si cambian las tablas, hay que actualizar sus INSERT.

## Versiones y publicación

- Semver en `VERSION`, con su entrada en `CHANGELOG.md` (una función nueva o un cambio de BD sube la MENOR).
- Ficheros nuevos del núcleo en `core.json`; si no, la actualización desde Cosmo no los instala. Módulos, idiomas,
  hooks y pasarelas propios de una tienda concreta no van ahí.
- Publicar: commit, `git tag -a vX.Y.Z -m "Nebula X.Y.Z"` y push de la rama y las etiquetas.

## Probar

- Sintaxis: `for f in $(find . -name '*.php' -not -path './.git/*'); do php -l $f; done` y
  `node --check templates/store/assets/store.js templates/default/assets/app.js`.
- Local: `config/config.local.php` (MariaDB local en el puerto 3307, root sin contraseña; correo `log` en
  `storage/logs/mail/`), `php bin/install.php` y `php -S 127.0.0.1:8010 -t public public/index.php`.
  Gestión `admin` / ver `config.local.php`; clientes de ejemplo con `demo1234`.
- Redsys: TPV de pruebas en `config.local.php`. La notificación se puede simular firmándola con
  `~/Projects/redsys/redsys/apiRedsys.php` (`createMerchantSignatureNotif`) y enviándola a `/pay/redsys/notify`.
- Navegador: Chromium headless por el protocolo de DevTools desde Node 22 (perfil en `~/snap/chromium/common/…`,
  Chromium es un snap). Probar la tienda en móvil y en oscuro, y la gestión también con un usuario no administrador.
