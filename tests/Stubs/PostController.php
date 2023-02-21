<?php

namespace Emargareten\InertiaModal\Tests\Stubs;

use Inertia\Inertia;

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
}
