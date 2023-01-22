<?php

namespace Emargareten\InertiaModal\Tests\Stubs;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ExampleController
{
    public function user(Request $request, User $user)
    {
        return Inertia::render('Users/Show', ['user' => $user, 'page' => $request->input('page')]);
    }

    public function post(User $user, Post $post)
    {
        return Inertia::modal('Posts/Show', [
            'user' => $user,
            'post' => $post,
        ])
            ->baseRoute('users.show', $user);
    }

    public function differentParameters(User $user, Post $post)
    {
        return Inertia::modal('Posts/Show', [
            'user' => $user,
            'post' => $post,
        ])
            ->baseRoute('users.show', User::where('id', '<>', $user->id)->first());
    }

    public function rawUser(string $user)
    {
        return Inertia::render('Users/Show', ['user' => $user]);
    }

    public function rawPost($user, $post)
    {
        return Inertia::modal('Posts/Show', [
            'user' => $user,
            'post' => $post,
        ])
            ->baseRoute('raw.users.show', $user);
    }
}
