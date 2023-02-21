import { router, usePage } from '@inertiajs/vue3'
import { defineAsyncComponent, h, nextTick, watch, computed, ref, shallowRef } from 'vue'
import axios from 'axios'
import resolver from './resolver'

const modal = computed(() => usePage()?.props?.modal);
const props = computed(() => modal.value?.props)
const key = computed(() => modal.value?.key)

const componentName = ref()
const component = shallowRef()
const show = ref(false)
const vnode = ref()

const setHeaders = () => {
  axios.defaults.headers.common['X-Inertia-Modal-Key'] = key.value
  axios.defaults.headers.common['X-Inertia-Modal-Redirect'] = modal.value?.redirectURL
}

const resetHeaders = () => {
  delete axios.defaults.headers.common['X-Inertia-Modal-Key']
  delete axios.defaults.headers.common['X-Inertia-Modal-Redirect']
}

const close = () => {
  show.value = false
  resetHeaders()
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

watch(key, setHeaders)

const redirect = () => {
  const redirectURL = modal.value?.redirectURL

  vnode.value = false

  if (!redirectURL) {
    return
  }

  return router.visit(redirectURL, {
    preserveScroll: true,
    preserveState: true,
  })
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
