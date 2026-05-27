<?php

namespace Emargareten\InertiaModal;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\Response;
use Inertia\ResponseFactory;
use Inertia\Support\Header;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use UnitEnum;

class Modal implements Responsable
{
    protected string $baseURL;

    protected bool $refreshBackdrop = false;

    protected bool $forceBase = false;

    protected ?string $transformedComponent = null;

    public function __construct(
        protected BackedEnum|UnitEnum|string $component,
        protected array|Arrayable $props = []
    ) {}

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
    public function refreshBackdrop(bool $refresh = true): static
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
     *
     * @param  string|array<string, mixed>|ProvidesInertiaProperties  $key
     */
    public function with($key, mixed $value = null): static
    {
        $props = $this->props instanceof Arrayable ? $this->props->toArray() : $this->props;

        if ($key instanceof ProvidesInertiaProperties) {
            $props[] = $key;
        } elseif (is_array($key)) {
            $props = array_merge($props, $key);
        } else {
            $props[$key] = $value;
        }

        $this->props = $props;

        return $this;
    }

    public function render(): mixed
    {
        if (request()->header(Header::INERTIA) && ! $this->refreshBackdrop) {
            return $this->renderModal();
        }

        Inertia::share(['modal' => $this->modalPayload()]);

        if (request()->header(Header::INERTIA) && request()->header(Header::PARTIAL_COMPONENT)) {
            return $this->partialBackdropResponse();
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

        $request->headers->replace($originalRequest->headers->all());

        $request->setJson($originalRequest->json())
            ->setUserResolver($originalRequest->getUserResolver())
            ->setLaravelSession($originalRequest->session());

        app()->instance('request', $request);

        try {
            return $router->dispatch($request);
        } finally {
            app()->instance('request', $originalRequest);
        }
    }

    /**
     * Render the modal.
     */
    protected function renderModal(): JsonResponse
    {
        $page = $this->inertiaPage([
            'modal' => $this->modalPayload(transformComponent: (bool) request()->header(Header::PARTIAL_COMPONENT)),
        ]);

        if (isset($page['props']['modal'])) {
            $page['props']['modal']['component'] = $this->modalComponentName($page);
        }

        return new JsonResponse($page, 200, ['X-Inertia-Modal' => 'true']);
    }

    protected function inertiaPage(array $props): array
    {
        $shared = Inertia::getShared();
        $filteredShared = Arr::except(
            $shared,
            app('config')->get('inertia-modal.exclude_shared_props', [])
        );

        Inertia::flushShared();
        Inertia::share($filteredShared);

        try {
            $response = $this->inertiaResponse($filteredShared, $props)->toResponse(request());
        } finally {
            Inertia::flushShared();
            Inertia::share($shared);
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    protected function inertiaResponse(array $shared, array $props): Response
    {
        if ($partialComponent = request()->header(Header::PARTIAL_COMPONENT)) {
            return $this->makeResponse($partialComponent, $shared, $props);
        }

        return Inertia::render($this->component, $props);
    }

    protected function partialBackdropResponse(): Response
    {
        return $this->makeResponse(
            request()->header(Header::PARTIAL_COMPONENT),
            Inertia::getShared(),
            [],
        );
    }

    /**
     * Build a Response for an already-transformed component while preserving the
     * application's Inertia settings (root view, history encryption and custom URL
     * resolver) that ResponseFactory::render() would normally apply. The component
     * name comes back from the client already transformed, so render() cannot be
     * used here without transforming it a second time.
     */
    protected function makeResponse(string $component, array $shared, array $props): Response
    {
        $factory = app(ResponseFactory::class);

        return new Response(
            component: $component,
            sharedProps: $shared,
            props: $props,
            rootView: $this->factorySetting($factory, 'rootView') ?? 'app',
            version: Inertia::getVersion(),
            encryptHistory: $this->factorySetting($factory, 'encryptHistory')
                ?? app('config')->get('inertia.history.encrypt', false),
            urlResolver: $this->factorySetting($factory, 'urlResolver'),
        );
    }

    protected function factorySetting(ResponseFactory $factory, string $property): mixed
    {
        try {
            return (new ReflectionProperty($factory, $property))->getValue($factory);
        } catch (ReflectionException) {
            return null;
        }
    }

    protected function modalComponentName(array $page): string
    {
        if (! request()->header(Header::PARTIAL_COMPONENT) && is_string($page['component'] ?? null)) {
            return $page['component'];
        }

        return $this->transformedComponentName();
    }

    protected function transformedComponentName(): string
    {
        if ($this->transformedComponent !== null) {
            return $this->transformedComponent;
        }

        $component = $this->component;
        $factory = app(ResponseFactory::class);

        // Inertia owns component transformation. Reflection keeps this adapter aligned
        // without constructing an extra Response and consuming session-backed flags.
        try {
            $transform = new ReflectionMethod($factory, 'transformComponent');
        } catch (ReflectionException $exception) {
            throw new LogicException('Inertia modal component transformers require inertiajs/inertia-laravel to expose ResponseFactory::transformComponent().', previous: $exception);
        }

        $component = $transform->invoke($factory, $component);

        $component = match (true) {
            $component instanceof BackedEnum => $component->value,
            $component instanceof UnitEnum => $component->name,
            default => $component,
        };

        if (! is_string($component)) {
            throw new InvalidArgumentException('Component argument must be of type string or a string BackedEnum');
        }

        return $this->transformedComponent = $component;
    }

    protected function rawComponentName(): string
    {
        $component = match (true) {
            $this->component instanceof BackedEnum => $this->component->value,
            $this->component instanceof UnitEnum => $this->component->name,
            default => $this->component,
        };

        if (! is_string($component)) {
            throw new InvalidArgumentException('Component argument must be of type string or a string BackedEnum');
        }

        return $component;
    }

    protected function modalPayload(bool $transformComponent = true): array
    {
        $props = $this->props instanceof Arrayable ? $this->props->toArray() : $this->props;

        return [
            'component' => $transformComponent ? $this->transformedComponentName() : $this->rawComponentName(),
            'redirectURL' => $this->redirectURL(),
            'props' => $this->unpackDotProps($props),
            'key' => $this->modalKey(),
        ];
    }

    /**
     * Reuse the client-supplied modal key only for sparse reloads of the modal
     * already on screen (e.g. deferred `modal.props.*` requests). A fresh modal
     * navigation carries the previous modal's key (sent on every visit by the
     * frontend); generating a new key there avoids the new modal inheriting the
     * previous one's page metadata and Vue state. A full `modal` partial (e.g.
     * `only: ['modal']`) fetches a different modal instance, so it gets a new key.
     * Validation responses keep the current key so the mounted modal form can
     * receive Inertia's onError callback without being remounted.
     */
    protected function modalKey(): string
    {
        if ($this->isSparseModalReload() || $this->hasValidationErrors()) {
            return request()->header('X-Inertia-Modal-Key', (string) Str::uuid());
        }

        return (string) Str::uuid();
    }

    protected function hasValidationErrors(): bool
    {
        return request()->hasSession() && request()->session()->has('errors');
    }

    protected function isSparseModalReload(): bool
    {
        if (! request()->header(Header::PARTIAL_COMPONENT)) {
            return false;
        }

        $only = $this->partialHeaderValues(Header::PARTIAL_ONLY);

        if ($only !== []) {
            $targetsModalChild = array_filter($only, fn (string $path) => str_starts_with($path, 'modal.'));

            return $targetsModalChild !== [] && ! in_array('modal', $only, true);
        }

        $except = $this->partialHeaderValues(Header::PARTIAL_EXCEPT);

        if ($except !== []) {
            return ! in_array('modal', $except, true);
        }

        return false;
    }

    protected function partialHeaderValues(string $header): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) request()->header($header, ''))),
            fn (string $path) => $path !== ''
        ));
    }

    protected function unpackDotProps(array $props): array
    {
        foreach ($props as $key => $value) {
            if (! is_string($key) || ! str_contains($key, '.')) {
                continue;
            }

            if ($value instanceof Closure) {
                $value = app()->call($value);
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            $this->ensurePathIsTraversable($props, $key);

            Arr::set($props, $key, $value);
            unset($props[$key]);
        }

        return $props;
    }

    /**
     * Resolve closures and Arrayable values along the intermediate segments of a
     * dot-notation path so Arr::set can nest into them instead of overwriting an
     * existing prop. Mirrors Inertia's own PropsResolver handling.
     */
    protected function ensurePathIsTraversable(array &$props, string $dotKey): void
    {
        $segments = explode('.', $dotKey);
        array_pop($segments);

        $current = &$props;

        foreach ($segments as $segment) {
            if (! isset($current[$segment])) {
                return;
            }

            if ($current[$segment] instanceof Closure) {
                $current[$segment] = app()->call($current[$segment]);
            }

            if ($current[$segment] instanceof Arrayable) {
                $current[$segment] = $current[$segment]->toArray();
            }

            if (! is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }
    }

    protected function redirectURL(): string
    {
        if ($this->forceBase) {
            return $this->resolveBaseURL();
        }

        if (request()->header('X-Inertia-Modal-Redirect')) {
            return request()->header('X-Inertia-Modal-Redirect');
        }

        if (request()->header(Header::INERTIA) && request()->headers->get('referer')) {
            return request()->headers->get('referer');
        }

        return $this->resolveBaseURL();
    }

    protected function resolveBaseURL(): string
    {
        if (! isset($this->baseURL)) {
            throw new LogicException('Inertia modal responses must define a backdrop URL with baseURL() or baseRoute().');
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
