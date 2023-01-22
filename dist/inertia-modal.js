import { ref, computed, shallowRef, watch, defineAsyncComponent, h, nextTick, defineComponent } from 'vue';
import axios from 'axios';
import { usePage, router } from '@inertiajs/vue3';

const resolveCallback = ref();

var resolver = {
  setResolveCallback: (callback) => {
    resolveCallback.value = callback;
  },
  resolve: (name) => resolveCallback.value(name),
};

const props$1 = computed(() => usePage().props);
const component$1 = computed(() => usePage().component);

/**
 * Reuse current (stale) props and component for the modal backdrop
 */
function preserveBackdrop () {
  axios.interceptors.response.use(function(response) {
    if(response.headers['x-inertia'] && response.data.props?.modal) {
      let oldProps = JSON.parse(JSON.stringify(props$1.value));
      response.data.props = { ...oldProps, ...response.data.props };
      response.data.component = component$1.value;
    }
    return response
  });
}

const plugin = {
  install(app, options) {
    resolver.setResolveCallback(options.resolve);

    preserveBackdrop();
  },
};

const response = computed(() => usePage().props);
const modal = computed(() => response.value?.modal);
const props = computed(() => modal.value?.props);
const key = computed(() => modal.value?.key);

const componentName = ref();
const component = shallowRef();
const show = ref(false);
const vnode = ref();

const setHeaders = () => {
  axios.defaults.headers.common['X-Inertia-Modal-Key'] = key.value;
  axios.defaults.headers.common['X-Inertia-Modal-Redirect'] = modal.value?.redirectURL;
};

const resetHeaders = () => {
  delete axios.defaults.headers.common['X-Inertia-Modal-Key'];
  delete axios.defaults.headers.common['X-Inertia-Modal-Redirect'];
};

const close = () => {
  show.value = false;
  resetHeaders();
};

const resolveComponent = () => {
  if (!modal.value?.component) {
    return close()
  }

  if (componentName.value !== modal.value?.component) {
    componentName.value = modal.value.component;

    if (componentName.value) {
      component.value = defineAsyncComponent(() => resolver.resolve(componentName.value));
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

resolveComponent();

watch(
  () => modal.value,
  () => {
      resolveComponent();
  },
  { deep: true }
);
watch(() => key.value, setHeaders);

const redirect = () => {
  const redirectURL = modal.value?.redirectURL ?? modal.value?.baseURL;

  vnode.value = false;

  if (!redirectURL) {
    return
  }

  return router.visit(redirectURL, {
    preserveScroll: true,
    preserveState: true,
  })
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
