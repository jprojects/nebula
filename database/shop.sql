-- Nebula · tablas de la tienda (MySQL 5.7+ / MariaDB 10.3+)
-- Las ejecuta Migrator después de schema.sql. Todo es CREATE TABLE IF NOT EXISTS: las columnas añadidas en
-- versiones posteriores van en Migrator::run() con ensureColumn().

CREATE TABLE IF NOT EXISTS countries (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code    CHAR(2)      NOT NULL,                  -- ISO 3166-1 alfa-2
  name    VARCHAR(100) NOT NULL,
  active  TINYINT(1)   NOT NULL DEFAULT 1,        -- se puede elegir en el checkout
  sort    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_countries_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupos (roles) de clientes: deciden la tarifa de precios, los envíos, los pagos y las promociones
CREATE TABLE IF NOT EXISTS customer_groups (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  code              VARCHAR(50)   NOT NULL,
  name              VARCHAR(100)  NOT NULL,
  description       VARCHAR(255)  NULL,
  discount_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0,   -- descuento general si el producto no tiene precio para el grupo
  is_default        TINYINT(1)    NOT NULL DEFAULT 0,   -- el de los visitantes y los clientes nuevos
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_groups_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email              VARCHAR(150) NOT NULL,
  password_hash      VARCHAR(255) NULL,                -- NULL = cliente creado al comprar sin cuenta
  name               VARCHAR(150) NOT NULL DEFAULT '',
  company            VARCHAR(150) NULL,
  vat_id             VARCHAR(30)  NULL,
  phone              VARCHAR(30)  NULL,
  group_id           INT UNSIGNED NULL,
  addresses          JSON         NULL,                -- repeater: [{label, name, address, city, postcode, province, country, phone}]
  locale             VARCHAR(5)   NULL,
  active             TINYINT(1)   NOT NULL DEFAULT 1,
  accepts_marketing  TINYINT(1)   NOT NULL DEFAULT 0,
  notes              TEXT         NULL,                -- solo para la tienda
  failed_logins      INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until       DATETIME     NULL,
  last_login         DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_email (email),
  KEY ix_customers_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enlaces de un solo uso (recuperar contraseña). Solo se guarda el hash del token.
CREATE TABLE IF NOT EXISTS customer_tokens (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  type         VARCHAR(20)  NOT NULL,                  -- reset
  token_hash   CHAR(64)     NOT NULL,
  expires_at   DATETIME     NOT NULL,
  used_at      DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_tokens_hash (token_hash),
  KEY ix_customer_tokens_customer (customer_id),
  CONSTRAINT fk_customer_tokens_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id         INT UNSIGNED NULL,
  name              VARCHAR(150) NOT NULL,
  slug              VARCHAR(160) NOT NULL,
  description       TEXT         NULL,
  image             INT UNSIGNED NULL,                 -- media
  active            TINYINT(1)   NOT NULL DEFAULT 1,
  in_menu           TINYINT(1)   NOT NULL DEFAULT 1,
  sort              INT          NOT NULL DEFAULT 0,
  meta_title        VARCHAR(200) NULL,
  meta_description  VARCHAR(300) NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY ix_categories_parent (parent_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  sku               VARCHAR(64)   NULL,
  name              VARCHAR(200)  NOT NULL,
  slug              VARCHAR(210)  NOT NULL,
  summary           VARCHAR(500)  NULL,
  description       MEDIUMTEXT    NULL,                -- richtext
  price             DECIMAL(12,2) NOT NULL DEFAULT 0,  -- precio general (tarifa por defecto), IVA incluido
  compare_price     DECIMAL(12,2) NULL,                -- precio anterior que se muestra tachado
  tax_rate          DECIMAL(5,2)  NOT NULL DEFAULT 21,
  weight            DECIMAL(10,3) NOT NULL DEFAULT 0,  -- kg, para los portes
  stock             INT           NULL,                -- NULL = sin control de existencias
  images            JSON          NULL,                -- media múltiple (imágenes generales)
  categories        JSON          NULL,                -- select múltiple; se copia a product_categories al guardar
  colors            JSON          NULL,                -- repeater: [{code, name, hex, images, sku, price_delta}]
  prices            JSON          NULL,                -- repeater: [{group_id, min_qty, price}]; se copia a product_prices
  active            TINYINT(1)    NOT NULL DEFAULT 1,
  featured          TINYINT(1)    NOT NULL DEFAULT 0,
  sort              INT           NOT NULL DEFAULT 0,
  meta_title        VARCHAR(200)  NULL,
  meta_description  VARCHAR(300)  NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  KEY ix_products_sku (sku),
  KEY ix_products_active (active, featured, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copia de products.categories para consultar la tienda por categoría (la mantiene ProductsHooks)
CREATE TABLE IF NOT EXISTS product_categories (
  product_id   INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  KEY ix_product_categories_category (category_id),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copia de products.prices: precio por grupo de clientes y cantidad mínima (la mantiene ProductsHooks)
CREATE TABLE IF NOT EXISTS product_prices (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED  NOT NULL,
  group_id    INT UNSIGNED  NOT NULL,
  min_qty     INT UNSIGNED  NOT NULL DEFAULT 1,
  price       DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_prices (product_id, group_id, min_qty),
  KEY ix_product_prices_group (group_id),
  CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_group FOREIGN KEY (group_id) REFERENCES customer_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formas de envío con sus reglas de portes (país, código postal, peso, importe)
CREATE TABLE IF NOT EXISTS shipping_methods (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(150) NOT NULL,
  description   VARCHAR(300) NULL,
  carrier       VARCHAR(100) NULL,
  tracking_url  VARCHAR(300) NULL,                    -- con {tracking}: https://…?n={tracking}
  delivery      VARCHAR(100) NULL,                    -- plazo que ve el cliente: "24/48 h"
  group_ids        JSON         NULL,                    -- solo estos grupos de clientes (vacío = todos)
  rules         JSON         NULL,                    -- repeater, ver ShippingCalculator
  pickup        TINYINT(1)   NOT NULL DEFAULT 0,      -- recogida en tienda: no pide dirección de envío
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  sort          INT          NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formas de pago. La pasarela (gateway) es una clase de config 'payments.gateways'; las claves van en config.local.php
CREATE TABLE IF NOT EXISTS payment_methods (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  gateway       VARCHAR(30)   NOT NULL,               -- transfer | redsys | paypal | …
  name          VARCHAR(150)  NOT NULL,
  description   VARCHAR(300)  NULL,
  instructions  TEXT          NULL,                   -- se muestran al terminar el pedido y en el correo
  group_ids        JSON          NULL,
  fee_percent   DECIMAL(5,2)  NOT NULL DEFAULT 0,     -- recargo
  fee_fixed     DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_total     DECIMAL(12,2) NULL,
  max_total     DECIMAL(12,2) NULL,
  active        TINYINT(1)    NOT NULL DEFAULT 1,
  sort          INT           NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Descuentos y promociones: automáticas (sin código) o con cupón, con fechas, mínimos y límites de uso
CREATE TABLE IF NOT EXISTS promotions (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name               VARCHAR(150)  NOT NULL,
  label              VARCHAR(150)  NULL,              -- texto que ve el cliente (por defecto el nombre)
  code               VARCHAR(40)   NULL,              -- cupón; NULL = se aplica sola
  type               VARCHAR(20)   NOT NULL DEFAULT 'percent',   -- percent | fixed | free_shipping
  value              DECIMAL(12,2) NOT NULL DEFAULT 0,
  scope              VARCHAR(20)   NOT NULL DEFAULT 'all',       -- all | categories | products
  categories         JSON          NULL,
  products           JSON          NULL,
  group_ids             JSON          NULL,              -- solo estos grupos de clientes (vacío = todos)
  min_subtotal       DECIMAL(12,2) NULL,
  starts_at          DATETIME      NULL,
  ends_at            DATETIME      NULL,
  max_uses           INT UNSIGNED  NULL,
  max_uses_customer  INT UNSIGNED  NULL,
  uses               INT UNSIGNED  NOT NULL DEFAULT 0,
  combinable         TINYINT(1)    NOT NULL DEFAULT 1,
  active             TINYINT(1)    NOT NULL DEFAULT 1,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_promotions_code (code),
  KEY ix_promotions_active (active, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  number              VARCHAR(30)   NOT NULL,
  token               CHAR(32)      NOT NULL,          -- acceso a la página del pedido sin cuenta
  customer_id         INT UNSIGNED  NULL,
  group_id            INT UNSIGNED  NULL,
  email               VARCHAR(150)  NOT NULL,
  phone               VARCHAR(30)   NULL,
  status              VARCHAR(20)   NOT NULL DEFAULT 'pending',  -- pending | paid | processing | shipped | delivered | cancelled | refunded
  payment_status      VARCHAR(20)   NOT NULL DEFAULT 'pending',  -- pending | paid | failed | refunded
  payment_method_id   INT UNSIGNED  NULL,
  payment_gateway     VARCHAR(30)   NULL,
  payment_name        VARCHAR(150)  NULL,
  shipping_method_id  INT UNSIGNED  NULL,
  shipping_name       VARCHAR(150)  NULL,
  bill_name           VARCHAR(150)  NOT NULL DEFAULT '',
  bill_company        VARCHAR(150)  NULL,
  bill_vat_id         VARCHAR(30)   NULL,
  bill_address        VARCHAR(255)  NOT NULL DEFAULT '',
  bill_city           VARCHAR(100)  NOT NULL DEFAULT '',
  bill_postcode       VARCHAR(20)   NOT NULL DEFAULT '',
  bill_province       VARCHAR(100)  NULL,
  bill_country        CHAR(2)       NOT NULL DEFAULT 'ES',
  ship_name           VARCHAR(150)  NULL,
  ship_address        VARCHAR(255)  NULL,
  ship_city           VARCHAR(100)  NULL,
  ship_postcode       VARCHAR(20)   NULL,
  ship_province       VARCHAR(100)  NULL,
  ship_country        CHAR(2)       NULL,
  ship_phone          VARCHAR(30)   NULL,
  subtotal            DECIMAL(12,2) NOT NULL DEFAULT 0,  -- suma de líneas (IVA incluido)
  discount            DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_total           DECIMAL(12,2) NOT NULL DEFAULT 0,  -- IVA incluido en el total
  total               DECIMAL(12,2) NOT NULL DEFAULT 0,
  weight              DECIMAL(10,3) NOT NULL DEFAULT 0,
  coupon_code         VARCHAR(40)   NULL,
  promotions          JSON          NULL,                -- promociones aplicadas: [{id, label, amount}]
  taxes               JSON          NULL,                -- desglose: [{rate, base, tax}]
  notes               TEXT          NULL,                -- del cliente
  admin_notes         TEXT          NULL,
  tracking_number     VARCHAR(100)  NULL,
  locale              VARCHAR(5)    NULL,
  ip                  VARCHAR(45)   NULL,
  stock_restored      TINYINT(1)    NOT NULL DEFAULT 0,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  paid_at             DATETIME      NULL,
  shipped_at          DATETIME      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (number),
  KEY ix_orders_customer (customer_id, created_at),
  KEY ix_orders_status (status, created_at),
  KEY ix_orders_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED  NOT NULL,
  product_id   INT UNSIGNED  NULL,
  sku          VARCHAR(64)   NULL,
  name         VARCHAR(200)  NOT NULL,
  color_code   VARCHAR(60)   NULL,
  color_name   VARCHAR(100)  NULL,
  qty          INT UNSIGNED  NOT NULL,
  unit_price   DECIMAL(12,2) NOT NULL,                 -- con IVA y antes de promociones
  discount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_rate     DECIMAL(5,2)  NOT NULL,
  total        DECIMAL(12,2) NOT NULL,                 -- qty × unit_price − discount
  weight       DECIMAL(10,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_order_items_order (order_id),
  KEY ix_order_items_product (product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de estados de cada pedido (lo escribe OrderService)
CREATE TABLE IF NOT EXISTS order_history (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT UNSIGNED NOT NULL,
  status      VARCHAR(20)  NOT NULL,
  note        VARCHAR(500) NULL,
  user_id     INT UNSIGNED NULL,                       -- usuario de la gestión (NULL = la tienda o la pasarela)
  notified    TINYINT(1)   NOT NULL DEFAULT 0,         -- se avisó al cliente por correo
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_order_history_order (order_id, id),
  CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de pago de cada pedido (uno por cada vez que se va a la pasarela)
CREATE TABLE IF NOT EXISTS payments (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  order_id        INT UNSIGNED  NOT NULL,
  gateway         VARCHAR(30)   NOT NULL,
  reference       VARCHAR(64)   NOT NULL,              -- la que se envía a la pasarela (Ds_Order, id de PayPal…)
  amount          DECIMAL(12,2) NOT NULL,
  status          VARCHAR(20)   NOT NULL DEFAULT 'pending',   -- pending | paid | failed | cancelled
  transaction_id  VARCHAR(100)  NULL,
  message         VARCHAR(255)  NULL,
  response        JSON          NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_reference (gateway, reference),
  KEY ix_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_uses (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  promotion_id  INT UNSIGNED  NOT NULL,
  order_id      INT UNSIGNED  NOT NULL,
  customer_id   INT UNSIGNED  NULL,
  email         VARCHAR(150)  NOT NULL,
  amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_promotion_uses (promotion_id, order_id),
  KEY ix_promotion_uses_email (promotion_id, email),
  CONSTRAINT fk_promotion_uses_promotion FOREIGN KEY (promotion_id) REFERENCES promotions (id) ON DELETE CASCADE,
  CONSTRAINT fk_promotion_uses_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
