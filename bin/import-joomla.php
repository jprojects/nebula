#!/usr/bin/env php
<?php
/**
 * Importa una tienda de Joomla (componente com_botiga) a esta instalación de Nebula. Ver README, «Migrar desde
 * Joomla». Se puede repetir: lo ya importado se actualiza y lo que se ha cambiado en Nebula se respeta.
 *
 *   php bin/import-joomla.php --db=joomla_tienda --joomla-dir=/ruta/joomla --dry-run
 *   php bin/import-joomla.php --db=joomla_tienda --joomla-dir=/ruta/joomla
 *
 *   --db=NOMBRE            base de datos de Joomla (en el mismo servidor que la de Nebula, salvo --host…)
 *   --host= --port= --user= --pass=   conexión al origen (por defecto, las de 'db'; --pass sola = sin contraseña)
 *   --prefix=jos_          prefijo de las tablas (por defecto se busca el de las de botiga)
 *   --joomla-dir=RUTA      carpeta de Joomla que contiene images/ (sin ella no se importan las imágenes)
 *   --only=products,…      solo esos pasos: languages groups categories products customers orders shipping coupons
 *   --dry-run              lo hace todo y lo deshace: para ver el informe antes de importar
 *   --prices-with-tax      los precios de Joomla ya llevan el IVA (botiga normalmente los guarda sin IVA)
 *   --order-prefix=J       número de los pedidos importados: J000123
 *   --country=2:ES         id de botiga_countries que hay que leer como otro país (se puede repetir)
 *   --skip-never-logged    sin los usuarios que nunca han entrado ni comprado (suelen ser altas de robots)
 *
 * Lo mismo se puede dejar en config.local.php: 'import' => ['joomla' => ['db' => [...], 'joomla_dir' => …, …]].
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';

use Nebula\Core\App;
use Nebula\Core\Database;
use Nebula\Import\JoomlaBotiga;

$o = getopt('', ['db:', 'host:', 'port:', 'user:', 'pass::', 'prefix:', 'joomla-dir:', 'only:', 'dry-run', 'prices-with-tax',
    'order-prefix:', 'country:', 'skip-never-logged', 'help']);
if (isset($o['help'])) {
    preg_match('~/\*\*(.*?)\*/~s', (string) file_get_contents(__FILE__), $m);
    echo preg_replace('~^\s*\* ?~m', '', $m[1] ?? '') . PHP_EOL;
    exit(0);
}
$cfg = (array) ($config['import']['joomla'] ?? []);
$source = array_replace($config['db'], (array) ($cfg['db'] ?? []), array_filter([
    'name' => $o['db'] ?? null, 'host' => $o['host'] ?? null, 'port' => $o['port'] ?? null,
    'user' => $o['user'] ?? null, 'pass' => isset($o['pass']) ? (string) $o['pass'] : null, // --pass o --pass= : vacía
], fn ($v) => $v !== null));
if (($source['name'] ?? '') === ($config['db']['name'] ?? '') && ($source['host'] ?? '') === ($config['db']['host'] ?? '')) {
    fwrite(STDERR, "Indica la base de datos de Joomla con --db=… (no puede ser la de Nebula).\n");
    exit(2);
}
$countries = (array) ($cfg['countries'] ?? []);
foreach ((array) ($o['country'] ?? []) as $pair) {
    if (preg_match('/^(\d+):([A-Za-z]{2})$/', (string) $pair, $m)) {
        $countries[(int) $m[1]] = strtoupper($m[2]);
    }
}
$opts = [
    'prefix' => $o['prefix'] ?? $cfg['prefix'] ?? null,
    'joomla_dir' => $o['joomla-dir'] ?? $cfg['joomla_dir'] ?? null,
    'only' => isset($o['only']) ? array_map('trim', explode(',', (string) $o['only'])) : ($cfg['only'] ?? JoomlaBotiga::STEPS),
    'dry_run' => isset($o['dry-run']),
    'prices_with_tax' => isset($o['prices-with-tax']) || !empty($cfg['prices_with_tax']),
    'order_prefix' => $o['order-prefix'] ?? $cfg['order_prefix'] ?? 'J',
    'skip_groups' => $cfg['skip_groups'] ?? [7, 8],
    'countries' => $countries,
    'skip_never_logged' => isset($o['skip-never-logged']) || !empty($cfg['skip_never_logged']),
];
if ($unknown = array_diff($opts['only'], JoomlaBotiga::STEPS)) {
    fwrite(STDERR, 'Pasos desconocidos: ' . implode(', ', $unknown) . "\n");
    exit(2);
}

try {
    $app = new App($config);
    $started = microtime(true);
    echo ($opts['dry_run'] ? 'Simulando' : 'Importando') . " desde `{$source['name']}`…" . PHP_EOL;
    $report = (new JoomlaBotiga(new Database($source), $app->db, $app->media(), $opts))->run();
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL;
foreach ($report['notes'] as $n) {
    echo "  · $n" . PHP_EOL;
}
echo PHP_EOL . "Importado:" . PHP_EOL;
foreach ($report['counts'] as $label => $n) {
    printf("  %6d  %s%s", $n, $label, PHP_EOL);
}
$missing = array_keys($report['missing_images'] ?? []);
if (!empty($report['missing_dir'])) {
    $report['warnings'][] = 'Sin --joomla-dir: no se han importado las imágenes';
} elseif ($missing) {
    $report['warnings'][] = count($missing) . ' imágenes que no están en --joomla-dir (p. ej. ' . implode(', ', array_slice($missing, 0, 3)) . ')';
}
if ($report['warnings']) {
    echo PHP_EOL . 'Para revisar:' . PHP_EOL;
    foreach ($report['warnings'] as $w) {
        echo "  ! $w" . PHP_EOL;
    }
}
printf('%sHecho en %.1f s.%s', PHP_EOL, microtime(true) - $started, PHP_EOL);
