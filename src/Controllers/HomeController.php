<?php
declare(strict_types=1);

namespace Nebula\Controllers;

final class HomeController extends Controller
{
    public function index(): void
    {
        // En la tienda, el inicio es el Panel (si el usuario lo puede ver)
        $dashboard = $this->app->modules()->get('dashboard');
        if ($dashboard !== null && $this->can($dashboard, 'view')) {
            $this->app->redirect($dashboard->url());
        }
        $this->render('home', [
            'title' => $this->t('home.title'),
            'subtitle' => $this->t('home.welcome', ['name' => $this->user()->name()]),
            'icon' => 'house',
            'breadcrumbs' => $this->crumbs(),
            'groups' => $this->app->modules()->menuFor($this->user()),
        ]);
    }
}
