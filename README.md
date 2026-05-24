# Inertia Modal

[![Latest Version on Packagist](https://img.shields.io/packagist/v/emargareten/inertia-modal.svg?style=flat-square)](https://packagist.org/packages/emargareten/inertia-modal)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/emargareten/inertia-modal/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/emargareten/inertia-modal/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/emargareten/inertia-modal/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/emargareten/inertia-modal/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/emargareten/inertia-modal.svg?style=flat-square)](https://packagist.org/packages/emargareten/inertia-modal)

Inertia Modal is a Laravel package that lets you implement backend-driven modal dialogs for Inertia apps. With this package, you can define modal routes on the backend and dynamically render them when you visit a dialog route.

> [!NOTE]
> This package supports Laravel 11+, PHP 8.2+, Inertia Laravel v3, and Vue 3 only.

> [!NOTE]
> Inertia Modal targets Inertia v3 and uses Inertia's built-in HTTP client. No separate Axios setup is required.

## Installation

Install the Laravel package with Composer:

```bash
composer require emargareten/inertia-modal
```

Install the frontend peer dependencies in your application if they are not already present:

```bash
npm install @inertiajs/vue3 vue
```

## Frontend Setup

### `Modal` Component

Modal is a **headless** component, meaning you have full control over its look, whether it's a modal dialog or a slide-over panel. You are free to use any 3rd-party solutions to power your modals, such as [Headless UI](https://github.com/tailwindlabs/headlessui).

Put the `Modal` component somewhere within the layout.

```vue
<script setup>
import { Modal } from '../../vendor/emargareten/inertia-modal'
</script>

<template>
  <div>
    <!-- layout -->
    <Modal />
  </div>
</template>
```

> [!NOTE]
> Ensure that the layout remains [persistent](https://inertiajs.com/pages#persistent-layouts) throughout the entire application. If you have multiple layouts, create a base layout that should invoke the modal, using nested layouts.

### Plugin

Set up the `modal` plugin with the same component resolver you use to render Inertia pages.

#### Vite / Laravel

```javascript
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h } from 'vue'
import { modal } from '../../vendor/emargareten/inertia-modal'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

const pages = import.meta.glob('./Pages/**/*.vue')

createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, pages),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(modal, {
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, pages),
      })
      .mount(el)
  }
})
```

If you prefer an alias, point it at the Composer-installed package:

```javascript
// vite.config.js
import { defineConfig } from 'vite'
import path from 'node:path'

export default defineConfig({
  resolve: {
    alias: {
      'inertia-modal': path.resolve('vendor/emargareten/inertia-modal'),
    },
  }
})
```

## Usage

Modals have their own routes, letting you access them even via direct URLs. Define routes for your modal pages.

```php
// background context / base page
Route::get('users', [UserController::class, 'index'])->name('users.index');

// modal route
Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
```

Render a modal from a controller. Specify the `base` route to render the background when the modal is accessed directly.

```php
use Emargareten\InertiaModal\Modal;
use Inertia\Inertia;

class UserController extends Controller
{
    // ...
    
    public function show(User $user): Modal
    {
        return Inertia::modal('Users/Show', ['user' => $user])->baseRoute('users.index');
    }
}
```

The component argument follows Inertia's component conventions, including configured component transformers and string-backed enums.

You can add props later with `with()`. It follows Inertia's native `Response::with()` behavior, so it accepts an array, a prop name and value, or a `ProvidesInertiaProperties` instance:

```php
return Inertia::modal('Users/Show', ['user' => $user])
    ->with(['permissions' => $permissions])
    ->with('canEdit', $request->user()->can('update', $user))
    ->baseRoute('users.index');
```

Dot notation is supported for modal props:

```php
return Inertia::modal('Users/Show', [
    'user' => $user,
    'filters.search' => request('search'),
])->baseRoute('users.index');
```

### Inertia v3 props

Modal responses are resolved through Inertia's v3 response pipeline, so modal props can use the current Inertia prop helpers:

```php
return Inertia::modal('Users/Show', [
    'user' => $user,
    'stats' => Inertia::defer(fn () => $user->stats()),
    'comments' => Inertia::merge($user->comments()->latest()->get())
        ->append()
        ->matchOn('id'),
    'actions' => Inertia::once(fn () => ActionResource::collection($actions)),
])->baseRoute('users.index');
```

This also preserves Inertia metadata such as shared props, deferred props, merge props, deep merge props, match-on strategies, once props, rescued props, and infinite scroll metadata when a modal opens over an existing page.

Partial reloads for modal props are supported using the nested `modal.props.*` path. This is also what Inertia uses when loading deferred modal props:

```javascript
router.reload({ only: ['modal.props.stats'] })
```

Partial modal responses stay sparse so Inertia can apply `mergeProps`, `prependProps`, and `deepMergeProps` itself. For example, a deferred modal prop using `Inertia::merge(...)->append()` will be appended by Inertia's native client merge logic, not pre-merged by this package.

If you need to exclude expensive shared props from modal-only responses, publish the config file and update `exclude_shared_props`:

```bash
php artisan vendor:publish --provider="Emargareten\InertiaModal\InertiaModalServiceProvider"
```

### Backdrop behavior

By default, the backdrop page is preserved with its current data while the modal is open. This keeps modal visits fast and avoids reloading the page behind the dialog. When the modal is closed through `redirect()`, Inertia visits the base URL again.

When a modal URL is opened directly, the configured base URL is dispatched through Laravel's normal router pipeline. Route middleware, model binding, route events, and response preparation run as they would for a normal request to the base page.

If your backdrop needs fresh data while the modal opens, call `refreshBackdrop()`:

```php
    public function show(User $user): Modal
    {
        return Inertia::modal('Users/Show', ['user' => $user])
            ->baseRoute('users.index')
            ->refreshBackdrop();
    }
```

To ignore the current modal redirect header and force a specific base route as the backdrop, call `forceBase()`:

```php
    public function show(User $user): Modal
    {
        return Inertia::modal('Users/Show', ['user' => $user])
            ->baseRoute('users.index')
            ->forceBase();
    }
```

This will force re-render of the base route (or even redirect to a different base route).

Both `refreshBackdrop()` and `forceBase()` accept a boolean.

### Frontend implementation

Use the `useModal()` composable in your modal component.

This example is a simple headlessui modal, you can add more transitions etc. see https://headlessui.com/vue/dialog.

```vue
<template>
  <TransitionRoot appear as="template" :show="show">
    <Dialog as="div" class="relative z-10" @close="close">
      <TransitionChild @after-leave="redirect" as="template">
        <div class="fixed inset-0 bg-black/75 transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild as="template">
            <DialogPanel class="w-full max-w-lg transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900">
                <slot name="title" />
              </DialogTitle>
              <slot />
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import { useModal } from '../../vendor/emargareten/inertia-modal'

const { show, close, redirect } = useModal()
</script>
```

The `redirect` method will redirect to the base route, you can pass in all inertia visit options as a parameter.

```javascript
redirect({ preserveScroll: true })
```

The `close` method will close the modal without redirecting to the base route.

> [!NOTE]
> If you configured the Vite alias shown above, import from `inertia-modal` instead of the vendor path:
>
> ```js
> import { useModal } from 'inertia-modal'
> ```

## Testing

```bash
composer test
npm run build
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

This package was highly inspired by [momentum-modal](https://github.com/lepikhinb/momentum-modal)

- [emargareten](https://github.com/emargareten)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
