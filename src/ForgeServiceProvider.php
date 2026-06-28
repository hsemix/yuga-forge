<?php

namespace Yuga\Forge;

use Yuga\Forge\Console\MakeResourceCommand;
use Yuga\Interfaces\Application\Application;
use Yuga\Providers\ServiceProvider;
use Yuga\Providers\Shared\MakesCommandsTrait;
use Yuga\Route\Route;

class ForgeServiceProvider extends ServiceProvider
{
    use MakesCommandsTrait;

    public function load(Application $app)
    {
        if ($app->runningInConsole()) {
            $app->singleton('command.forge.make-resource', fn () => new MakeResourceCommand());

            $this->commands('command.forge.make-resource');
        }

        return $app;
    }

    /**
     * Auto-registers dedicated per-record page routes for any resource
     * listed in config('forge.pages') - Forge can't know the consuming
     * app's layout/partials, so it doesn't render anything itself: it
     * re-renders the same view the resource's list page already uses (the
     * "view" entry below), passing the route's {key} (and 'edit' for the
     * edit route) through as $key/$mode - the view just needs to forward
     * those into its existing ylc(...) call (see Resource::mount()'s
     * docblock for what the Resource side of this convention expects):
     *
     *     // config/forge.php
     *     return [
     *         'pages' => [
     *             \App\Live\Admin\ProductsResource::class => [
     *                 'view' => 'admin.products', // same view index() already uses
     *             ],
     *         ],
     *     ];
     *
     *     // resources/views/admin/products.hax.php
     *     @include('admin.partials.header')
     *     <?= ylc('admin.products-resource', array_filter([$key ?? null, $mode ?? null])) ?>
     *     @include('admin.partials.footer')
     *
     * This registers GET {listUrl}/{key} and GET {listUrl}/{key}/edit -
     * listUrl() comes straight off the Resource itself (its own label-based
     * convention, the same one notify() already uses), not duplicated here.
     */
    public function boot(Route $router)
    {
        foreach ((array) config('forge.pages', []) as $resourceClass => $pageConfig) {
            $this->registerResourcePages($router, $resourceClass, (array) $pageConfig);
        }
    }

    protected function registerResourcePages(Route $router, string $resourceClass, array $pageConfig): void
    {
        if (!class_exists($resourceClass) || !isset($pageConfig['view'])) {
            return;
        }

        $instance = (new \ReflectionClass($resourceClass))->newInstanceWithoutConstructor();
        $listPath = ltrim($instance->listUrl(), '/');
        $view = $pageConfig['view'];
        $extra = $pageConfig['data'] ?? [];

        $router->get($listPath . '/{key}', fn ($key) => view($view, array_merge($extra, ['key' => $key, 'mode' => null])))
            ->where(['key' => '[\w-]+']);

        $router->get($listPath . '/{key}/edit', fn ($key) => view($view, array_merge($extra, ['key' => $key, 'mode' => 'edit'])))
            ->where(['key' => '[\w-]+']);
    }
}
