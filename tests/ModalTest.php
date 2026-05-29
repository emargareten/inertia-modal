<?php

namespace Emargareten\InertiaModal\Tests;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use LogicException;

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

    public function test_direct_modal_visit_uses_inertia_component_transformer()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::transformComponentUsing(fn (string $component) => "Pages/{$component}");

        $this->get(route('posts.show', [$post]))
            ->assertSuccessful()
            ->assertInertia(function (AssertableInertia $page) use ($post) {
                $page->component('Pages/Posts/Index')
                    ->where('modal.redirectURL', route('posts.index'))
                    ->where('modal.component', 'Pages/Posts/Show')
                    ->where('modal.props.post.content', $post->content);
            });
    }

    public function test_refresh_backdrop_modal_visit_uses_inertia_component_transformer()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::transformComponentUsing(fn (string $component) => "Pages/{$component}");

        $this->get(route('posts.show.refresh', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('component', 'Pages/Home')
            ->assertJsonPath('props.modal.component', 'Pages/Posts/Show')
            ->assertJsonPath('props.modal.props.post.content', $post->content);
    }

    public function test_refresh_backdrop_partial_reload_does_not_retransform_partial_component()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::transformComponentUsing(fn (string $component) => "Pages/{$component}");

        $this->get(route('posts.show.refresh', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Pages/Home',
            'X-Inertia-Partial-Data' => 'modal',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('component', 'Pages/Home')
            ->assertJsonPath('props.modal.component', 'Pages/Posts/Show')
            ->assertJsonPath('props.modal.props.post.content', $post->content);
    }

    public function test_direct_modal_visit_dispatches_base_route_through_middleware()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.show.middleware', [$post]))
            ->assertSuccessful()
            ->assertInertia(function (AssertableInertia $page) use ($post) {
                $page->component('Posts/Index')
                    ->where('from_base_middleware', true)
                    ->where('modal.redirectURL', route('posts.middleware'))
                    ->where('modal.component', 'Posts/Show')
                    ->where('modal.props.post.content', $post->content);
            });
    }

    public function test_direct_modal_visit_can_use_a_base_action()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.show.action', [$post]))
            ->assertSuccessful()
            ->assertInertia(function (AssertableInertia $page) use ($post) {
                $page->component('Posts/Index')
                    ->where('modal.redirectURL', action([PostController::class, 'index']))
                    ->where('modal.component', 'Posts/Show')
                    ->where('modal.props.post.content', $post->content);
            });
    }

    public function test_modal_inertia_visit_resolves_shared_props()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::share('auth.user', fn () => ['name' => 'Taylor']);

        $this->get(route('posts.show', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.auth.user.name', 'Taylor')
            ->assertJsonPath('sharedProps.0', 'auth');
    }

    public function test_modal_inertia_visit_uses_inertia_v3_prop_metadata()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.features', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.component', 'Posts/Features')
            ->assertJsonPath('props.modal.props.comments.0.body', 'First')
            ->assertJsonPath('props.modal.props.filters.search', 'modal')
            ->assertJsonMissingPath('props.modal.props.stats')
            ->assertJsonPath('deferredProps.default.0', 'modal.props.stats')
            ->assertJsonPath('mergeProps.0', 'modal.props.comments')
            ->assertJsonPath('matchPropsOn.0', 'modal.props.comments.id');
    }

    public function test_modal_props_can_be_resolved_during_partial_requests()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.features', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Posts/Index',
            'X-Inertia-Partial-Data' => 'modal.props.stats',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('component', 'Posts/Index')
            ->assertJsonPath('props.modal.props.stats.views', 10)
            ->assertJsonMissingPath('props.modal.props.post')
            ->assertJsonMissingPath('props.modal.props.comments');
    }

    public function test_modal_component_uses_inertia_component_transformer()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::transformComponentUsing(fn (string $component) => "Pages/{$component}");

        $this->get(route('posts.show', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('component', 'Pages/Posts/Show')
            ->assertJsonPath('props.modal.component', 'Pages/Posts/Show');
    }

    public function test_transformed_modal_props_can_be_resolved_during_partial_requests()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::transformComponentUsing(fn (string $component) => "Pages/{$component}");

        $this->get(route('posts.features', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Pages/Posts/Index',
            'X-Inertia-Partial-Data' => 'modal.props.stats',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('component', 'Pages/Posts/Index')
            ->assertJsonPath('props.modal.props.stats.views', 10)
            ->assertJsonMissingPath('props.modal.props.post')
            ->assertJsonMissingPath('props.modal.props.comments');
    }

    public function test_modal_requires_a_backdrop_url()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('baseURL(), baseRoute(), or baseAction()');

        Inertia::modal('Posts/Show')->toResponse(request());
    }

    public function test_modal_accepts_a_string_backed_enum_component()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.enum', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.component', 'Posts/Show')
            ->assertJsonPath('props.modal.props.post.content', $post->content);
    }

    public function test_with_merges_props_into_existing_modal_props()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.with', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.props.post.content', $post->content)
            ->assertJsonPath('props.modal.props.extra', 'merged')
            ->assertJsonPath('props.modal.props.named', 'value')
            ->assertJsonPath('props.modal.props.provided', 'Posts/Show');
    }

    public function test_force_base_ignores_redirect_header_and_uses_base_url()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.force-base', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.redirectURL', route('posts.index'));
    }

    public function test_dot_notation_props_are_unpacked_on_direct_visit()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.features', [$post]))
            ->assertSuccessful()
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('Posts/Index')
                    ->where('modal.props.filters.search', 'modal');
            });
    }

    public function test_dot_notation_props_preserve_existing_intermediate_values()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.nested-dot', [$post]), ['X-Inertia' => true, 'referer' => route('home')])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.props.filters.sort', 'recent')
            ->assertJsonPath('props.modal.props.filters.search', 'modal');
    }

    public function test_modal_navigation_generates_a_new_key()
    {
        $post = Post::create(['content' => 'test content']);

        $key = $this->get(route('posts.show', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Modal-Key' => 'previous-modal-key',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->json('props.modal.key');

        $this->assertNotSame('previous-modal-key', $key);
    }

    public function test_full_modal_partial_generates_a_new_key()
    {
        $post = Post::create(['content' => 'test content']);

        $key = $this->get(route('posts.show', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Posts/Index',
            'X-Inertia-Partial-Data' => 'modal',
            'X-Inertia-Modal-Key' => 'previous-modal-key',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->json('props.modal.key');

        $this->assertNotSame('previous-modal-key', $key);
    }

    public function test_partial_modal_reload_preserves_the_key()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.features', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Posts/Index',
            'X-Inertia-Partial-Data' => 'modal.key',
            'X-Inertia-Modal-Key' => 'kept-key',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.key', 'kept-key');
    }

    public function test_partial_modal_except_reload_preserves_the_key()
    {
        $post = Post::create(['content' => 'test content']);

        $this->get(route('posts.features', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Posts/Index',
            'X-Inertia-Partial-Except' => 'modal.props.comments',
            'X-Inertia-Modal-Key' => 'kept-key',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.key', 'kept-key')
            ->assertJsonMissingPath('props.modal.props.comments');
    }

    public function test_modal_validation_response_preserves_the_key()
    {
        $post = Post::create(['content' => 'test content']);
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'title' => ['The title field is required.'],
        ]));

        $this->withSession(['errors' => $errors])
            ->get(route('posts.show', [$post]), [
                'X-Inertia' => true,
                'X-Inertia-Modal-Key' => 'kept-key',
                'referer' => route('home'),
            ])
            ->assertSuccessful()
            ->assertJsonPath('props.modal.key', 'kept-key')
            ->assertJsonPath('props.errors.title', 'The title field is required.');
    }

    public function test_modal_validation_response_without_key_generates_a_key()
    {
        $post = Post::create(['content' => 'test content']);
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'title' => ['The title field is required.'],
        ]));

        $key = $this->withSession(['errors' => $errors])
            ->get(route('posts.show', [$post]), [
                'X-Inertia' => true,
                'referer' => route('home'),
            ])
            ->assertSuccessful()
            ->assertJsonPath('props.errors.title', 'The title field is required.')
            ->json('props.modal.key');

        $this->assertIsString($key);
        $this->assertNotSame('', $key);
    }

    public function test_partial_modal_response_preserves_history_encryption()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::encryptHistory();

        $this->get(route('posts.features', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Posts/Index',
            'X-Inertia-Partial-Data' => 'modal.props.stats',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('encryptHistory', true);
    }

    public function test_partial_modal_response_uses_custom_url_resolver()
    {
        $post = Post::create(['content' => 'test content']);

        Inertia::resolveUrlUsing(fn () => '/resolved-url');

        $this->get(route('posts.features', [$post]), [
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Posts/Index',
            'X-Inertia-Partial-Data' => 'modal.props.stats',
            'referer' => route('home'),
        ])
            ->assertSuccessful()
            ->assertJsonPath('url', '/resolved-url');
    }
}
