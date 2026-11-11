# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) · versiones [semver](https://semver.org/lang/es/).

## 0.5.0 — 2026-11-11

### Cambiado
- La portada ya no muestra las tarjetas de las categorías principales (están en el megamenú de la cabecera), así los
  productos destacados se ven sin bajar. `shop.home_categories => true` las vuelve a poner.

## 0.4.0 — 2026-11-11

### Añadido
- **Megamenú de categorías** en la cabecera (`partials/mega_menu.php`): botón «Totes les categories» con las
  principales a la izquierda y sus subcategorías y las de tercer nivel en columnas; en el móvil, a pantalla completa
  entrando en cada categoría. Sustituye a la fila de categorías bajo la cabecera, que con muchas no se veía.
- **Botón de modo claro / oscuro** en la cabecera, que se recuerda en el navegador (`shop.theme.switch`).

### Cambiado
- El modo oscuro de `store.css` sigue al sistema salvo que el visitante elija (`data-theme` en `<html>`). Las
  cabeceras de tema con el menú antiguo (`data-nav`) siguen funcionando.

## 0.3.1 — 2026-11-11

### Corregido
- Importación desde Joomla: las categorías principales salían repetidas una vez por idioma. Ahora las versiones de una
  categoría se enlazan también por sus subcategorías (o por el alias sin el sufijo del idioma), y las categorías sin
  productos en todo su árbol no se importan. Una tienda importada con la 0.3.0 se tiene que volver a importar desde cero.

## 0.3.0 — 2026-11-11

### Añadido
- **Idiomas de la tienda en la gestión** (Configuración → Idiomas, tabla `shop_languages`): cuántos idiomas tiene la
  tienda, su orden y cuál es el de por defecto. Sustituye a `shop.locale` / `shop.locales`, que solo se usan para
  crearlos la primera vez.
- **Catálogo traducido**: sección Traducciones en productos y categorías (nombre, URL, resumen, descripción y SEO por
  idioma; lo vacío sale en el idioma por defecto). La tienda muestra los textos, el menú, la búsqueda, el orden por
  nombre y las líneas del carrito y del pedido en el idioma del visitante, con URL propia por idioma (redirige a la del
  idioma actual). Tablas `product_translations` y `category_translations`.
- **Precios solo para clientes** (`shop.guest_prices => false`): sin sesión no se ven precios ni se puede comprar.
- **Recargo de equivalencia**: casilla en la ficha del cliente; se suma por tipo de IVA (`shop.equivalence_surcharge`)
  y se ve en el carrito, el checkout, el pedido, los correos y la gestión. Columnas `customers.equivalence_surcharge`
  y `orders.surcharge_total`.
- **Contraseñas de clientes importados** (`LegacyPasswords`, `shop.legacy_passwords`): se aceptan los hashes antiguos
  de Joomla (md5 con sal, `{SHA256}`, phpass) y se pasan a `password_hash()` al entrar.
- **Importación desde Joomla** (`bin/import-joomla.php`, `src/Import/`): idiomas, grupos, categorías y productos con
  sus traducciones e imágenes, clientes con su contraseña, pedidos como historial, portes y cupones de com_botiga (1.0
  de Joomla 3 y versión de Joomla 4/5). Simulación (`--dry-run`), informe de lo que hay que revisar y reimportación sin
  duplicados (`import_map`).

### Corregido
- En móvil, una ficha con muchas imágenes ensanchaba la página (la fila de miniaturas ahora se desplaza).

## 0.2.0 — 2026-11-06

### Añadido
- **Tema propio** (`shop.theme.dir`): una carpeta fuera del núcleo cuyas plantillas y assets tienen prioridad sobre los
  de `templates/store/`. Se carga su `assets/theme.css` después de `store.css`, su `partials/head.php` en el `<head>`
  y su `assets/favicon.png` si lo tiene. Ver «Tema propio» en el README.

### Cambiado
- La cabecera y el pie de la tienda pasan del `layout.php` a `partials/header.php` y `partials/footer.php`, para que un
  tema los pueda sustituir sin copiar el layout.

## 0.1.0 — 2026-10-26

Primera versión. La gestión parte del núcleo de Quasar 0.7.0 (renombrado a `Nebula\`); ver `docs/gestion.md`.

### Añadido
- **Tienda pública** (`public/index.php`) con URLs limpias, en catalán, castellano e inglés: portada, categorías con
  subcategorías, búsqueda con sugerencias, ficha de producto, carrito lateral, checkout en una página, página del
  pedido y cuenta del cliente (pedidos, datos, direcciones, recuperar contraseña). Todo por AJAX sin recargar:
  orden y paginación de listados (con `history.pushState`), cambio de color (imágenes y precio), carrito, cupones,
  portes y formas de pago al cambiar la dirección, formularios de la cuenta y espera del pago.
- **Catálogo**: productos en varias categorías (árbol), colores con sus imágenes, referencia y suplemento, precio
  anterior tachado, IVA por producto, peso, existencias (opcionales) y SEO.
- **Tarifas por grupo de clientes** (roles): precio por grupo y cantidad mínima, y descuento general del grupo para el
  resto de productos. El grupo también limita envíos, pagos y promociones.
- **Portes** por reglas: país, código postal (prefijos, intervalos, exclusiones), peso, importe, precio por kg y
  gratis a partir de un importe; recogida en tienda; simulador en la gestión.
- **Pagos** con pasarelas intercambiables (`payments.gateways`): transferencia, Redsys (redirección, HMAC-SHA256,
  notificación online) y PayPal (Orders v2). Recargos, mínimos y máximos por forma de pago.
- **Promociones**: automáticas o con cupón; porcentaje, importe fijo o portes gratis; por pedido, categorías o
  productos; grupos, fechas, importe mínimo, usos totales y por cliente, combinables o exclusivas.
- **Pedidos**: estados con historial, avisos por correo al cliente, existencias que se descuentan y se devuelven,
  marcar como pagado, reenviar la confirmación, desglose de IVA, intentos de pago; panel de ventas.
- Tienda de ejemplo en `database/demo.sql` con sus imágenes en `database/demo/`.
- `bin/cron.php`: cancela los pedidos sin pagar caducados.
- Tipos de campo `display` (bloque calculado con plantilla) y `color` en la gestión.
- API remota para Cosmo con `"app": "nebula"` y un bloque `shop` en el estado; responde también en la raíz de la tienda.
