<?php
declare(strict_types=1);

namespace Nebula\Core;

/**
 * Crea o pone al día las tablas del núcleo. Lo usan bin/install.php y la actualización remota
 * (?r=remote&a=migrate), así que todo lo que hace es idempotente: no borra nada.
 */
final class Migrator
{
    /** @var list<string> */
    private array $log = [];

    public function __construct(private readonly Database $db, private readonly string $schema)
    {
    }

    /**
     * @param bool $demo también las tablas y datos de ejemplo (solo en la instalación)
     * @return list<string> pasos realizados
     */
    public function run(bool $demo = false): array
    {
        $this->log = [];
        $this->runSqlFile(NB_ROOT . '/database/schema.sql');
        $this->runSqlFile(NB_ROOT . '/database/shop.sql');
        // Columnas añadidas después de la 0.1.0 (en las instalaciones nuevas ya vienen en shop.sql)
        $this->ensureColumn('products', 'translations', 'JSON NULL AFTER meta_description');
        $this->ensureColumn('categories', 'translations', 'JSON NULL AFTER meta_description');
        $this->ensureColumn('customers', 'equivalence_surcharge', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER locale');
        $this->ensureColumn('orders', 'surcharge_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_total');
        if ($demo) {
            $this->demo();
        }
        $this->seed();
        return $this->log;
    }

    /**
     * Tienda de ejemplo (database/demo.sql + imágenes de database/demo/), solo si todavía no hay productos.
     * Va antes de seed(): trae sus propios países y grupos, con los ids que usan los productos.
     */
    private function demo(): void
    {
        if ((int) $this->db->fetchValue('SELECT COUNT(*) FROM products') > 0) {
            return;
        }
        $this->runSqlFile(NB_ROOT . '/database/demo.sql');
        $target = rtrim((string) \Nebula\Shop\ShopConfig::get('uploads_dir', NB_ROOT . '/storage/uploads'), '/') . '/demo';
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            throw new \RuntimeException("No se puede crear $target");
        }
        foreach (glob(NB_ROOT . '/database/demo/*') ?: [] as $file) {
            copy($file, $target . '/' . basename($file));
        }
        $this->log[] = 'imágenes de ejemplo en ' . $target;
    }

    /** Datos mínimos de una tienda nueva (solo si la tabla está vacía): países, grupo de clientes e idiomas. */
    private function seed(): void
    {
        if ((int) $this->db->fetchValue('SELECT COUNT(*) FROM shop_languages') === 0) {
            // Los de 'shop.locales' (lo que había antes de la 0.3.0), con 'shop.locale' por defecto
            $locales = (array) \Nebula\Shop\ShopConfig::get('shop.locales', ['ca' => 'Català', 'es' => 'Español', 'en' => 'English']);
            $default = (string) \Nebula\Shop\ShopConfig::get('shop.locale', 'ca');
            $sort = 0;
            foreach ($locales as $code => $name) {
                $this->db->insert('shop_languages', ['code' => (string) $code, 'name' => (string) $name, 'is_default' => $code === $default ? 1 : 0, 'sort' => $sort++]);
            }
            $this->log[] = 'idiomas de la tienda';
        }
        if ((int) $this->db->fetchValue('SELECT COUNT(*) FROM countries') === 0) {
            $sort = 0;
            foreach (self::COUNTRIES as $code => $name) {
                $this->db->insert('countries', ['code' => $code, 'name' => $name, 'active' => $code === 'ES' ? 1 : 0, 'sort' => $code === 'ES' ? 0 : ++$sort]);
            }
            $this->log[] = 'países';
        }
        if ((int) $this->db->fetchValue('SELECT COUNT(*) FROM customer_groups') === 0) {
            $this->db->insert('customer_groups', ['code' => 'general', 'name' => 'General', 'is_default' => 1]);
            $this->log[] = 'grupo de clientes por defecto';
        }
    }

    /** Países de Europa (los que se venden se activan en Configuración → Países). */
    private const COUNTRIES = [
        'ES' => 'España', 'AD' => 'Andorra', 'DE' => 'Alemania', 'AT' => 'Austria', 'BE' => 'Bélgica', 'BG' => 'Bulgaria',
        'CY' => 'Chipre', 'HR' => 'Croacia', 'DK' => 'Dinamarca', 'SK' => 'Eslovaquia', 'SI' => 'Eslovenia', 'EE' => 'Estonia',
        'FI' => 'Finlandia', 'FR' => 'Francia', 'GR' => 'Grecia', 'HU' => 'Hungría', 'IE' => 'Irlanda', 'IT' => 'Italia',
        'LV' => 'Letonia', 'LT' => 'Lituania', 'LU' => 'Luxemburgo', 'MT' => 'Malta', 'NL' => 'Países Bajos', 'PL' => 'Polonia',
        'PT' => 'Portugal', 'GB' => 'Reino Unido', 'CZ' => 'República Checa', 'RO' => 'Rumanía', 'SE' => 'Suecia', 'CH' => 'Suiza',
        'NO' => 'Noruega', 'MC' => 'Mónaco',
    ];

    /** Añade una columna si la tabla existe y la columna no. */
    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $tableExists = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->schema, $table]
        );
        if (!$tableExists) {
            return;
        }
        $exists = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->schema, $table, $column]
        );
        if (!$exists) {
            $this->db->pdo()->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', Database::ident($table), Database::ident($column), $definition));
            $this->log[] = "columna $table.$column añadida";
        }
    }

    private function runSqlFile(string $file): void
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (preg_split('/;\s*$/m', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $this->db->pdo()->exec($statement);
            }
        }
        $this->log[] = basename($file);
    }
}
