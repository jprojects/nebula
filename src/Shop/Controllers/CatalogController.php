<?php
declare(strict_types=1);

namespace Nebula\Shop\Controllers;

use Nebula\Core\HttpException;
use Nebula\Shop\Catalog;
use Nebula\Shop\ShopConfig;

/**
 * Portada, categorías, búsqueda y ficha de producto. Los listados responden también por AJAX
 * (X-Requested-With): devuelven solo la rejilla y la paginación, y store.js cambia la URL con history.pushState.
 */
final class CatalogController extends ShopController
{
    public function home(): void
    {
        $catalog = $this->services->catalog();
        $group = $this->shop->group();
        $featured = $catalog->listing(['featured' => true, 'size' => 8], $group)['items'];
        if (!$featured) {
            $featured = $catalog->listing(['sort' => 'newest', 'size' => 8], $group)['items'];
        }
        $this->shop->html('home', [
            'featured' => $featured,
            'categories' => array_values(array_filter($catalog->categories(), fn ($c) => $c['parent_id'] === null && $c['in_menu'])),
        ] + $this->meta(ShopConfig::shopName()));
    }

    public function category(string $slug): void
    {
        $catalog = $this->services->catalog();
        $category = $catalog->categoryBySlug($slug) ?? throw new HttpException(404);
        $this->listing($category, [
            'category' => $category['id'],
        ], $this->view->urls->category($slug), $category['meta_title'] ?: $category['name'], $category['meta_description']);
    }

    public function search(): void
    {
        $q = mb_substr($this->query('q'), 0, 100);
        $this->listing(null, ['q' => $q], $this->view->url('/buscar'), $this->t('search.title_q', ['q' => $q]), null, $q);
    }

    /** Sugerencias del buscador de la cabecera: JSON con los 6 primeros. */
    public function suggest(): void
    {
        $q = mb_substr($this->query('q'), 0, 100);
        if (mb_strlen($q) < 2) {
            $this->shop->json(['ok' => true, 'items' => []]);
            return;
        }
        $res = $this->services->catalog()->listing(['q' => $q, 'size' => 6, 'sort' => 'name'], $this->shop->group());
        $this->shop->json(['ok' => true, 'total' => $res['total'], 'items' => array_map(fn ($p) => [
            'name' => $p['name'],
            'url' => $this->view->urls->product($p['slug']),
            'image' => $this->view->img($p['cover'], true),
            'price' => $this->view->money($p['price']),
        ], $res['items']), 'all' => $this->view->url('/buscar', ['q' => $q])]);
    }

    public function product(string $slug): void
    {
        $catalog = $this->services->catalog();
        $group = $this->shop->group();
        $product = $catalog->productBySlug($slug, $group) ?? throw new HttpException(404);
        $category = null;
        foreach ($product['categories'] as $id) {
            if (isset($catalog->categories()[$id])) {
                $category = $catalog->categories()[$id];
                break;
            }
        }
        $related = $category !== null
            ? $catalog->listing(['category' => $category['id'], 'size' => 4, 'exclude' => $product['id']], $group)['items']
            : [];
        $this->shop->html('product', [
            'product' => $product,
            'crumbs' => $category !== null ? $catalog->categoryPath($category['id']) : [],
            'related' => $related,
        ] + $this->meta(
            $product['meta_title'] ?: $product['name'],
            $product['meta_description'] ?: ($product['summary'] ?: null),
            $this->view->urls->product($product['slug'])
        ));
    }

    private function listing(?array $category, array $opts, string $baseUrl, string $title, ?string $description, ?string $q = null): void
    {
        $catalog = $this->services->catalog();
        $sort = in_array($this->query('sort'), Catalog::SORTS, true) ? $this->query('sort') : 'featured';
        $page = max(1, (int) $this->query('page', '1'));
        $result = $catalog->listing($opts + [
            'sort' => $sort,
            'page' => $page,
            'size' => (int) ShopConfig::get('shop.page_size', 12),
        ], $this->shop->group());
        $vars = [
            'category' => $category,
            'crumbs' => $category !== null ? $catalog->categoryPath($category['id']) : [],
            'subcategories' => $category !== null ? $catalog->children($category['id']) : [],
            'result' => $result,
            'sort' => $sort,
            'q' => $q,
            'baseUrl' => $baseUrl,
            'query' => array_filter(['q' => $q, 'sort' => $sort !== 'featured' ? $sort : null]),
        ];
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->shop->json([
                'ok' => true,
                'html' => $this->view->partial('partials/product_grid', $vars),
                'count' => $this->t('listing.count', ['n' => $result['total']]),
                'title' => $title . ' · ' . ShopConfig::shopName(),
            ]);
            return;
        }
        $this->shop->html('listing', $vars + $this->meta($title, $description, $baseUrl));
    }
}
