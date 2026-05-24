<?php

namespace Emargareten\InertiaModal\Tests;

use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

class PostController
{
    public function index()
    {
        return Inertia::render('Posts/Index', ['posts' => Post::paginate(10)]);
    }

    public function show(Post $post)
    {
        return Inertia::modal('Posts/Show', ['post' => $post])->baseRoute('posts.index');
    }

    public function refresh(Post $post)
    {
        return Inertia::modal('Posts/Show', ['post' => $post])
            ->baseRoute('posts.index')
            ->refreshBackdrop();
    }

    public function middleware(Post $post)
    {
        return Inertia::modal('Posts/Show', ['post' => $post])->baseRoute('posts.middleware');
    }

    public function enum(Post $post)
    {
        return Inertia::modal(PostComponent::Show, ['post' => $post])->baseRoute('posts.index');
    }

    public function withProps(Post $post)
    {
        return Inertia::modal('Posts/Show', ['post' => $post])
            ->with(['extra' => 'merged'])
            ->with('named', 'value')
            ->with(new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return ['provided' => $context->component];
                }
            })
            ->baseRoute('posts.index');
    }

    public function forceBase(Post $post)
    {
        return Inertia::modal('Posts/Show', ['post' => $post])
            ->baseRoute('posts.index')
            ->forceBase();
    }

    public function nestedDotProps(Post $post)
    {
        return Inertia::modal('Posts/Show', [
            'filters' => fn () => ['sort' => 'recent'],
            'filters.search' => 'modal',
        ])->baseRoute('posts.index');
    }

    public function features(Post $post)
    {
        return Inertia::modal('Posts/Features', [
            'post' => $post,
            'stats' => Inertia::defer(fn () => ['views' => 10]),
            'comments' => Inertia::merge([
                ['id' => 1, 'body' => 'First'],
            ])->append()->matchOn('id'),
            'filters.search' => 'modal',
        ])->baseRoute('posts.index');
    }
}
