<?php

use Emargareten\InertiaModal\Tests\Stubs\ExampleController;
use Emargareten\InertiaModal\Tests\Stubs\ExampleMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\from;
use function Pest\Laravel\get;

beforeEach(function () {
    Route::middleware([StartSession::class, ExampleMiddleware::class, SubstituteBindings::class])
        ->group(function () {
            Route::get('/', fn () => inertia()->render('Home'))->name('home');
            Route::get('raw/{user}', [ExampleController::class, 'rawUser'])->name('raw.users.show');
            Route::get('raw/{user}/{post}', [ExampleController::class, 'rawPost'])->name('raw.users.posts.show');
            Route::get('{user}', [ExampleController::class, 'user'])->name('users.show');
            Route::get('{user}/{post}', [ExampleController::class, 'post'])->name('users.posts.show');

            Route::get('different/{user}/{post}', [ExampleController::class, 'differentParameters'])->name('different.users.posts.show');
        });
});

test('modals can be rendered', function () {
    $user = user();
    $post = post($user);

    get(route('users.posts.show', [$user, $post]))
        ->assertSuccessful()
        ->assertInertia(function (AssertableInertia $page) use ($user, $post) {
            $page->component('Users/Show')
                ->where('modal.baseURL', route('users.show', $user))
                ->where('modal.component', 'Posts/Show')
                ->where('modal.props.user.username', $user->username)
                ->where('modal.props.post.body', $post->body);
        });
});

test('pass raw data without model bindings', function () {
    $user = 'test-user';
    $post = 'test-post';

    get(route('raw.users.posts.show', [$user, $post]))
        ->assertSuccessful()
        ->assertInertia(function (AssertableInertia $page) use ($user, $post) {
            $page->component('Users/Show')
                ->where('modal.baseURL', route('raw.users.show', $user))
                ->where('modal.component', 'Posts/Show')
                ->where('modal.props.user', $user)
                ->where('modal.props.post', $post);
        });
});

test('preserve background on inertia visits', function () {
    //
});

test('preserve background on non-inertia visits', function () {
    $fromURL = route('home');
    $user = user();
    $post = post($user);

    from($fromURL)
        ->get(route('users.posts.show', [$user, $post]))
        ->assertSuccessful()
        ->assertInertia(function (AssertableInertia $page) use ($user) {
            $page->component('Users/Show')
                ->where('user.username', $user->username)
                ->where('modal.redirectURL', route('users.show', $user))
                ->where('modal.baseURL', route('users.show', $user));
        });
});

test('preserve query string for parent component', function () {
    //
});

test('route parameters are bound correctly', function () {
    $fromURL = route('home');
    $user = user();
    $otherUser = user();
    $post = post($user);

    from($fromURL)
        ->get(route('different.users.posts.show', [$user, $post]))
        ->assertSuccessful()
        ->assertInertia(function (AssertableInertia $page) use ($otherUser) {
            $page->component('Users/Show')
                ->where('user.id', $otherUser->id)
                ->where('modal.redirectURL', route('users.show', $otherUser))
                ->where('modal.baseURL', route('users.show', $otherUser));
        });
});
