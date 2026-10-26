<?php
declare(strict_types=1);

namespace Nebula\Pages;

use Nebula\Controllers\Page;
use Nebula\Shop\Services;
use Nebula\Shop\ShopConfig;

/**
 * Simulador de portes: qué formas de envío y a qué precio vería un cliente para un destino, un peso y un importe.
 * Sirve para comprobar las reglas sin hacer un pedido.
 */
final class ShippingSimulatorPage extends Page
{
    public function index(): void
    {
        $db = $this->app->db;
        $input = [
            'country' => strtoupper(substr((string) ($this->param('country') ?? ShopConfig::get('shop.default_country', 'ES')), 0, 2)),
            'postcode' => mb_substr(trim((string) $this->param('postcode', '')), 0, 20),
            'weight' => (float) str_replace(',', '.', (string) $this->param('weight', '1')),
            'subtotal' => (float) str_replace(',', '.', (string) $this->param('subtotal', '50')),
            'group' => (int) $this->param('group', '0'),
        ];
        $shop = new Services($db);
        $group = $shop->pricing()->group($input['group'] ?: null);
        $this->view([
            'input' => $input,
            'ran' => $this->param('country') !== null,
            'options' => $shop->shipping()->options($input['country'], $input['postcode'], $input['weight'], $input['subtotal'], (int) $group['id']),
            'countries' => $db->fetchAll('SELECT code, name FROM countries ORDER BY sort, name'),
            'groups' => $db->fetchAll('SELECT id, name FROM customer_groups ORDER BY name'),
        ]);
    }
}
