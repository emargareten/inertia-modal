import { defineComponent } from "vue";
import { useModal } from "./useModal";

export const Modal = defineComponent({
  setup() {
    const { vnode, close } = useModal();

    onBeforeUnmount(() => {
      close();
    });

    return () => vnode.value;
  },
});
