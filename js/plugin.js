import resolver from './resolver'
import preserveBackdrop from './preserveBackdrop'

export const plugin = {
  install(app, options = {}) {
    if (typeof options.resolve !== 'function') {
      throw new Error('Inertia Modal requires a resolve option when installing the plugin.')
    }

    resolver.setResolveCallback(options.resolve)

    preserveBackdrop(app)
  },
}
