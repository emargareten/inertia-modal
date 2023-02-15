<?php

namespace Emargareten\InertiaModal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
            return $this->renderCurrentComponentWithModal();
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
     * Rerender the current component with the modal.
     */
    protected function renderCurrentComponentWithModal(): Response
    {
        $shared = Inertia::getShared();

        Inertia::flushShared();

        $excluded = app('config')->get('inertia-modal.exclude_shared_props', []);

        Inertia::share(Arr::except($shared, $excluded));

        /*
         * We are returning the modal and it's data as a prop. We are also
         * passing an empty string as the component name, since we are
         * reusing the current component. (see preserveBackdrop.js)
         */
        return Inertia::render('', [
            'modal' => $this->component(),
        ]);
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
            'baseURL' => $this->baseURL,
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
