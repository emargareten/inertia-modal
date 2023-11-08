(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports, require('vue'), require('axios'), require('@inertiajs/vue3')) :
  typeof define === 'function' && define.amd ? define(['exports', 'vue', 'axios', '@inertiajs/vue3'], factory) :
  (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global["inertia-modal"] = {}, global.vue, global.axios, global.vue3));
})(this, (function (exports, vue, axios, vue3) { 'use strict';

  const resolveCallback = vue.ref();

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

      if (response.headers['x-inertia-modal']) {
        let { component, props } = vue3.usePage();
        props = JSON.parse(JSON.stringify(props));
        response.data.props = { ...props, ...response.data.props };
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

  const modal = vue.computed(() => vue3.usePage()?.props?.modal);
  const props = vue.computed(() => modal.value?.props);
  const key = vue.computed(() => modal.value?.key);

  const componentName = vue.ref();
  const component = vue.shallowRef();
  const show = vue.ref(false);
  const vnode = vue.ref();

  vue3.router.on('before', (event) => {
    event.detail.visit.headers['X-Inertia-Modal-Key'] = key.value;
    event.detail.visit.headers['X-Inertia-Modal-Redirect'] = modal.value?.redirectURL;
  });

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
        component.value = vue.defineAsyncComponent(() => resolver.resolve(componentName.value));
      } else {
        component.value = false;
      }
    }

    vnode.value = component.value
      ? vue.h(component.value, {
        key: key.value,
        ...props.value,
      })
      : '';

    vue.nextTick(() => (show.value = true));
  };

  vue.watch(modal, resolveComponent, {
      deep: true,
      immediate: true,
  });

  const redirect = () => {
    const redirectURL = modal.value?.redirectURL;

    vnode.value = false;

    if (!redirectURL) {
      return
    }

    return vue3.router.visit(redirectURL, {
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

  const Modal = vue.defineComponent({
    setup() {
      const { vnode } = useModal();

      return () => vnode.value
    },
  });

  exports.Modal = Modal;
  exports.modal = plugin;
  exports.useModal = useModal;

}));
