# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) · versiones [semver](https://semver.org/lang/es/).

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
