<?php

use Emargareten\InertiaModal\Tests\Stubs\Post;
use Emargareten\InertiaModal\Tests\Stubs\User;
use Emargareten\InertiaModal\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function user(): User
{
    return User::create(['username' => 'test-user']);
}

function post(User $user): Post
{
    return $user->posts()->create(['body' => 'test-post']);
}
