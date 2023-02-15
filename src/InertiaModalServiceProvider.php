<?php

namespace Emargareten\InertiaModal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\ServiceProvider;
use Inertia\ResponseFactory;

class InertiaModalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/inertia-modal.php' => config_path('inertia-modal.php'),
        ]);

        ResponseFactory::macro('modal', function (
            string $component,
            array|Arrayable $props = []
        ) {
            return new Modal($component, $props);
        });
    }
}
