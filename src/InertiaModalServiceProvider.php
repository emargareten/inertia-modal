<?php

namespace Emargareten\InertiaModal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\ServiceProvider;
use Inertia\ResponseFactory;

class InertiaModalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ResponseFactory::macro('modal', function (
            string $component,
            array|Arrayable $props = []
        ) {
            return new Modal($component, $props);
        });
    }
}
