<?php

namespace Emargareten\InertiaModal\Tests;

use Emargareten\InertiaModal\InertiaModalServiceProvider;
use Emargareten\InertiaModal\Tests\Stubs\ExampleMiddleware;
use Emargareten\InertiaModal\Tests\Stubs\PostController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/Stubs');
        config()->set('inertia.testing.ensure_pages_exist', false);
        config()->set('inertia.testing.page_paths', [realpath(__DIR__)]);

        Route::middleware([StartSession::class, ExampleMiddleware::class, SubstituteBindings::class])
            ->group(function () {
                Route::get('/', fn () => inertia()->render('Home'))->name('home');
                Route::get('posts', [PostController::class, 'index'])->name('posts.index');
                Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
            });
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
