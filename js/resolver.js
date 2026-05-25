import { ref } from 'vue'

const resolveCallback = ref()

export default {
  setResolveCallback: (callback) => {
    resolveCallback.value = callback
  },
  resolve: (name) => resolveCallback.value(name),
  resolveComponent: (name) => {
    if (!resolveCallback.value) {
      throw new Error('Inertia Modal requires a component resolver. Pass a resolve callback when installing the modal plugin.')
    }

    return Promise.resolve(resolveCallback.value(name)).then((module) => module.default || module)
  },
}
