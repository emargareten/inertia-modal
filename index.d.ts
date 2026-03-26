declare module 'inertia-modal' {
    import type { VisitOptions } from '@inertiajs/core'
    import type { Component, Plugin, Ref, VNode } from 'vue'

    export interface ModalPluginOptions {
        resolve: (name: string) => Promise<unknown> | unknown
    }

    export const modal: Plugin<[ModalPluginOptions]>
    export const Modal: Component

    export function useModal(): {
        show: Ref<boolean>
        vnode: Ref<VNode | string | false | undefined>
        close: () => void
        redirect: (options?: VisitOptions) => void
        props: Ref<Record<string, unknown> | undefined>
    }
}

export {}
