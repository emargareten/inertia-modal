import { ref, computed, shallowRef, watch, defineAsyncComponent, h, nextTick, defineComponent } from 'vue';
import { http, usePage, router } from '@inertiajs/vue3';

const resolveCallback = ref();

var resolver = {
  setResolveCallback: (callback) => {
    resolveCallback.value = callback;
  },
  resolve: (name) => resolveCallback.value(name),
  resolveComponent: (name) => {
    if (!resolveCallback.value) {
      throw new Error('Inertia Modal requires a component resolver. Pass a resolve callback when installing the modal plugin.')
    }

    return Promise.resolve(resolveCallback.value(name)).then((module) => module.default || module)
  },
};

const PAGE_METADATA_KEYS = [
  'scrollProps',
  'mergeProps',
  'prependProps',
  'deepMergeProps',
  'matchPropsOn',
  'deferredProps',
  'initialDeferredProps',
  'sharedProps',
  'onceProps',
  'rescuedProps',
];

// Metadata that makes Inertia merge incoming arrays/objects onto the currently
// mounted props (see ResponseFactory.mergeProps in @inertiajs/core). For a new
// modal these paths must be dropped so the modal's props replace rather than
// append onto the previous modal's props. scrollProps is intentionally excluded:
// it is not a merge trigger and a new modal needs its own scroll metadata kept.
const MERGE_METADATA_KEYS = [
  'mergeProps',
  'prependProps',
  'deepMergeProps',
  'matchPropsOn',
];

const clone = (value) => {
  if (value === undefined || value === null) {
    return value
  }

  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(value)
    } catch {
      // Fall back to JSON cloning for Vue proxies and other JSON-safe page data.
    }
  }

  return JSON.parse(JSON.stringify(value))
};

const isObject = (value) => value && typeof value === 'object' && !Array.isArray(value);

const REMOVED = Symbol('removed');

const isEmptyContainer = (value) =>
  (Array.isArray(value) && value.length === 0) ||
  (isObject(value) && Object.keys(value).length === 0);

const mergePageValue = (currentValue, responseValue) => {
  if (responseValue === undefined) {
    return clone(currentValue)
  }

  if (currentValue === undefined) {
    return clone(responseValue)
  }

  if (Array.isArray(currentValue) || Array.isArray(responseValue)) {
    return [...new Set([...(currentValue || []), ...(responseValue || [])])]
  }

  if (isObject(currentValue) && isObject(responseValue)) {
    return Object.keys(responseValue).reduce((merged, key) => ({
      ...merged,
      [key]: mergePageValue(currentValue[key], responseValue[key]),
    }), clone(currentValue))
  }

  return clone(responseValue)
};

const isModalPath = (value) => typeof value === 'string' && (value === 'modal' || value.startsWith('modal.'));

const withoutModalPaths = (value) => {
  if (isModalPath(value)) {
    return REMOVED
  }

  if (Array.isArray(value)) {
    return value
      .map((item) => withoutModalPaths(item))
      .filter((item) => item !== REMOVED)
  }

  if (isObject(value)) {
    return Object.fromEntries(
      Object.entries(value)
        .filter(([key, item]) => !isModalPath(key) && !isModalPath(item?.prop))
        .map(([key, item]) => [key, withoutModalPaths(item)])
        .filter(([, item]) => item !== REMOVED && !isEmptyContainer(item))
    )
  }

  return value
};

const isPartialModalResponse = (responseProps) => !responseProps?.modal?.key;

const isNewModal = (currentProps, responseProps) => {
  const current = currentProps?.modal;
  const response = responseProps?.modal;

  if (!current || !response) {
    return false
  }

  // A partial visit to a different modal (e.g. `only: ['modal']`) carries the
  // previous modal key, so fall back to the component name to detect the swap.
  if (response.component && current.component && response.component !== current.component) {
    return true
  }

  return Boolean(response.key && current.key && response.key !== current.key)
};

const propsForBackdrop = (currentProps, responseProps) => {
  if (isPartialModalResponse(responseProps)) {
    return clone(responseProps)
  }

  return {
    ...clone(currentProps),
    ...clone(responseProps),
  }
};

/**
 * Rewrite an Inertia modal response so the previous page is reused as the
 * backdrop, while preventing stale modal props/metadata from leaking into a
 * newly opened modal. Mutates and returns `data` (the parsed response body).
 */
function applyBackdrop(currentPage, data) {
  const responseProps = data.props || {};
  const hasNewModal = isNewModal(currentPage.props, responseProps);
  const partialModalResponse = isPartialModalResponse(responseProps);

  data.component = currentPage.component;
  data.props = propsForBackdrop(currentPage.props, responseProps);

  for (const key of PAGE_METADATA_KEYS) {
    const current = currentPage[key];
    const incoming = data[key];

    if (current === undefined && incoming === undefined) {
      continue
    }

    let merged = mergePageValue(
      hasNewModal && current !== undefined ? withoutModalPaths(current) : current,
      incoming,
    );

    // Drop the new modal's own merge-family paths so Inertia replaces its props
    // instead of appending them onto the previous (still mounted) modal's props.
    if (hasNewModal && MERGE_METADATA_KEYS.includes(key)) {
      merged = withoutModalPaths(merged);
    }

    data[key] = merged;
  }

  // Inertia only seeds initialDeferredProps from deferredProps when it is unset.
  // When the backdrop already carries initialDeferredProps, fold in the response's
  // deferredProps so history restores can reload the new modal's deferred props.
  if (data.initialDeferredProps && data.deferredProps) {
    data.initialDeferredProps = mergePageValue(data.initialDeferredProps, data.deferredProps);

    // Inertia's partial-response merge later preserves initialDeferredProps from
    // the currently mounted page. Keep that source in sync so new modal deferred
    // paths survive the later merge step.
    if (currentPage.initialDeferredProps) {
      currentPage.initialDeferredProps = clone(data.initialDeferredProps);
    }
  }

  data.flash = partialModalResponse
    ? mergePageValue(currentPage.flash, data.flash || {})
    : data.flash || {};

  return data
}

const hasHeader = (headers, name) => Object.keys(headers || {})
  .some((header) => header.toLowerCase() === name.toLowerCase());

/**
 * Reuse current props and component for the modal backdrop
 */
function preserveBackdrop (app) {
  http.onResponse((response) => {
    if (!hasHeader(response.headers, 'x-inertia-modal')) {
      return response
    }

    const currentPage = app.config.globalProperties.$page;

    if (!currentPage) {
      return response
    }

    response.data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
    response.data = applyBackdrop(currentPage, response.data);

    response.headers['x-inertia'] = 'true';

    return response
  });
}

const plugin = {
  install(app, options = {}) {
    if (typeof options.resolve !== 'function') {
      throw new Error('Inertia Modal requires a resolve option when installing the plugin.')
    }

    resolver.setResolveCallback(options.resolve);

    preserveBackdrop(app);
  },
};

const page = usePage();
const modal = computed(() => page?.props?.modal);
const props = computed(() => modal.value?.props);
const key = computed(() => modal.value?.key);

const componentName = ref();
const component = shallowRef();
const show = ref(false);
const vnode = ref();

if (typeof document !== 'undefined') {
  router.on('before', (event) => {
    event.detail.visit.headers = event.detail.visit.headers || {};

    if (key.value) {
      event.detail.visit.headers['X-Inertia-Modal-Key'] = key.value;
    }

    if (modal.value?.redirectURL) {
      event.detail.visit.headers['X-Inertia-Modal-Redirect'] = modal.value.redirectURL;
    }
  });
}

const close = () => {
  show.value = false;
};

const resolveComponent = () => {
  if (!modal.value?.component) {
    return close()
  }

  if (componentName.value !== modal.value?.component) {
    componentName.value = modal.value.component;

    if (componentName.value) {
      component.value = defineAsyncComponent(() => resolver.resolveComponent(componentName.value));
    } else {
      component.value = false;
    }
  }

  vnode.value = component.value
    ? h(component.value, {
      key: key.value,
      ...props.value,
    })
    : '';

  nextTick(() => (show.value = true));
};

watch(modal, resolveComponent, {
  deep: true,
  immediate: true,
});

/**
 * @param {import('@inertiajs/core').VisitOptions} options
 */
const redirect = (options = {}) => {
  const redirectURL = modal.value?.redirectURL;

  vnode.value = false;

  if (!redirectURL) {
    return
  }

  return router.visit(redirectURL, options)
};

const useModal = () => {
  return {
    show,
    vnode,
    close,
    redirect,
    props,
  }
};

const Modal = defineComponent({
  setup() {
    const { vnode } = useModal();

    return () => vnode.value
  },
});

export { Modal, plugin as modal, useModal };
