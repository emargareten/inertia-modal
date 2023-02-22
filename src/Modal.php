<?php

namespace Emargareten\InertiaModal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
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

    /**
     * Set the URL for the backdrop page.
     */
    public function baseURL(string $url): static
    {
        $this->baseURL = $url;

        return $this;
    }

    /**
     * Set the URL for the backdrop page using a route name.
     */
    public function baseRoute(string $name, mixed $parameters = [], bool $absolute = true): static
    {
        $this->baseURL = route($name, $parameters, $absolute);

        return $this;
    }

    /**
     * Force refreshing backdrop data.
     */
    public function refreshBackdrop($refresh = true): static
    {
        $this->refreshBackdrop = $refresh;

        return $this;
    }

    /**
     * Ignore redirect header and force setting backdrop to new base URL.
     */
    public function forceBase(bool $force = true): static
    {
        $this->forceBase = $force;

        return $this;
    }

    /**
     * Add props to the modal.
     */
    public function with(array $props): static
    {
        $this->props = $props;

        return $this;
    }

    public function render(): mixed
    {
        if (request()->header('X-Inertia') && ! $this->refreshBackdrop) {
            return $this->renderModal();
        }

        Inertia::share(['modal' => $this->component()]);

        if (request()->header('X-Inertia') && request()->header('X-Inertia-Partial-Component')) {
            return Inertia::render(request()->header('X-Inertia-Partial-Component'));
        }

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

    /**
     * Render the modal.
     */
    protected function renderModal(): JsonResponse
    {
        $shared = Arr::except(
            Inertia::getShared(),
            app('config')->get('inertia-modal.exclude_shared_props', [])
        );

        $props = [
            ...$shared,
            'modal' => $this->component(),
        ];

        $page = [
            'props' => $props,
            'url' => request()->getBaseUrl().request()->getRequestUri(),
            'version' => Inertia::getVersion(),
        ];

        return new JsonResponse($page, 200, ['X-Inertia-Modal' => 'true']);
    }

    protected function handleRoute(Request $request, Route $route): mixed
    {
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
            'redirectURL' => $this->redirectURL(),
            'props' => $this->props,
            'key' => request()->header('X-Inertia-Modal-Key', (string) Str::uuid()),
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

        if (request()->header('X-Inertia') && request()->headers->get('referer')) {
            return request()->headers->get('referer');
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
