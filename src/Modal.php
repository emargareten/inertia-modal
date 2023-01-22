<?php

namespace Emargareten\InertiaModal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

class Modal implements Responsable
{
    protected string $baseURL;

    protected bool $refreshBackdrop = false;

    protected bool $forceBase = false;

    public function __construct(
        protected string $component,
        protected array|Arrayable $props = []
    ) {
    }

    public function baseRoute(string $name, mixed $parameters = [], bool $absolute = true): static
    {
        $this->baseURL = route($name, $parameters, $absolute);

        return $this;
    }

    public function basePageRoute(string $name, mixed $parameters = [], bool $absolute = true): static
    {
        return $this->baseRoute($name, $parameters, $absolute);
    }

    public function baseURL(string $url): static
    {
        $this->baseURL = $url;

        return $this;
    }

    /**
     * Force refreshing backdrop data
     */
    public function refreshBackdrop($refresh = true): static
    {
        $this->refreshBackdrop = $refresh;

        return $this;
    }

    /**
     * Ignore redirect header and force setting backdrop to new base URL
     */
    public function forceBase(bool $force = true): static
    {
        $this->forceBase = $force;

        return $this;
    }

    public function with(array $props): static
    {
        $this->props = $props;

        return $this;
    }

    public function render(): mixed
    {
        if (request()->inertia() && ! $this->refreshBackdrop) {
            /*
         * Here we will remove global sharing of auth etc. this way we don't need to fetch these
         * data (the user doesn't even need to be authenticated). We don't care that the props
         * are stale since it is only used for the backdrop. See preserveBackdrop.js
         */
            Inertia::flushShared();

            /*
             * Now we are going to share the modal and it's data as a prop. we also want
             * the validation errors to be shared as well, we need to add it to the
             * props here since we removed everything from the global share.
             *
             * We pass in an empty string for the component name, we are reusing
             * the current component, see preserveBackdrop.js
             */
            return Inertia::render('', [
                'modal' => $this->component(),
                'errors' => (new \Inertia\Middleware())->resolveValidationErrors(request()),
            ]);
        }

        inertia()->share(['modal' => $this->component()]);

        if (request()->header('X-Inertia') && request()->header('X-Inertia-Partial-Component')) {
            return inertia()->render(request()->header('X-Inertia-Partial-Component'));
        }

        /** @var Request $originalRequest */
        $originalRequest = app('request');

        $request = Request::create(
            $this->redirectURL(),
            Request::METHOD_GET,
            $originalRequest->query->all(),
            $originalRequest->cookies->all(),
            $originalRequest->files->all(),
            $originalRequest->server->all(),
            $originalRequest->getContent()
        );

        /** @var \Illuminate\Routing\Router */
        $router = app('router');

        $baseRoute = $router->getRoutes()->match($request);

        $request->headers->replace($originalRequest->headers->all());

        $request->setJson($originalRequest->json())
            ->setUserResolver(fn () => $originalRequest->getUserResolver())
            ->setRouteResolver(fn () => $baseRoute)
            ->setLaravelSession($originalRequest->session());

        app()->instance('request', $request);

        return $this->handleRoute($request, $baseRoute);
    }

    protected function handleRoute(Request $request, Route $route): mixed
    {
        /** @var \Illuminate\Routing\Router */
        $router = app('router');

        $middleware = new SubstituteBindings($router);

        return $middleware->handle(
            $request,
            fn () => $route->run()
        );
    }

    protected function component(): array
    {
        return [
            'component' => $this->component,
            'baseURL' => $this->baseURL,
            'redirectURL' => $this->redirectURL(),
            'props' => $this->props,
            'key' => request()->header('X-Inertia-Modal-Key', Str::uuid()->toString()),
        ];
    }

    protected function redirectURL(): string
    {
        if ($this->forceBase) {
            return $this->baseURL;
        }

        if (request()->header('X-Inertia-Modal-Redirect')) {
            return request()->header('X-Inertia-Modal-Redirect');
        }

        $referer = request()->headers->get('referer');

        if (request()->header('X-Inertia') && $referer && $referer != url()->current()) {
            return $referer;
        }

        return $this->baseURL;
    }

    public function toResponse($request)
    {
        $response = $this->render();

        if ($response instanceof Responsable) {
            return $response->toResponse($request);
        }

        return $response;
    }
}
