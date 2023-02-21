# Inertia Modal

[![Latest Version on Packagist](https://img.shields.io/packagist/v/emargareten/inertia-modal.svg?style=flat-square)](https://packagist.org/packages/emargareten/inertia-modal)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/emargareten/inertia-modal/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/emargareten/inertia-modal/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/emargareten/inertia-modal/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/emargareten/inertia-modal/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/emargareten/inertia-modal.svg?style=flat-square)](https://packagist.org/packages/emargareten/inertia-modal)

Inertia Modal is a Laravel package that lets you implement backend-driven modal dialogs for Inertia apps.

Define modal routes on the backend and dynamically render them when you visit a dialog route.

> **Note**
> This package supports Vue 3 only

## Installation

You can install the package via composer:

```bash
composer require emargareten/inertia-modal
```
## Frontend Setup

> **Warning**
> The package utilizes `axios` under the hood. If your app is already using `axios` as a dependency, make sure to lock it to the same version Inertia uses.
> ```bash
> npm i axios
> ```

### `Modal` Component

Modal is a **headless** component, meaning you have full control over its look, whether it's a modal dialog or a slide-over panel. You are free to use any 3rd-party solutions to power your modals, such as [Headless UI](https://github.com/tailwindlabs/headlessui).

Put the `Modal` component somewhere within the layout.

```vue
<script setup>
import { Modal } from '/vendor/emargareten/inertia-modal'
</script>

<template>
    <div>
        <!-- layout -->
        <Modal />
    </div>
</template>
```

### Plugin

Set up a `modal` plugin with the same component resolver you use to render Inertia pages.

#### Vite

```javascript
import { modal } from '/vendor/emargareten/inertia-modal'

createInertiaApp({
  resolve: (name) => resolvePageComponent(name, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, app, props, plugin }) {
    createApp({ render: () => h(app, props) })
      .use(modal, {
        resolve: (name) => resolvePageComponent(name, import.meta.glob('./Pages/**/*.vue')),
      })
      .use(plugin)
      .mount(el)
  }
})
```

#### Laravel Mix

```javascript
import { modal } from '/vendor/emargareten/inertia-modal'

createInertiaApp({
  resolve: (name) => require(`./Pages/${name}`),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(modal, {
        resolve: (name) => import(`./Pages/${name}`),
      })
      .use(plugin)
      .mount(el)
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

class ShowTweet extends Controller
{
    // ...
    
    public function show(User $user): Modal
    {
        return Inertia::modal('Users/Show', ['user' => $user])->baseRoute('users.index');
    }
}
```

By default, the backdrop component will be preserved with its current [stale] data (besides for the validation errors), in most cases this is fine since it
will refresh when we close the modal (redirect to the base route), if your app does need fresh data for the backdrop, add
the `refreshBackdrop` method:

```php
    public function show(User $user): Modal
    {
        return Inertia::modal('Users/Show', ['user' => $user])
            ->baseRoute('users.index')
            ->refreshBackdrop();
    }
```

To force a specific route as the backdrop add the `forceBase` method:

```php
    public function show(User $user): Modal
    {
        return Inertia::modal('Users/Show', ['user' => $user])
            ->baseRoute('users.index')
            ->forceBase();
    }
```

This will force re-render of the base route (or even redirect to a different base route).

Both of the above methods can also accept a boolean whether to refresh etc.

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
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from "@headlessui/vue"
import { useModal } from "vendor/emargareten/inertia-modal"

const { show, close, redirect } = useModal()
</script>
```

## Testing

```bash
composer test
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
