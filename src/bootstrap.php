<?php
declare(strict_types=1);

define('NB_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Nebula\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = NB_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require NB_ROOT . '/config/config.php';
if (is_file(NB_ROOT . '/config/config.local.php')) {
    $config = array_replace_recursive($config, require NB_ROOT . '/config/config.local.php');
}

date_default_timezone_set($config['timezone'] ?? 'UTC');

$config['log']['level'] ??= !empty($config['debug']) ? 'debug' : 'info';
$config['log']['dir'] ??= NB_ROOT . '/storage/logs';
Nebula\Core\Log::configure($config['log']);

Nebula\Shop\ShopConfig::set($config);

foreach ($config['field_types'] ?? [] as $type => $class) {
    Nebula\Fields\FieldFactory::register($type, $class);
}

return $config;
