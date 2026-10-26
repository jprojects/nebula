<?php
declare(strict_types=1);

// Gestión de la tienda (backoffice)
$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';

(new Nebula\Core\App($config))->run();
