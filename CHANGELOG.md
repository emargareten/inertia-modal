# Changelog

All notable changes to `inertia-modal` will be documented in this file.

## v3.0.0 - Unreleased

This release modernizes `inertia-modal` for Inertia Laravel v3 and makes the package a thinner adapter around Inertia's native request, response, prop-resolution, and client merge behavior.

### Breaking Changes

- Requires Inertia Laravel ^3.0.3.
- Replaces the Axios-based frontend adapter with Inertia's native HTTP client. Applications no longer need to configure Axios for this package, but custom Axios interceptors will no longer affect modal responses.
- `with()` now follows Inertia's native `Response::with()` behavior. Array props are merged, string keys use `with($key, $value)`, and `ProvidesInertiaProperties` instances are appended for Inertia to resolve.
- Fresh modal navigations now generate a new modal key even when the previous modal key header is present. Sparse modal prop reloads still preserve the current key.

### Changed

- Updated the frontend adapter to use Inertia's native HTTP client instead of Axios.
- Modal responses now go through Inertia's native response pipeline so modal props can use current Inertia features such as deferred props, merge props, prepend/deep merge metadata, once props, rescued props, shared props, custom URL resolvers, and encrypted history.
- Direct modal URL visits now dispatch the configured backdrop URL through Laravel's router pipeline, including middleware, route model binding, route events, and normal response preparation.
- Modal component names now follow Inertia component conventions, including component transformers and PHP backed/unit enums.
- Modal keys now distinguish fresh modal navigations from sparse modal prop reloads so new modals do not inherit stale modal state.
- Frontend backdrop preservation now keeps modal partial responses sparse so Inertia's own client merge logic can apply append, prepend, match-on, and deep merge behavior.
- Drops the Laravel Mix setup from the documentation in favor of Vite/Laravel setup.

### Added

- Support for partial reloads and deferred modal prop requests using nested `modal.props.*` paths.
- Support for dot-notation modal props.
- Support for excluding expensive shared props from modal-only responses through the published config.
- Frontend tests for backdrop metadata handling and stale modal metadata pruning.
- Backend tests covering Inertia v3 metadata, component transformers, enum components, modal keys, direct modal visits, refreshed backdrops, partial reloads, custom URL resolvers, and encrypted history.
- TypeScript package definitions for the Vue plugin and `useModal()` composable.

### Fixed

- Fixed double-transforming already-transformed `X-Inertia-Partial-Component` headers during modal partial reloads.
- Fixed direct modal visits and refreshed backdrop responses returning untransformed modal component names when an Inertia component transformer is configured.
- Fixed stale modal merge, deferred, once, and scroll metadata leaking into newly opened modals.
- Fixed duplicated array items when modal props use Inertia merge metadata.
- Fixed direct modal visits bypassing base route middleware.
- Fixed partial modal responses losing Inertia history encryption or custom URL resolver behavior.
- Fixed modal-marked frontend responses without `props` from clearing the backdrop props.

### Removed

- Removed manual backend prop resolution that duplicated Inertia internals.
- Removed Axios-specific frontend assumptions.
