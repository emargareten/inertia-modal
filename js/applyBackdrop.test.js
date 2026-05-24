import assert from 'node:assert/strict'
import { test } from 'node:test'
import { applyBackdrop } from './applyBackdrop.js'

const backdropPage = (overrides = {}) => ({
  component: 'Posts/Index',
  props: {
    posts: [{ id: 1 }],
    modal: {
      key: 'modal-1',
      component: 'Posts/Show',
      props: { comments: [{ id: 1 }] },
    },
  },
  ...overrides,
})

test('new modal via only:[modal] does not append the previous modal merge arrays', () => {
  const currentPage = backdropPage({ mergeProps: ['modal.props.comments'] })

  const data = applyBackdrop(currentPage, {
    component: 'Posts/Show',
    props: {
      modal: {
        key: 'modal-2',
        component: 'Posts/Show',
        props: { comments: [{ id: 2 }] },
      },
    },
    mergeProps: ['modal.props.comments'],
  })

  // Modal merge path stripped, so Inertia will not append onto the old modal.
  assert.deepEqual(data.mergeProps, [])
  // New modal props replace the previous modal's props.
  assert.deepEqual(data.props.modal.props.comments, [{ id: 2 }])
  // Backdrop component is preserved.
  assert.equal(data.component, 'Posts/Index')
})

test('new modal keeps non-modal backdrop merge paths', () => {
  const currentPage = backdropPage({ mergeProps: ['posts', 'modal.props.comments'] })

  const data = applyBackdrop(currentPage, {
    props: {
      modal: { key: 'modal-2', component: 'Posts/Other', props: {} },
    },
    mergeProps: ['modal.props.comments'],
  })

  assert.deepEqual(data.mergeProps, ['posts'])
})

test('sparse same-modal reload preserves modal merge metadata', () => {
  const currentPage = backdropPage({ mergeProps: ['modal.props.comments'] })

  // Sparse reload: no modal.key in response, so it targets the mounted modal.
  const data = applyBackdrop(currentPage, {
    props: { modal: { props: { comments: [{ id: 2 }] } } },
    mergeProps: ['modal.props.comments'],
  })

  assert.deepEqual(data.mergeProps, ['modal.props.comments'])
})

test('new modal strips stale deferred paths but keeps the new modal deferred', () => {
  const currentPage = backdropPage({
    deferredProps: { default: ['modal.props.old', 'posts'] },
  })

  const data = applyBackdrop(currentPage, {
    props: { modal: { key: 'modal-2', component: 'Posts/Show', props: {} } },
    deferredProps: { default: ['modal.props.stats'] },
  })

  assert.ok(data.deferredProps.default.includes('modal.props.stats'))
  assert.ok(data.deferredProps.default.includes('posts'))
  assert.ok(!data.deferredProps.default.includes('modal.props.old'))
})

test('new modal keeps its own scroll metadata and drops the previous modal scroll paths', () => {
  const currentPage = backdropPage({
    scrollProps: {
      posts: { pageName: 'page' },
      'modal.props.old': { pageName: 'oldPage' },
    },
  })

  const data = applyBackdrop(currentPage, {
    props: { modal: { key: 'modal-2', component: 'Posts/Show', props: {} } },
    scrollProps: { 'modal.props.items': { pageName: 'itemsPage' } },
  })

  assert.deepEqual(data.scrollProps['modal.props.items'], { pageName: 'itemsPage' })
  assert.deepEqual(data.scrollProps.posts, { pageName: 'page' })
  assert.ok(!('modal.props.old' in data.scrollProps))
})

test('initialDeferredProps folds in the response deferredProps', () => {
  const currentPage = backdropPage({
    initialDeferredProps: { default: ['posts'] },
    deferredProps: { default: ['posts'] },
  })

  const data = applyBackdrop(currentPage, {
    props: { modal: { key: 'modal-2', component: 'Posts/Show', props: {} } },
    deferredProps: { default: ['modal.props.stats'] },
  })

  assert.ok(data.initialDeferredProps.default.includes('modal.props.stats'))
})

test('partial new modal keeps deferred props after Inertia preserves current initialDeferredProps', () => {
  const currentPage = backdropPage({
    initialDeferredProps: { default: ['posts'] },
    deferredProps: { default: ['posts'] },
  })

  const data = applyBackdrop(currentPage, {
    props: { modal: { key: 'modal-2', component: 'Posts/Show', props: {} } },
    deferredProps: { default: ['modal.props.stats'] },
  })

  // Inertia core preserves initialDeferredProps from the mounted page during
  // partial response merging, after response handlers have run.
  data.initialDeferredProps = currentPage.initialDeferredProps

  assert.ok(data.initialDeferredProps.default.includes('modal.props.stats'))
})

test('partial modal response merges flash, full modal replaces it', () => {
  const currentPage = backdropPage({ flash: { message: 'old' } })

  const sparse = applyBackdrop(currentPage, {
    props: { modal: { props: { comments: [] } } },
    flash: { error: 'new' },
  })
  assert.deepEqual(sparse.flash, { message: 'old', error: 'new' })

  const fresh = applyBackdrop(backdropPage({ flash: { message: 'old' } }), {
    props: { modal: { key: 'modal-2', component: 'Posts/Show', props: {} } },
    flash: { error: 'new' },
  })
  assert.deepEqual(fresh.flash, { error: 'new' })
})
