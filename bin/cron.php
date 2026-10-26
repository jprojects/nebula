#!/usr/bin/env php
<?php
/**
 * Tareas periódicas de la tienda. Programarlo cada hora (o más a menudo):
 *
 *   0 * * * * php /ruta/a/nebula/bin/cron.php --quiet
 *
 * - Cancela los pedidos sin pagar más antiguos que shop.pending_ttl_hours (tarjeta, PayPal…) y
 *   shop.transfer_ttl_days (transferencia, 0 = nunca) y devuelve sus unidades.
 * - Borra los enlaces de recuperar contraseña caducados.
 *
 * Necesita 'base_url' en config.local.php si se envían correos con enlaces.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';

use Nebula\Core\App;
use Nebula\Core\Log;
use Nebula\Shop\Services;

$quiet = in_array('--quiet', $argv, true);
try {
    $app = new App($config); // fachada de correo y log
    $shop = new Services($app->db);
    $cancelled = $shop->orders()->expirePending(
        (int) ($config['shop']['pending_ttl_hours'] ?? 48),
        (int) ($config['shop']['transfer_ttl_days'] ?? 0)
    );
    $tokens = $app->db->query('DELETE FROM customer_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)])->rowCount();
    if ($cancelled > 0) {
        Log::channel('shop')->info('Cron: {n} pedidos sin pagar cancelados', ['n' => $cancelled]);
    }
    if (!$quiet) {
        printf("%d pedido(s) sin pagar cancelado(s), %d enlace(s) caducado(s) borrado(s).%s", $cancelled, $tokens, PHP_EOL);
    }
} catch (Throwable $e) {
    Log::error('Cron de la tienda fallido', ['exception' => $e]);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
