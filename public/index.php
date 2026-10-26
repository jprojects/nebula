<?php
declare(strict_types=1);

// Servidor de PHP (php -S … public/index.php): los ficheros que existen se sirven tal cual
if (PHP_SAPI === 'cli-server') {
    $path = realpath(__DIR__ . rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)));
    if ($path !== false && str_starts_with($path, __DIR__) && is_file($path) && $path !== __FILE__) {
        return false;
    }
    if ($path !== false && is_dir($path) && is_file($path . '/index.php') && $path !== __DIR__) {
        $_SERVER['SCRIPT_NAME'] = substr($path, strlen(__DIR__)) . '/index.php';
        require $path . '/index.php';
        return true;
    }
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';

// La API remota (Cosmo) responde también en la raíz de la tienda: index.php?r=remote&a=…
if (in_array($_GET['r'] ?? null, ['remote', 'status'], true)) {
    (new Nebula\Core\App($config))->run();
    return;
}

(new Nebula\Shop\Shop($config))->run();
