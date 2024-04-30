import { router, usePage } from '@inertiajs/vue3'
import { defineAsyncComponent, h, nextTick, watch, computed, ref, shallowRef } from 'vue'
import resolver from './resolver'

const page = usePage()
const modal = computed(() => page?.props?.modal);
const props = computed(() => modal.value?.props)
const key = computed(() => modal.value?.key)

const componentName = ref()
const component = shallowRef()
const show = ref(false)
const vnode = ref()

if (typeof document !== 'undefined') {
  router.on('before', (event) => {
    event.detail.visit.headers['X-Inertia-Modal-Key'] = key.value
    event.detail.visit.headers['X-Inertia-Modal-Redirect'] = modal.value?.redirectURL
  })
}

const close = () => {
  show.value = false
}

const resolveComponent = () => {
  if (!modal.value?.component) {
    return close()
  }

  if (componentName.value !== modal.value?.component) {
    componentName.value = modal.value.component

    if (componentName.value) {
      component.value = defineAsyncComponent(() => resolver.resolve(componentName.value))
    } else {
      component.value = false
    }
  }

  vnode.value = component.value
    ? h(component.value, {
      key: key.value,
      ...props.value,
    })
    : ''

  nextTick(() => (show.value = true))
}

watch(modal, resolveComponent, {
    deep: true,
    immediate: true,
})

/**
 * @param {import('@inertiajs/core').VisitOptions} options
 */
const redirect = (options = {}) => {
  const redirectURL = modal.value?.redirectURL

  vnode.value = false

  if (!redirectURL) {
    return
  }

  return router.visit(redirectURL, options)
}

export const useModal = () => {
  return {
    show,
    vnode,
    close,
    redirect,
    props,
  }
}
