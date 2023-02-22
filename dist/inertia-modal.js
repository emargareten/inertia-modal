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

/**
 * Reuse current props and component for the modal backdrop
 */
function preserveBackdrop () {
  axios.interceptors.response.use(function(response) {

    if(response.headers['x-inertia-modal']) {
      let { component, props } = usePage();
      props = JSON.parse(JSON.stringify(props));
      response.data.props = { ...props, ...response.data };
      response.data.component = component;
      response.headers['x-inertia'] = true;
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

const modal = computed(() => usePage()?.props?.modal);
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

watch(modal, resolveComponent, {
    deep: true,
    immediate: true,
});

watch(key, setHeaders);

const redirect = () => {
  const redirectURL = modal.value?.redirectURL;

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
