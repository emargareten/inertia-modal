<?php

namespace Emargareten\InertiaModal\Tests;

use Emargareten\InertiaModal\InertiaModalServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__);
        config()->set('inertia.testing.ensure_pages_exist', false);
        config()->set('inertia.pages.paths', [realpath(__DIR__)]);

        Route::middleware([StartSession::class, Middleware::class, SubstituteBindings::class])
            ->group(function () {
                Route::get('/', fn () => inertia()->render('Home'))->name('home');
                Route::get('posts', [PostController::class, 'index'])->name('posts.index');
                Route::get('posts-with-middleware', [PostController::class, 'index'])
                    ->middleware(BaseMiddleware::class)
                    ->name('posts.middleware');
                Route::get('posts/{post}/middleware', [PostController::class, 'middleware'])->name('posts.show.middleware');
                Route::get('posts/{post}/features', [PostController::class, 'features'])->name('posts.features');
                Route::get('posts/{post}/enum', [PostController::class, 'enum'])->name('posts.enum');
                Route::get('posts/{post}/nested-dot', [PostController::class, 'nestedDotProps'])->name('posts.nested-dot');
                Route::get('posts/{post}/with', [PostController::class, 'withProps'])->name('posts.with');
                Route::get('posts/{post}/force-base', [PostController::class, 'forceBase'])->name('posts.force-base');
                Route::get('posts/{post}/refresh', [PostController::class, 'refresh'])->name('posts.show.refresh');
                Route::get('posts/{post}/action', [PostController::class, 'action'])->name('posts.show.action');
                Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
            });

        Inertia::version(null);
    }

    public function defineDatabaseMigrations()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('content');
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
            InertiaModalServiceProvider::class,
        ];
    }
}
