<?php
declare(strict_types=1);

namespace Nebula\Import;

use Nebula\Core\Database;
use Nebula\Core\HtmlSanitizer;
use Nebula\Core\MediaException;
use Nebula\Core\MediaStorage;
use Nebula\Hooks\ProductsHooks;
use Nebula\Shop\Locales;
use Nebula\Shop\Money;
use Nebula\Shop\Text;
use Nebula\Shop\Translations;

/**
 * Importa una tienda de Joomla hecha con el componente com_botiga (cualquier versión: la 1.0 de Joomla 3 y la de
 * Joomla 4/5) a Nebula: idiomas, grupos, categorías, productos con sus imágenes y traducciones, clientes (con su
 * contraseña de Joomla, que sigue valiendo: ver LegacyPasswords), pedidos como historial, portes y cupones.
 *
 * No depende de la versión de botiga sino de lo que hay en la base de datos: se mira qué tablas y columnas existen
 * (precios en items.price o en botiga_items_prices, imágenes image1..5 o images, direcciones en botiga_users o en
 * botiga_user_address…). Se puede repetir: import_map recuerda qué se importó y lo actualiza (ver ImportMap).
 *
 *   $report = (new JoomlaBotiga($joomlaDb, $nebulaDb, $media, ['joomla_dir' => '/ruta/joomla']))->run();
 *
 * Opciones:
 *   prefix          prefijo de las tablas de Joomla (por defecto, el que tenga las de botiga)
 *   joomla_dir      carpeta de la instalación de Joomla (la que contiene images/) para copiar las imágenes
 *   only            lista de pasos: languages, groups, categories, products, customers, orders, shipping, coupons
 *   dry_run         todo dentro de una transacción que se deshace; las imágenes no se copian
 *   prices_with_tax los precios de Joomla ya llevan IVA (por defecto no: botiga los guarda sin IVA)
 *   skip_groups     grupos de Joomla cuyos usuarios no son clientes (por defecto 7 Administrator y 8 Super Users)
 *   order_prefix    número de los pedidos importados: {order_prefix}{id de Joomla} (por defecto «J»)
 *   countries       correcciones de país: [id de botiga_countries => ISO2] (p. ej. un id por defecto erróneo)
 *   skip_never_logged  no importa los usuarios que nunca han entrado ni comprado (suelen ser altas de robots)
 */
final class JoomlaBotiga
{
    public const STEPS = ['languages', 'groups', 'categories', 'products', 'customers', 'orders', 'shipping', 'coupons'];
    private const SOURCE = 'joomla';
    /** Grupos del núcleo de Joomla: no son tarifas (se quedan en el grupo por defecto de Nebula) */
    private const CORE_GROUPS = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    private const LANGUAGE_NAMES = [
        'ca' => 'Català', 'es' => 'Español', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano',
        'pt' => 'Português', 'eu' => 'Euskara', 'gl' => 'Galego', 'nl' => 'Nederlands', 'oc' => 'Occitan',
    ];

    private string $p = '';
    /** @var array<string,list<string>> tabla (sin prefijo) => columnas */
    private array $columns = [];
    private array $params = [];
    private ImportMap $map;
    private Locales $locales;
    private int $defaultGroup = 0;
    /** @var array<int,int> grupo de Joomla => grupo de Nebula (los que no están: el de por defecto) */
    private array $groups = [];
    /** @var array<int,string> id de botiga_countries => ISO2 */
    private array $countries = [];
    private array $report = ['counts' => [], 'warnings' => [], 'notes' => []];
    /** @var array<string,list<string|int>> registros escritos en esta pasada, por entidad (ImportMap::touch) */
    private array $written = [];

    public function __construct(
        private readonly Database $src,
        private readonly Database $db,
        private readonly MediaStorage $media,
        private readonly array $opts = [],
    ) {
        $this->map = new ImportMap($db, self::SOURCE);
    }

    /** @return array{counts:array<string,int>,warnings:list<string>,notes:list<string>} */
    public function run(): array
    {
        $this->detect();
        ImportMap::ensureTable($this->db);
        $dry = !empty($this->opts['dry_run']);
        $only = $this->opts['only'] ?? self::STEPS;
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->locales = new Locales($this->db);
            $this->loadCountries();
            foreach (self::STEPS as $step) {
                // Idiomas y grupos siempre: los demás pasos los necesitan
                if (in_array($step, $only, true) || in_array($step, ['languages', 'groups'], true)) {
                    $this->$step();
                    foreach ($this->written as $entity => $ids) {
                        $this->map->touch($entity, $ids);
                    }
                    $this->written = [];
                }
            }
            $dry ? $pdo->rollBack() : $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        if ($dry) {
            $this->note('Simulación (--dry-run): no se ha guardado nada ni se han copiado imágenes.');
        }
        return $this->report;
    }

    // ---------------------------------------------------------------- origen

    private function detect(): void
    {
        $prefix = $this->opts['prefix'] ?? null;
        if ($prefix === null) {
            $table = (string) $this->src->fetchValue("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%botiga\\_items' LIMIT 1");
            if ($table === '') {
                throw new \RuntimeException('No hay tablas de com_botiga (…_botiga_items) en la base de datos de origen');
            }
            $prefix = substr($table, 0, -strlen('botiga_items'));
        }
        $this->p = (string) $prefix;
        foreach ($this->src->fetchAll(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ? ORDER BY ORDINAL_POSITION',
            [addcslashes($this->p, '_%') . '%']
        ) as $r) {
            $this->columns[substr((string) $r['TABLE_NAME'], strlen($this->p))][] = (string) $r['COLUMN_NAME'];
        }
        foreach (['users', 'usergroups', 'user_usergroup_map', 'categories', 'botiga_items'] as $t) {
            if (!$this->hasTable($t)) {
                throw new \RuntimeException("Falta la tabla {$this->p}$t en el origen");
            }
        }
        $params = $this->src->fetchValue("SELECT params FROM {$this->t('extensions')} WHERE element = 'com_botiga' AND type = 'component' LIMIT 1");
        $this->params = Text::json($params);
        $version = (string) ($this->src->fetchValue("SELECT manifest_cache FROM {$this->t('extensions')} WHERE element = 'joomla' AND type = 'file' LIMIT 1") ?? '');
        $this->note(sprintf('Origen: prefijo «%s», Joomla %s, com_botiga %s', $this->p,
            Text::json($version)['version'] ?? '?',
            Text::json($this->src->fetchValue("SELECT manifest_cache FROM {$this->t('extensions')} WHERE element = 'com_botiga' LIMIT 1"))['version'] ?? '?'));
    }

    private function t(string $table): string
    {
        return Database::ident($this->p . $table);
    }

    private function hasTable(string $table): bool
    {
        return isset($this->columns[$table]);
    }

    private function has(string $table, string $column): bool
    {
        return in_array($column, $this->columns[$table] ?? [], true);
    }

    // ---------------------------------------------------------------- idiomas y grupos

    /** Idiomas del catálogo de Joomla que faltan en Nebula (se crean activos). */
    private function languages(): void
    {
        $tags = array_merge(
            array_column($this->src->fetchAll("SELECT DISTINCT language FROM {$this->t('botiga_items')}"), 'language'),
            array_column($this->src->fetchAll("SELECT DISTINCT language FROM {$this->t('categories')} WHERE extension = 'com_botiga'"), 'language'),
        );
        $created = 0;
        foreach (array_unique(array_filter(array_map(fn ($t) => $this->locale((string) $t), $tags))) as $code) {
            if (!$this->db->fetchValue('SELECT 1 FROM shop_languages WHERE code = ?', [$code])) {
                $this->db->insert('shop_languages', ['code' => $code, 'name' => self::LANGUAGE_NAMES[$code] ?? strtoupper($code),
                    'sort' => 10 + $created, 'active' => 1]);
                $created++;
                if (!is_dir(NB_ROOT . '/lang/' . $code)) {
                    $this->warn("Idioma «{$code}» creado: la tienda no tiene sus textos (lang/{$code}/store.json), se verán en el de reserva");
                }
            }
        }
        $this->locales = new Locales($this->db);
        $this->count('idiomas creados', $created);
        $this->note('Idioma por defecto (textos principales del catálogo): ' . $this->locales->default());
    }

    /**
     * Grupos de Joomla con precio propio o con clientes que no son del núcleo → grupos de clientes de Nebula
     * (código joomla_{id}). Los del núcleo (Registered…) son el grupo por defecto de Nebula.
     */
    private function groups(): void
    {
        $this->defaultGroup = (int) ($this->db->fetchValue('SELECT id FROM customer_groups WHERE is_default = 1 LIMIT 1')
            ?? $this->db->fetchValue('SELECT id FROM customer_groups ORDER BY id LIMIT 1') ?? 0);
        $used = array_map('intval', array_column($this->src->fetchAll("SELECT DISTINCT group_id FROM {$this->t('user_usergroup_map')}"), 'group_id'));
        foreach ($this->src->fetchAll("SELECT id, price FROM {$this->t('botiga_items')}") as $row) {
            $used = array_merge($used, array_keys($this->itemPrices($row)));
        }
        $default = $this->defaultJoomlaGroup();
        $titles = array_column($this->src->fetchAll("SELECT id, title FROM {$this->t('usergroups')}"), 'title', 'id');
        $n = 0;
        foreach (array_unique(array_filter($used, 'is_int')) as $gid) {
            if (in_array($gid, self::CORE_GROUPS, true) || $gid === $default || !isset($titles[$gid])) {
                continue;
            }
            $id = $this->map->get('group', $gid);
            if ($id === null || !$this->db->fetchValue('SELECT 1 FROM customer_groups WHERE id = ?', [$id])) {
                $id = (int) ($this->db->fetchValue('SELECT id FROM customer_groups WHERE code = ?', ['joomla_' . $gid])
                    ?? $this->db->insert('customer_groups', ['code' => 'joomla_' . $gid, 'name' => mb_substr((string) $titles[$gid], 0, 100),
                        'description' => 'Grupo de Joomla #' . $gid]));
                $this->map->set('group', $gid, $id);
                $n++;
            }
            $this->groups[$gid] = $id;
        }
        $this->count('grupos de clientes creados', $n);
    }

    /** Grupo de Joomla cuyos precios son el precio general (params particular_usergroup_default, Registered). */
    private function defaultJoomlaGroup(): int
    {
        return (int) ($this->params['particular_usergroup_default'] ?? 2);
    }

    /** Grupo de Nebula de un usuario de Joomla: el de mayor id de los suyos que es tarifa (como botiga). */
    private function groupFor(array $joomlaGroups): ?int
    {
        rsort($joomlaGroups);
        foreach ($joomlaGroups as $g) {
            if (isset($this->groups[$g])) {
                return $this->groups[$g];
            }
        }
        return $this->defaultGroup ?: null;
    }

    // ---------------------------------------------------------------- categorías

    private function categories(): void
    {
        $rows = $this->src->fetchAll(
            "SELECT id, parent_id, lft, title, alias, description, params, metadesc, published, language
             FROM {$this->t('categories')} WHERE extension = 'com_botiga' AND published IN (0, 1) ORDER BY lft"
        );
        // Las versiones de una categoría en cada idioma comparten el código de familia de Factusol (botiga_categories)
        $keys = [];
        if ($this->has('botiga_categories', 'factusol_codfam')) {
            foreach ($this->src->fetchAll("SELECT catid, factusol_codfam FROM {$this->t('botiga_categories')}") as $r) {
                if (trim((string) $r['factusol_codfam']) !== '') {
                    $keys[(int) $r['catid']] = 'fam:' . trim((string) $r['factusol_codfam']);
                }
            }
        }
        $groups = $this->groupByLanguage($rows, fn ($r) => $keys[(int) $r['id']] ?? null);

        $done = 0;
        $kept = 0;
        $parents = [];
        foreach ($groups as $key => $members) {
            $main = $this->mainRow($members);
            $existing = $this->map->get('category', $key);
            if ($existing !== null && $this->map->changedSince('category', $key, 'categories')) {
                $kept++;
                $this->mapMembers('category_id', $members, $existing);
                continue;
            }
            $values = [
                'name' => mb_substr($this->clean($main['title']), 0, 150) ?: 'Categoria ' . $main['id'],
                'description' => $this->plain((string) $main['description']) ?: null,
                'active' => (int) $main['published'] === 1 ? 1 : 0,
                'sort' => (int) $main['lft'],
                'meta_description' => mb_substr($this->clean($main['metadesc']), 0, 300) ?: null,
            ];
            $id = $this->save('categories', 'category', $key, $values, $existing);
            $slug = trim((string) $main['alias']) !== '' ? Text::slug((string) $main['alias'], 150) : Text::slug($values['name'], 150);
            $update = ['slug' => Translations::uniqueSlug($this->db, 'categories', $slug, (string) $id)];
            $image = (string) (Text::json($main['params'])['image'] ?? '');
            if ($image !== '' && ($mediaId = $this->mediaFor($image, 'categories', 'image')) !== null) {
                $update['image'] = $mediaId;
            }
            $translations = $this->translations('categories', $members, $main, $id, fn ($r) => [
                'name' => $this->clean($r['title']), 'slug' => (string) $r['alias'],
                'description' => $this->plain((string) $r['description']), 'meta_description' => $this->clean($r['metadesc']),
            ]);
            $update['translations'] = json_encode($translations, JSON_UNESCAPED_UNICODE);
            $this->db->update('categories', $update, 'id', $id);
            Translations::sync($this->db, 'categories', $id, $translations);
            $this->mapMembers('category_id', $members, $id);
            $parents[$id] = (int) $main['parent_id'];
            $done++;
        }
        // Árbol: cuando ya están todas (el padre puede estar en otro grupo de idiomas)
        foreach ($parents as $id => $joomlaParent) {
            $parent = $joomlaParent > 1 ? $this->map->get('category_id', $joomlaParent) : null;
            $this->db->update('categories', ['parent_id' => $parent !== $id ? $parent : null], 'id', $id);
        }
        $this->count('categorías', $done);
        if ($kept) {
            $this->count('categorías cambiadas en Nebula (no se tocan)', $kept);
        }
    }

    // ---------------------------------------------------------------- productos

    private function products(): void
    {
        $rows = $this->src->fetchAll("SELECT * FROM {$this->t('botiga_items')} ORDER BY id");
        $codeCol = $this->has('botiga_items', 'factusol_codart') ? 'factusol_codart' : null;
        $groups = $this->groupByLanguage($rows, function (array $r) use ($codeCol): ?string {
            $code = $codeCol !== null ? trim((string) $r[$codeCol]) : '';
            if ($code !== '' && $code !== '0') {
                return 'cod:' . $code;
            }
            $ref = trim((string) ($r['ref'] ?? ''));
            return $ref !== '' ? 'ref:' . $ref : null;
        });
        $prices = $this->hasTable('botiga_items_prices') ? $this->pricesTable() : [];
        $stockControl = !empty($this->params['control_stock']);
        $defaultGroup = $this->defaultJoomlaGroup();

        $done = $kept = $noPrice = $foreign = 0;
        foreach ($groups as $key => $members) {
            $main = $this->mainRow($members);
            $foreign += ($this->locale((string) $main['language']) ?? $this->locales->default()) !== $this->locales->default() ? 1 : 0;
            $existing = $this->map->get('product', $key);
            if ($existing !== null && $this->map->changedSince('product', $key, 'products')) {
                $kept++;
                $this->mapMembers('item', $members, $existing);
                continue;
            }
            $rate = $this->taxRate((int) ($main['tipus_iva'] ?? 1));
            $byGroup = $prices[(int) $main['id']] ?? $this->itemPrices($main);
            $base = $byGroup[$defaultGroup] ?? $byGroup['default'] ?? ($byGroup ? min($byGroup) : null);
            if ($base === null) {
                $noPrice++;
                $base = 0.0;
            }
            $groupPrices = [];
            foreach ($byGroup as $g => $price) {
                if (is_int($g) && isset($this->groups[$g])) {
                    $groupPrices[] = ['group_id' => (string) $this->groups[$g], 'min_qty' => 1, 'price' => $this->withTax($price, $rate)];
                }
            }
            $price = $this->withTax($base, $rate);
            $pvp = (float) ($main['pvp'] ?? 0) > 0 ? $this->withTax((float) $main['pvp'], $rate) : null;

            $categories = [];
            foreach (Text::ids((string) $main['catid']) as $cat) {
                if (($c = $this->map->get('category_id', $cat)) !== null) {
                    $categories[] = (string) $c;
                }
            }
            $values = [
                'sku' => mb_substr(trim((string) ($main['ref'] ?? '')) ?: trim((string) ($main['sku'] ?? '')), 0, 64) ?: null,
                'name' => mb_substr($this->clean($main['name']), 0, 200) ?: 'Producte ' . $main['id'],
                'summary' => $this->texts($main)['summary'],
                'price' => $price,
                'compare_price' => $pvp !== null && $pvp > $price ? $pvp : null,
                'tax_rate' => $rate,
                'weight' => round(max(0.0, (float) ($main['pes'] ?? 0)), 3),
                'stock' => $stockControl ? (int) ($main['stock'] ?? 0) : null,
                'categories' => json_encode($categories),
                'prices' => json_encode($groupPrices),
                'active' => (int) $main['published'] === 1 ? 1 : 0,
                'featured' => (int) ($main['destacat'] ?? $main['featured'] ?? 0) ? 1 : 0,
                'sort' => (int) ($main['ordering'] ?? 0),
                'meta_description' => mb_substr($this->clean($main['metadescription'] ?? ''), 0, 300) ?: null,
            ];
            $id = $this->save('products', 'product', $key, $values, $existing);
            $slugBase = trim((string) ($main['alias'] ?? '')) !== '' ? (string) $main['alias'] : $values['name'];
            $translations = $this->translations('products', $members, $main, $id, fn ($r) => [
                'name' => $this->clean($r['name']), 'slug' => (string) ($r['alias'] ?? ''),
                'summary' => (string) $this->texts($r)['summary'],
                'description' => $this->texts($r)['description'],
                'meta_description' => mb_substr($this->clean($r['metadescription'] ?? ''), 0, 300),
            ]);
            $this->db->update('products', [
                'slug' => Translations::uniqueSlug($this->db, 'products', Text::slug($slugBase), (string) $id),
                'description' => $this->texts($main)['description'] ?: null,
                'images' => json_encode(array_map('strval', $this->productImages($main))),
                'translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ], 'id', $id);
            $this->db->query('DELETE FROM product_categories WHERE product_id = ?', [$id]);
            foreach ($categories as $c) {
                $this->db->query('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)', [$id, $c]);
            }
            ProductsHooks::syncPrices($this->db, $id, $groupPrices);
            Translations::sync($this->db, 'products', $id, $translations);
            $this->mapMembers('item', $members, $id);
            $done++;
        }
        $this->count('productos', $done);
        $this->count('filas de producto de Joomla (todos los idiomas)', count($rows));
        if ($kept) {
            $this->count('productos cambiados en Nebula (no se tocan)', $kept);
        }
        if ($noPrice) {
            $this->warn("$noPrice productos sin precio en Joomla: importados a 0");
        }
        if ($foreign) {
            $this->warn("$foreign productos sin versión en " . $this->locales->default() . ' (o sin código ni referencia que la enlace): sus textos principales están en otro idioma');
        }
        $this->note(sprintf('Precios de Joomla %s IVA: %s', !empty($this->opts['prices_with_tax']) ? 'con' : 'sin',
            !empty($this->opts['prices_with_tax']) ? 'se importan tal cual' : 'se les suma el IVA de cada producto (en Nebula van con IVA)'));
        foreach (['botiga_brands' => 'marcas', 'botiga_docs' => 'documentos de producto', 'botiga_documents' => 'documentos de producto',
                     'botiga_favorites' => 'favoritos', 'botiga_savedCarts' => 'carritos guardados', 'botiga_discounts' => 'descuentos por cantidad'] as $t => $label) {
            if ($this->hasTable($t) && ($n = (int) $this->src->fetchValue("SELECT COUNT(*) FROM {$this->t($t)}")) > 0) {
                $this->warn("$n $label ($this->p$t): Nebula no los tiene, no se importan");
            }
        }
    }

    /**
     * Resumen y descripción de una fila de botiga_items. Muchas tiendas solo rellenan la descripción corta
     * (s_description), a veces con mucho texto: entonces es la descripción, y el resumen solo si es breve.
     * @return array{summary:?string,description:string}
     */
    private function texts(array $row): array
    {
        static $cache = [];
        $id = (int) $row['id'];
        if (isset($cache[$id])) {
            return $cache[$id];
        }
        $short = $this->plain((string) $row['s_description']);
        $description = $this->html((string) $row['description'], 'products');
        if ($description === '' && mb_strlen($short) > 300) {
            return $cache[$id] = ['summary' => null, 'description' => $this->html((string) $row['s_description'], 'products')];
        }
        return $cache[$id] = ['summary' => $short !== '' ? mb_substr($short, 0, 500) : null, 'description' => $description];
    }

    /** Precios de botiga_items_prices (Joomla 4): [itemId => [grupo => precio]] */
    private function pricesTable(): array
    {
        $out = [];
        foreach ($this->src->fetchAll("SELECT itemId, usergroup, price FROM {$this->t('botiga_items_prices')}") as $r) {
            $out[(int) $r['itemId']][(int) $r['usergroup']] = (float) $r['price'];
        }
        return $out;
    }

    /** Precios de items.price (botiga 1.0): {"usergroup":["2"],"pricing":["78.72"]} o un número. */
    private function itemPrices(array $row): array
    {
        $raw = $row['price'] ?? '';
        if (is_numeric($raw)) {
            return ['default' => (float) $raw];
        }
        $json = Text::json($raw);
        $out = [];
        foreach ((array) ($json['usergroup'] ?? []) as $i => $g) {
            $value = $json['pricing'][$i] ?? null;
            if (is_numeric($value)) {
                $out[is_numeric($g) ? (int) $g : (string) $g] = (float) $value;
            }
        }
        return $out;
    }

    private function productImages(array $row): array
    {
        $paths = [];
        foreach (['image1', 'image2', 'image3', 'image4', 'image5'] as $c) {
            if (isset($row[$c]) && trim((string) $row[$c]) !== '') {
                $paths[] = trim((string) $row[$c]);
            }
        }
        if (isset($row['images'])) {
            $json = Text::json($row['images']);
            array_walk_recursive($json, function ($v) use (&$paths) {
                if (is_string($v) && preg_match('/\.(jpe?g|png|gif|webp)$/i', $v)) {
                    $paths[] = trim($v);
                }
            });
        }
        $ids = [];
        foreach (array_unique($paths) as $path) {
            if (($id = $this->mediaFor($path, 'products', 'images')) !== null) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function taxRate(int $type): float
    {
        return (float) match ($type) {
            2 => $this->params['iva_reduit'] ?? 10,
            3 => $this->params['iva_superreduit'] ?? 4,
            default => $this->params['iva'] ?? 21,
        };
    }

    private function withTax(float $price, float $rate): float
    {
        return Money::round(!empty($this->opts['prices_with_tax']) ? $price : $price * (1 + $rate / 100));
    }

    // ---------------------------------------------------------------- clientes

    private function customers(): void
    {
        $groupsOf = [];
        foreach ($this->src->fetchAll("SELECT user_id, group_id FROM {$this->t('user_usergroup_map')}") as $r) {
            $groupsOf[(int) $r['user_id']][] = (int) $r['group_id'];
        }
        $botiga = $this->botigaUsers();
        $addresses = [];
        if ($this->hasTable('botiga_user_address')) {
            foreach ($this->src->fetchAll("SELECT * FROM {$this->t('botiga_user_address')} ORDER BY activa DESC, id") as $a) {
                $addresses[(int) $a['userid']][] = $this->address($a['name'], $a['adreca'], $a['cp'], $a['poblacio'], null, $a['pais'], $a['telefon'], $a['ref_externa'] ?? null);
            }
        }
        $skip = array_map('intval', $this->opts['skip_groups'] ?? [7, 8]);
        $withOrders = $this->hasTable('botiga_comandes')
            ? array_flip(array_map('intval', array_column($this->src->fetchAll("SELECT DISTINCT userid FROM {$this->t('botiga_comandes')}"), 'userid')))
            : [];
        $done = $kept = $admins = $dupes = $noPassword = $never = $neverSkipped = 0;
        $seen = $invalid = $countries = [];
        foreach ($this->src->fetchAll("SELECT * FROM {$this->t('users')} ORDER BY id") as $u) {
            $uid = (int) $u['id'];
            if (array_intersect($groupsOf[$uid] ?? [], $skip)) {
                $admins++;
                continue;
            }
            $email = mb_strtolower(trim((string) $u['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email; // suelen ser altas de robots (s.a.mmy.@gmail.com)
                continue;
            }
            if (isset($seen[$email])) {
                $dupes++;
                continue;
            }
            $seen[$email] = true;
            $existing = $this->map->get('customer', $uid);
            if ($existing === null && ($own = $this->customerByEmail($email)) !== null) {
                // Ya era cliente de Nebula (se registró o compró aquí): se enlaza, pero no se toca su ficha
                $this->map->set('customer', $uid, $own);
                $kept++;
                continue;
            }
            if ($existing !== null && $this->map->changedSince('customer', $uid, 'customers')) {
                $kept++;
                continue;
            }
            // Nunca ha entrado ni ha comprado: muchas veces, altas de robots
            if ($this->date($u['lastvisitDate']) === null && !isset($withOrders[$uid])) {
                $never++;
                if (!empty($this->opts['skip_never_logged'])) {
                    $neverSkipped++;
                    continue;
                }
            }
            $b = $botiga[$uid] ?? [];
            $hash = trim((string) $u['password']);
            $noPassword += $hash === '' ? 1 : 0;
            $list = $addresses[$uid] ?? [];
            if (!$list && trim((string) ($b['adreca'] ?? '')) !== '') {
                $list[] = $this->address($b['nombre'] ?? $u['name'], $b['adreca'], $b['cp'] ?? '', $b['poblacio'] ?? '', $b['provincia'] ?? null, $b['pais'] ?? null, $b['telefon'] ?? null);
            }
            $values = [
                'email' => $email,
                'password_hash' => $hash !== '' ? $hash : null,
                'name' => mb_substr(trim((string) $u['name']) ?: trim((string) ($b['nombre'] ?? '')) ?: $email, 0, 150),
                'company' => $this->nullable($b['nom_empresa'] ?? null, 150),
                'vat_id' => $this->nullable($b['cif'] ?? null, 30),
                'phone' => $this->nullable($b['telefon'] ?? null, 30),
                'group_id' => $this->groupFor($groupsOf[$uid] ?? []),
                'addresses' => $list ? json_encode($list, JSON_UNESCAPED_UNICODE) : null,
                'locale' => $this->locale((string) (Text::json($u['params'])['language'] ?? '')) ?: null,
                'equivalence_surcharge' => !empty($b['re_equiv']) ? 1 : 0,
                'active' => (int) $u['block'] === 0 ? 1 : 0,
                'last_login' => $this->date($u['lastvisitDate']),
                'created_at' => $this->date($u['registerDate']) ?? date('Y-m-d H:i:s'),
            ];
            if ($values['locale'] !== null && !$this->locales->has($values['locale'])) {
                $values['locale'] = null;
            }
            $this->save('customers', 'customer', $uid, $values, $existing);
            $done++;
            $c = $list[0]['country'] ?? '—';
            $countries[$c] = ($countries[$c] ?? 0) + 1;
        }
        arsort($countries);
        if ($countries) {
            $this->note('Países de los clientes: ' . implode(', ', array_map(fn ($c, $n) => "$c $n", array_keys(array_slice($countries, 0, 8, true)), array_slice($countries, 0, 8, true)))
                . ' (si sale uno extraño, puede ser un valor por defecto de Joomla: --country=id:ES)');
        }
        if ($neverSkipped) {
            $this->note("$neverSkipped usuarios que nunca habían entrado ni comprado no se importan (--skip-never-logged)");
        } elseif ($never) {
            $this->warn("$never clientes no han entrado nunca ni tienen pedidos (a menudo altas de robots): --skip-never-logged para no importarlos");
        }
        $this->count('clientes', $done);
        $legacy = (int) $this->db->fetchValue("SELECT COUNT(*) FROM customers WHERE password_hash IS NOT NULL AND password_hash NOT LIKE '$2%' AND password_hash NOT LIKE '\$argon2%'");
        $this->note("Contraseñas de Joomla: bcrypt valen tal cual; $legacy en formato antiguo (md5/phpass) se pasan a bcrypt al entrar (shop.legacy_passwords)");
        if ($kept) {
            $this->count('clientes que ya estaban o han cambiado en Nebula (no se tocan)', $kept);
        }
        if ($admins) {
            $this->note("$admins administradores de Joomla no se importan como clientes");
        }
        if ($dupes) {
            $this->warn("$dupes usuarios con el correo repetido: solo se importa el primero");
        }
        if ($invalid) {
            $this->warn(count($invalid) . ' usuarios con un correo no válido, no se importan (p. ej. ' . implode(', ', array_slice($invalid, 0, 3)) . ')');
        }
        if ($noPassword) {
            $this->warn("$noPassword clientes sin contraseña: tendrán que recuperarla");
        }
        if ($this->hasTable('botiga_users')) {
            $orphans = (int) $this->src->fetchValue("SELECT COUNT(*) FROM {$this->t('botiga_users')} b WHERE NOT EXISTS (SELECT 1 FROM {$this->t('users')} u WHERE u.id = b.userid)");
            if ($orphans) {
                $this->note("$orphans fichas de botiga_users sin usuario de Joomla: no se importan (no podían entrar)");
            }
        }
    }

    /** La ficha de botiga_users más completa de cada usuario (puede haber varias). */
    private function botigaUsers(): array
    {
        if (!$this->hasTable('botiga_users')) {
            return [];
        }
        $best = [];
        foreach ($this->src->fetchAll("SELECT * FROM {$this->t('botiga_users')} ORDER BY id") as $b) {
            $uid = (int) $b['userid'];
            $score = count(array_filter($b, fn ($v) => $v !== null && trim((string) $v) !== '' && $v !== '0'));
            if (!isset($best[$uid]) || $score >= $best[$uid][0]) {
                $best[$uid] = [$score, $b];
            }
        }
        return array_map(fn ($x) => $x[1], $best);
    }

    private function customerByEmail(string $email): ?int
    {
        $id = $this->db->fetchValue('SELECT id FROM customers WHERE email = ?', [$email]);
        return $id !== null ? (int) $id : null;
    }

    private function address(mixed $name, mixed $address, mixed $postcode, mixed $city, mixed $province, mixed $country, mixed $phone, mixed $label = null): array
    {
        return [
            'label' => mb_substr(trim((string) $label), 0, 60),
            'name' => mb_substr(trim((string) $name), 0, 150),
            'address' => mb_substr(trim((string) $address), 0, 255),
            'postcode' => mb_substr(trim((string) $postcode), 0, 20),
            'city' => mb_substr(trim((string) $city), 0, 100),
            'province' => mb_substr(trim((string) $province), 0, 100),
            'country' => $this->country($country),
            'phone' => mb_substr(trim((string) $phone), 0, 30),
        ];
    }

    // ---------------------------------------------------------------- pedidos

    private function orders(): void
    {
        if (!$this->hasTable('botiga_comandes')) {
            return;
        }
        $lines = [];
        foreach ($this->src->fetchAll("SELECT * FROM {$this->t('botiga_comandesDetall')} ORDER BY id") as $l) {
            $lines[(int) $l['idComanda']][] = $l;
        }
        $botiga = $this->botigaUsers();
        $names = [];
        foreach ($this->src->fetchAll("SELECT id, name, ref, sku FROM {$this->t('botiga_items')}") as $i) {
            $names[(int) $i['id']] = $i;
        }
        $prefix = (string) ($this->opts['order_prefix'] ?? 'J');
        $done = $empty = $kept = $deleted = 0;
        foreach ($this->src->fetchAll("SELECT * FROM {$this->t('botiga_comandes')} ORDER BY id") as $o) {
            $jid = (int) $o['id'];
            $items = $lines[$jid] ?? [];
            if (!$items && (float) $o['total'] <= 0) {
                $empty++;
                continue; // carrito que nunca llegó a pedido
            }
            $existing = $this->map->get('order', $jid);
            if ($existing !== null && $this->map->changedSince('order', $jid, 'orders')) {
                $kept++;
                continue;
            }
            $rate = (float) $o['iva_percent'] > 0 ? (float) $o['iva_percent'] : (float) ($this->params['iva'] ?? 21);
            $created = $this->date($o['data']) ?? date('Y-m-d H:i:s');
            [$status, $payment] = $this->orderStatus((int) $o['status'], $created);

            $customerId = (int) $o['userid'] > 0 ? $this->map->get('customer', (int) $o['userid']) : null;
            $customer = $customerId !== null ? $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$customerId]) : null;
            $b = $botiga[(int) $o['userid']] ?? [];
            $addr = Text::json($customer['addresses'] ?? null)[0] ?? [];

            $orderItems = [];
            $subtotal = $discount = 0.0;
            foreach ($items as $l) {
                $lineRate = (float) ($l['iva_percent'] ?? 0) > 0 ? (float) $l['iva_percent'] : $rate;
                $unit = $this->withTax((float) $l['price'], $lineRate);
                $qty = max(1, (int) $l['qty']);
                $lineDiscount = Money::round($unit * $qty * (float) ($l['dte_linia'] ?? 0) / 100);
                $item = $names[(int) $l['idItem']] ?? null;
                $deleted += $item === null ? 1 : 0;
                $productId = $this->map->get('item', (int) $l['idItem']);
                $orderItems[] = [
                    'product_id' => $productId !== null && $this->db->fetchValue('SELECT 1 FROM products WHERE id = ?', [$productId]) ? $productId : null,
                    'sku' => $item !== null ? (mb_substr(trim((string) ($item['ref'] ?: $item['sku'])), 0, 64) ?: null) : null,
                    'name' => $item !== null ? mb_substr($this->clean($item['name']), 0, 200) : 'Producte de Joomla #' . (int) $l['idItem'],
                    'qty' => $qty,
                    'unit_price' => $unit,
                    'discount' => $lineDiscount,
                    'tax_rate' => $lineRate,
                    'total' => Money::round($unit * $qty - $lineDiscount),
                ];
                $subtotal += $unit * $qty;
                $discount += $lineDiscount;
            }
            $discount += $this->withTax((float) ($o['discount'] ?? 0), $rate);
            $shipping = $this->withTax((float) $o['shipment'], $rate);
            $surcharge = Money::round((float) ($o['re_total'] ?? 0));
            // El total es el que cobró Joomla; el IVA, el suyo o el incluido en ese total
            $total = (float) $o['total'] > 0 ? Money::round((float) $o['total']) : Money::round($subtotal - $discount + $shipping + $surcharge);
            $tax = (float) $o['iva_total'] > 0 ? Money::round((float) $o['iva_total']) : Money::includedTax($total - $surcharge, $rate);
            $values = [
                'number' => $prefix . str_pad((string) $jid, 6, '0', STR_PAD_LEFT),
                'token' => bin2hex(random_bytes(16)),
                'customer_id' => $customerId,
                'group_id' => $customer['group_id'] ?? null,
                'email' => (string) ($customer['email'] ?? mb_strtolower(trim((string) ($o['mail_empresa'] ?? $b['mail_empresa'] ?? '')))),
                'phone' => $this->nullable($o['telefon'] ?? $customer['phone'] ?? null, 30),
                'status' => $status,
                'payment_status' => $payment,
                'payment_gateway' => $this->gateway((string) $o['processor']),
                'payment_name' => $this->nullable($o['processor'], 150),
                'bill_name' => mb_substr(trim((string) ($o['name'] ?? '')) ?: (string) ($customer['name'] ?? ''), 0, 150),
                'bill_company' => $this->nullable($o['nom_empresa'] ?? $customer['company'] ?? null, 150),
                'bill_vat_id' => $this->nullable($customer['vat_id'] ?? null, 30),
                'bill_address' => mb_substr(trim((string) ($o['adreca'] ?? '')) ?: (string) ($addr['address'] ?? ''), 0, 255),
                'bill_city' => mb_substr(trim((string) ($o['poblacio'] ?? '')) ?: (string) ($addr['city'] ?? ''), 0, 100),
                'bill_postcode' => mb_substr(trim((string) ($o['cp'] ?? '')) ?: (string) ($addr['postcode'] ?? ''), 0, 20),
                'bill_province' => $this->nullable($addr['province'] ?? null, 100),
                'bill_country' => isset($o['pais']) && trim((string) $o['pais']) !== '' ? $this->country($o['pais']) : ((string) ($addr['country'] ?? '') ?: $this->country(null)),
                'subtotal' => Money::round($subtotal),
                'discount' => Money::round($discount),
                'shipping_cost' => $shipping,
                'tax_total' => $tax,
                'surcharge_total' => $surcharge,
                'total' => $total,
                'taxes' => json_encode([['rate' => $rate, 'base' => Money::round($total - $surcharge - $tax), 'tax' => $tax]
                    + ($surcharge > 0 ? ['surcharge_rate' => (float) ($o['re_percent'] ?? 0), 'surcharge' => $surcharge] : [])]),
                'promotions' => json_encode([]),
                'notes' => $this->nullable($o['observa'] ?? null, 1000),
                'ip' => $this->nullable($o['ip'] ?? null, 45),
                'stock_restored' => $status === 'cancelled' ? 1 : 0,
                'created_at' => $created,
                'paid_at' => $payment === 'paid' ? $created : null,
            ];
            if ($existing !== null) {
                unset($values['token']); // el enlace del pedido no cambia
            }
            $id = $this->save('orders', 'order', $jid, $values, $existing);
            $this->db->query('DELETE FROM order_items WHERE order_id = ?', [$id]);
            foreach ($orderItems as $item) {
                $this->db->insert('order_items', $item + ['order_id' => $id]);
            }
            $this->db->query('DELETE FROM order_history WHERE order_id = ?', [$id]);
            $this->db->insert('order_history', ['order_id' => $id, 'status' => $status, 'created_at' => $created,
                'note' => sprintf('Importat de Joomla (comanda #%d, estat %d)', $jid, (int) $o['status'])]);
            $done++;
        }
        $this->count('pedidos', $done);
        if ($empty) {
            $this->note("$empty carritos de Joomla sin líneas ni importe: no se importan");
        }
        if ($deleted) {
            $this->warn("$deleted líneas de pedido de productos que ya no existían en Joomla: con el nombre «Producte de Joomla #id»");
        }
        if ($kept) {
            $this->count('pedidos cambiados en Nebula (no se tocan)', $kept);
        }
        $this->note('Estados: 1 (sin confirmar) → cancelado; 2 (pendiente de pago) → pendiente, o cancelado si tiene más de 30 días; 3 y 4 → pagado');
    }

    /** @return array{0:string,1:string} [status, payment_status] */
    private function orderStatus(int $status, string $created): array
    {
        return match ($status) {
            3, 4 => ['paid', 'paid'],
            2 => strtotime($created) < time() - 30 * 86400 ? ['cancelled', 'failed'] : ['pending', 'pending'],
            default => ['cancelled', 'pending'],
        };
    }

    private function gateway(string $processor): ?string
    {
        $p = mb_strtolower(trim($processor));
        return match (true) {
            $p === '' => null,
            str_contains($p, 'redsys'), str_contains($p, 'targeta'), str_contains($p, 'tarjeta'), str_contains($p, 'bizum') => 'redsys',
            str_contains($p, 'paypal') => 'paypal',
            str_contains($p, 'transf') => 'transfer',
            default => mb_substr(Text::slug($p, 30), 0, 30),
        };
    }

    // ---------------------------------------------------------------- portes y cupones

    /**
     * botiga_shipments → una forma de envío por grupo de clientes con una regla por fila (primero las de código
     * postal, que son más concretas, luego las de país). Los importes de Joomla van sin IVA.
     */
    private function shipping(): void
    {
        if (!$this->hasTable('botiga_shipments')) {
            return;
        }
        $rate = (float) \Nebula\Shop\ShopConfig::get('shop.shipping_tax_rate', 21);
        $byGroup = [];
        foreach ($this->src->fetchAll("SELECT * FROM {$this->t('botiga_shipments')} WHERE published = 1 ORDER BY type = 1 DESC, ordering, id") as $s) {
            $countries = array_values(array_filter(array_map(fn ($c) => (int) $c > 0 ? $this->country($c) : null, preg_split('/[;,]/', (string) $s['country']) ?: [])));
            $rule = [
                'countries' => (int) $s['conditional'] === 0 ? implode(',', $countries) : '',
                'postcodes' => (int) $s['type'] === 1 && trim((string) $s['min']) !== '' ? trim((string) $s['min']) . '-' . trim((string) ($s['max'] ?: $s['min'])) : '',
                'weight_min' => '', 'weight_max' => '', 'subtotal_min' => '', 'subtotal_max' => '',
                'price' => Money::round((float) $s['total'] * (1 + $rate / 100)),
                'price_per_kg' => '',
                'free_from' => (float) $s['free'] > 0 ? Money::round((float) $s['free'] * (1 + $rate / 100)) : '',
            ];
            if ((int) $s['conditional'] === 1 && $countries) {
                $this->warn("Envío «{$s['name']}»: «países distintos de " . implode(',', $countries) . '» queda como «cualquier país» al final de la lista');
            }
            if (trim((string) $s['operator']) === '%') {
                $this->warn("Envío «{$s['name']}»: en Joomla era un % del pedido; en Nebula queda como importe fijo ({$s['total']}): revísalo");
            }
            if ((int) $s['type'] === 2) {
                $this->warn("Envío «{$s['name']}»: era por peso; revisa la regla");
            }
            $byGroup[(int) ($s['usergroup'] ?? 1)][] = ['name' => (string) $s['name'], 'rule' => $rule, 'last' => (int) $s['conditional'] === 1];
        }
        $n = 0;
        foreach ($byGroup as $group => $rules) {
            usort($rules, fn ($a, $b) => $a['last'] <=> $b['last']); // «resto del mundo» al final
            $groupIds = isset($this->groups[$group]) ? json_encode([(string) $this->groups[$group]]) : null;
            $values = [
                'name' => count($byGroup) > 1 && $groupIds !== null ? 'Enviament (' . $group . ')' : 'Enviament',
                'description' => 'Importat de Joomla: ' . implode(' · ', array_column($rules, 'name')),
                'group_ids' => $groupIds,
                'rules' => json_encode(array_column($rules, 'rule'), JSON_UNESCAPED_UNICODE),
                'active' => 1,
            ];
            $existing = $this->map->get('shipping', $group);
            $this->save('shipping_methods', 'shipping', $group, $values, $existing);
            $n++;
        }
        $this->count('formas de envío', $n);
        if ($n) {
            $this->note('Portes: revisa las reglas en Configuración → Formas de envío (y el Simulador de portes)');
        }
    }

    private function coupons(): void
    {
        if (!$this->has('botiga_coupons', 'valor')) {
            return; // la tabla de la 1.0 no tiene importe
        }
        $n = 0;
        foreach ($this->src->fetchAll("SELECT * FROM {$this->t('botiga_coupons')}") as $c) {
            $code = mb_strtoupper(trim((string) $c['coupon']));
            if ($code === '') {
                continue;
            }
            $values = [
                'name' => $code, 'code' => mb_substr($code, 0, 40),
                'type' => (int) $c['tipus'] === 1 ? 'fixed' : 'percent',
                'value' => (int) $c['tipus'] === 1 ? Money::round((float) $c['valor'] * 1.21) : (float) $c['valor'],
                'ends_at' => $this->date(($c['finishDate'] ?? '') . ' 23:59:59'),
                'active' => (int) ($c['published'] ?? 1) === 1 ? 1 : 0,
            ];
            $existing = $this->map->get('coupon', (int) $c['id']) ?? (($id = $this->db->fetchValue('SELECT id FROM promotions WHERE code = ?', [$values['code']])) !== null ? (int) $id : null);
            $this->save('promotions', 'coupon', (int) $c['id'], $values, $existing);
            $n++;
        }
        $this->count('cupones', $n);
    }

    // ---------------------------------------------------------------- utilidades

    /** Inserta o actualiza y apunta la correspondencia. */
    private function save(string $table, string $entity, string|int $sourceId, array $values, ?int $existing): int
    {
        if ($existing !== null && $this->db->fetchValue("SELECT 1 FROM $table WHERE id = ?", [$existing])) {
            $this->db->update($table, $values, 'id', $existing);
            $id = $existing;
        } else {
            if ($table === 'products' || $table === 'categories') {
                $values['slug'] = 'import-' . bin2hex(random_bytes(6)); // provisional: el definitivo necesita el id
            }
            $id = (int) $this->db->insert($table, $values);
        }
        $this->map->set($entity, $sourceId, $id);
        $this->written[$entity][] = $sourceId;
        return $id;
    }

    /**
     * Agrupa las filas de un mismo registro en varios idiomas por una clave (código de Factusol, referencia…).
     * Dentro de un grupo, una fila por idioma: si un idioma se repite, la repetida va sola.
     * @return array<string,list<array>> clave estable => filas
     */
    private function groupByLanguage(array $rows, \Closure $key): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $k = $key($r) ?? 'id:' . $r['id'];
            $locale = $this->locale((string) $r['language']) ?? $this->locales->default();
            if (isset($groups[$k][$locale])) {
                $k = 'id:' . $r['id'];
            }
            $groups[$k][$locale] = $r;
        }
        return array_map('array_values', $groups);
    }

    /** La fila del idioma por defecto (o la primera) es la principal: sus textos son los de la ficha. */
    private function mainRow(array $members): array
    {
        foreach ($members as $r) {
            if (($this->locale((string) $r['language']) ?? $this->locales->default()) === $this->locales->default()) {
                return $r;
            }
        }
        return $members[0];
    }

    private function mapMembers(string $entity, array $members, int $id): void
    {
        foreach ($members as $r) {
            $this->map->set($entity, (int) $r['id'], $id);
        }
    }

    /** Repeater de traducciones a partir de las filas de los otros idiomas. */
    private function translations(string $table, array $members, array $main, int $id, \Closure $texts): array
    {
        $rows = [];
        foreach ($members as $r) {
            $locale = $this->locale((string) $r['language']);
            if ($r === $main || $locale === null || $this->locales->isDefault($locale) || !$this->locales->has($locale)) {
                continue;
            }
            $rows[] = ['locale' => $locale] + $texts($r);
        }
        $out = Translations::normalize($this->db, $table, $rows, (string) $id, $this->locales);
        return is_array($out) ? $out : [];
    }

    /** "ca-ES" → "ca"; "*" o vacío → null (todos los idiomas: el de por defecto). */
    private function locale(string $tag): ?string
    {
        $tag = strtolower(trim($tag));
        return preg_match('/^([a-z]{2})(-[a-z]{2})?$/', $tag, $m) ? $m[1] : null;
    }

    private function loadCountries(): void
    {
        if ($this->hasTable('botiga_countries')) {
            foreach ($this->src->fetchAll("SELECT country_id, country_code FROM {$this->t('botiga_countries')}") as $c) {
                $iso = Countries::iso2((string) $c['country_code']);
                if ($iso !== null) {
                    $this->countries[(int) $c['country_id']] = $iso;
                }
            }
        }
        foreach ((array) ($this->opts['countries'] ?? []) as $id => $iso) {
            $this->countries[(int) $id] = strtoupper((string) $iso);
        }
    }

    /** País de Joomla (id de botiga_countries, ISO2 o ISO3) → ISO2; si no se sabe, el de por defecto. */
    private function country(mixed $value): string
    {
        $v = trim((string) $value);
        $default = strtoupper((string) \Nebula\Shop\ShopConfig::get('shop.default_country', 'ES'));
        if ($v === '' || $v === '0') {
            return $default;
        }
        if (ctype_digit($v)) {
            return $this->countries[(int) $v] ?? $default;
        }
        return strlen($v) === 2 ? strtoupper($v) : (Countries::iso2($v) ?? $default);
    }

    /**
     * Imagen de Joomla (ruta relativa a su carpeta, p. ej. images/x.jpg) → media de Nebula. Una sola copia por
     * fichero aunque la usen varios productos o idiomas.
     */
    private function mediaFor(string $path, string $module, string $field): ?int
    {
        $path = ltrim(str_replace('\\', '/', urldecode(trim($path))), '/');
        $path = preg_replace('~#.*$~', '', $path) ?? $path; // images/x.jpg#joomlaImage://… (Joomla 4)
        if ($path === '' || preg_match('~^(https?:|data:)~i', $path)) {
            return null;
        }
        $key = $module . ':' . $path;
        if (($id = $this->map->get('media', $key)) !== null) {
            return $id;
        }
        $dir = rtrim((string) ($this->opts['joomla_dir'] ?? ''), '/');
        if ($dir === '') {
            $this->report['missing_dir'] = true;
            return null;
        }
        $file = $dir . '/' . $path;
        if (!is_file($file)) {
            $this->report['missing_images'][$path] = true;
            return null;
        }
        if (!empty($this->opts['dry_run'])) {
            if (!isset($this->report['dry_images'][$key])) {
                $this->report['dry_images'][$key] = true;
                $this->report['counts']['imágenes'] = ($this->report['counts']['imágenes'] ?? 0) + 1;
            }
            return null;
        }
        try {
            $info = $this->media->importFile($file, basename($path), $module, $field);
        } catch (MediaException $e) {
            $this->warn("Imagen no válida, no se importa: $path");
            return null;
        }
        $this->map->set('media', $key, (int) $info['id']);
        $this->report['counts']['imágenes'] = ($this->report['counts']['imágenes'] ?? 0) + 1;
        return (int) $info['id'];
    }

    /** HTML de Joomla para un richtext: limpio, con sus imágenes importadas; texto sin etiquetas → párrafos. */
    private function html(string $value, string $module): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/<[a-z][^>]*>/i', $value)) {
            $value = implode('', array_map(fn ($p) => '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8'), false) . '</p>',
                preg_split("/\R{2,}/", $value) ?: [$value]));
        }
        $value = (string) preg_replace_callback('~(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])~i', function ($m) use ($module) {
            $id = $this->mediaFor(preg_replace('~^(?:https?://[^/]+)?/~i', '', $m[2]) ?? $m[2], $module, 'description');
            return $id !== null ? $m[1] . 'index.php?r=media&id=' . $id . $m[3] : $m[0];
        }, $value);
        $clean = HtmlSanitizer::clean($value);
        return trim(HtmlSanitizer::toText($clean)) === '' && !str_contains($clean, '<img') ? '' : $clean;
    }

    private function plain(string $value): string
    {
        return trim(HtmlSanitizer::toText($value));
    }

    private function clean(mixed $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $v = trim((string) $value);
        return $v !== '' && $v !== '0' ? mb_substr($v, 0, $max) : null;
    }

    private function date(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v === '' || str_starts_with($v, '0000') || strtotime($v) === false ? null : date('Y-m-d H:i:s', (int) strtotime($v));
    }

    private function count(string $label, int $n): void
    {
        $this->report['counts'][$label] = ($this->report['counts'][$label] ?? 0) + $n;
    }

    private function warn(string $message): void
    {
        $this->report['warnings'][] = $message;
    }

    private function note(string $message): void
    {
        $this->report['notes'][] = $message;
    }
}
