export default {
    input: 'js/index.js',
    output: [
        {
            file: 'dist/inertia-modal.umd.js',
            format: 'umd',
            name: 'inertia-modal',
        },
        {
            file: 'dist/inertia-modal.js',
            format: 'es',
        }],
    external: ['@inertiajs/vue3', 'axios', 'vue'],
}
