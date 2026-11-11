<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;
use Nebula\Shop\Payments\PaymentMethods;
use Nebula\Shop\Payments\PaymentService;

/**
 * Servicios de la tienda, creados al pedirlos. Lo usan la tienda (Shop), los hooks de la gestión y bin/cron.php.
 *
 *   $shop = new Services($db);
 *   $shop->orders()->changeStatus(…);
 */
final class Services
{
    private array $cache = [];
    /** Idioma de los textos del catálogo (el de la tienda lo fija al resolver el del visitante) */
    private ?string $locale = null;

    public function __construct(public readonly Database $db)
    {
    }

    public function locales(): Locales
    {
        return $this->cache[__FUNCTION__] ??= new Locales($this->db);
    }

    public function locale(): string
    {
        return $this->locale ?? $this->locales()->default();
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $this->locales()->has($locale) ? $locale : null;
    }

    public function pricing(): Pricing
    {
        return $this->cache[__FUNCTION__] ??= new Pricing($this->db);
    }

    public function promotions(): Promotions
    {
        return $this->cache[__FUNCTION__] ??= new Promotions($this->db);
    }

    public function catalog(): Catalog
    {
        return $this->cache[__FUNCTION__] ??= new Catalog($this->db, $this->pricing(), $this->promotions(), $this);
    }

    public function shipping(): Shipping
    {
        return $this->cache[__FUNCTION__] ??= new Shipping($this->db);
    }

    public function paymentMethods(): PaymentMethods
    {
        return $this->cache[__FUNCTION__] ??= new PaymentMethods($this->db, (array) ShopConfig::get('payments', []));
    }

    public function calculator(): CartCalculator
    {
        return $this->cache[__FUNCTION__] ??= new CartCalculator($this->db, $this->pricing(), $this->promotions(), $this->shipping(), $this->paymentMethods(), $this);
    }

    public function urls(): Urls
    {
        return $this->cache[__FUNCTION__] ??= Urls::fromConfig();
    }

    public function mailer(): ShopMailer
    {
        return $this->cache[__FUNCTION__] ??= new ShopMailer($this->db, $this->urls());
    }

    public function orders(): OrderService
    {
        return $this->cache[__FUNCTION__] ??= new OrderService($this->db, $this->mailer(), $this->promotions());
    }

    public function payments(): PaymentService
    {
        return $this->cache[__FUNCTION__] ??= new PaymentService($this->db, $this->paymentMethods(), $this->orders(), $this->urls());
    }

    public function customers(): Customers
    {
        return $this->cache[__FUNCTION__] ??= new Customers($this->db);
    }
}
