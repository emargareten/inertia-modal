import { ref } from 'vue'

const resolveCallback = ref()

export default {
  setResolveCallback: (callback) => {
    resolveCallback.value = callback
  },
  resolve: (name) => resolveCallback.value(name),
}
