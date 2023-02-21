<?php

namespace Emargareten\InertiaModal\Tests;

use Emargareten\InertiaModal\Tests\Stubs\Post;
use Inertia\Testing\AssertableInertia;

class ModalTest extends TestCase
{
    public function test_modal_can_be_rendered_from_inertia_visit()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.show', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.redirectURL', route('home'))
            ->assertJsonPath('props.modal.component', 'Posts/Show')
            ->assertJsonPath('props.modal.props.post.content', $post->content);
    }

    public function test_modal_can_be_rendered_from_non_inertia_visit()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.show', [$post]))
            ->assertSuccessful()
            ->assertInertia(function (AssertableInertia $page) use ($post) {
                $page->component('Posts/Index')
                    ->where('modal.redirectURL', route('posts.index'))
                    ->where('modal.component', 'Posts/Show')
                    ->where('modal.props.post.content', $post->content);
            });
    }
}
