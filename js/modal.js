import { defineComponent } from 'vue'
import { useModal } from './useModal'

export const Modal = defineComponent({
  setup() {
    const { vnode } = useModal()

    return () => vnode.value
  },
})
